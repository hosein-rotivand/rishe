<?php
/**
 * JSON API front controller: CORS, routing, token auth and responses.
 */

declare(strict_types=1);

final class Rishe_Api {

	private const TOKEN_DAYS = 60;

	/** @var array<int, array{0: string, 1: string, 2: callable, 3: bool}> */
	private static array $routes = [];

	private static ?array $user = null;

	public static function routes(): void {
		self::$routes = [
			['GET', 'config', [Rishe_Api_Auth::class, 'config'], false],
			['POST', 'auth/register', [Rishe_Api_Auth::class, 'register'], false],
			['POST', 'auth/login', [Rishe_Api_Auth::class, 'login'], false],
			['POST', 'auth/otp/send', [Rishe_Api_Auth::class, 'otp_send'], false],
			['POST', 'auth/otp/verify', [Rishe_Api_Auth::class, 'otp_verify'], false],
			['POST', 'auth/password/reset', [Rishe_Api_Auth::class, 'password_reset'], false],
			['POST', 'auth/logout', [Rishe_Api_Auth::class, 'logout'], true],
			['GET', 'me', [Rishe_Api_Auth::class, 'me'], true],
			['POST', 'me', [Rishe_Api_Auth::class, 'update_me'], true],
			['GET', 'courses', [Rishe_Api_Learning::class, 'courses'], true],
			['GET', 'courses/(\d+)', [Rishe_Api_Learning::class, 'course'], true],
			['GET', 'courses/(\d+)/exam', [Rishe_Api_Learning::class, 'exam'], true],
			['POST', 'courses/(\d+)/exam', [Rishe_Api_Learning::class, 'submit_exam'], true],
			['GET', 'lessons/(\d+)', [Rishe_Api_Learning::class, 'lesson'], true],
			['POST', 'lessons/(\d+)/complete', [Rishe_Api_Learning::class, 'complete_lesson'], true],
			['GET', 'certificates', [Rishe_Api_Account::class, 'certificates'], true],
			['POST', 'certificates', [Rishe_Api_Account::class, 'request_certificate'], true],
			['GET', 'certificates/([A-Za-z0-9-]+)', [Rishe_Api_Account::class, 'certificate'], true],
			['GET', 'plans', [Rishe_Api_Account::class, 'plans'], true],
			['POST', 'payments', [Rishe_Api_Account::class, 'start_payment'], true],
		];
	}

	public static function handle(): void {
		header('Content-Type: application/json; charset=utf-8');
		header('X-Content-Type-Options: nosniff');
		header('Cache-Control: no-store');
		// Token auth (no cookies), so any origin may call the API: the Capacitor app runs on https://localhost.
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Auth-Token');
		header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
		header('Access-Control-Max-Age: 86400');

		$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
		if ($method === 'OPTIONS') {
			http_response_code(204);
			exit;
		}

		self::routes();
		$path = self::path();

		try {
			foreach (self::$routes as [$verb, $pattern, $handler, $auth]) {
				if (!preg_match('#^' . $pattern . '$#', $path, $m)) {
					continue;
				}
				if ($verb !== $method) {
					continue;
				}
				if ($auth) {
					self::require_user();
				}
				array_shift($m);
				call_user_func_array($handler, $m);
				return;
			}
			self::error('مسیر درخواست‌شده وجود ندارد.', 404, 'not_found');
		} catch (RuntimeException $e) {
			self::error($e->getMessage(), 422, 'invalid');
		} catch (Throwable $e) {
			error_log('Rishe API: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
			self::error('خطای داخلی سرور. کمی بعد دوباره تلاش کنید.', 500, 'server_error');
		}
	}

	private static function path(): string {
		if (isset($_GET['route']) && is_string($_GET['route'])) {
			$path = $_GET['route'];
		} else {
			$uri  = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
			$pos  = strpos($uri, '/api/');
			$path = $pos === false ? '' : substr($uri, $pos + 5);
			$path = preg_replace('#^(index\.php/?)?(v1/)?#', '', $path) ?? '';
		}
		return trim($path, '/');
	}

	/**
	 * @return never
	 */
	public static function json(array $data, int $status = 200): void {
		http_response_code($status);
		echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}

	/**
	 * @return never
	 */
	public static function error(string $message, int $status = 400, string $code = 'error', array $extra = []): void {
		self::json(array_merge(['ok' => false, 'code' => $code, 'message' => $message], $extra), $status);
	}

	public static function input(): array {
		static $input = null;
		if ($input !== null) {
			return $input;
		}
		$raw   = file_get_contents('php://input', false, null, 0, 1048576) ?: '';
		$data  = json_decode($raw, true);
		$input = is_array($data) ? $data : $_POST;
		return $input;
	}

	public static function issue_token(int $user_id): string {
		$token = bin2hex(random_bytes(32));
		Rishe_DB::insert('rishe_tokens', [
			'user_id'    => $user_id,
			'token_hash' => hash('sha256', $token),
			'expires_at' => date('Y-m-d H:i:s', strtotime('+' . self::TOKEN_DAYS . ' days')),
			'created_at' => rishe_now(),
		]);
		if (random_int(1, 50) === 1) {
			Rishe_DB::query('DELETE FROM rishe_tokens WHERE expires_at < ?', [rishe_now()]);
		}
		Rishe_DB::update('rishe_users', ['last_login_at' => rishe_now()], ['id' => $user_id]);
		return $token;
	}

	public static function bearer(): string {
		$header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
		if ($header === '' && function_exists('getallheaders')) {
			foreach (getallheaders() as $name => $value) {
				if (strtolower((string) $name) === 'authorization') {
					$header = (string) $value;
				}
			}
		}
		if (preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim($header), $m)) {
			return strtolower($m[1]);
		}
		$alt = (string) ($_SERVER['HTTP_X_AUTH_TOKEN'] ?? '');
		return preg_match('/^[a-f0-9]{64}$/i', $alt) ? strtolower($alt) : '';
	}

	public static function user(): ?array {
		if (self::$user !== null) {
			return self::$user;
		}
		$token = self::bearer();
		if ($token === '') {
			return null;
		}
		$user = Rishe_DB::row(
			"SELECT u.* FROM rishe_tokens t INNER JOIN rishe_users u ON u.id = t.user_id
			 WHERE t.token_hash = ? AND t.expires_at > ? AND u.status = 'active'",
			[hash('sha256', $token), rishe_now()]
		);
		self::$user = $user;
		return $user;
	}

	public static function require_user(): array {
		$user = self::user();
		if (!$user) {
			self::error('لطفاً دوباره وارد حساب شوید.', 401, 'unauthorized');
		}
		return $user;
	}

	public static function user_payload(array $user): array {
		$sub = Rishe_Learning::active_subscription((int) $user['id']);
		return [
			'id'           => (int) $user['id'],
			'name'         => $user['name'],
			'name_en'      => $user['name_en'],
			'mobile'       => $user['mobile'],
			'has_password' => !empty($user['password_hash']),
			'stats'        => Rishe_Learning::stats((int) $user['id']),
			'subscription' => $sub ? [
				'plan'      => $sub['plan_title'],
				'ends_at'   => rishe_jalali($sub['ends_at']),
				'days_left' => max(0, (int) ceil((strtotime($sub['ends_at']) - time()) / 86400)),
			] : null,
		];
	}
}
