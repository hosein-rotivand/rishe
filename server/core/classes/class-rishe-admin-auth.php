<?php
/**
 * Admin session authentication.
 */

declare(strict_types=1);

final class Rishe_Admin_Auth {

	public static function start_session(): void {
		if (session_status() === PHP_SESSION_ACTIVE) {
			return;
		}
		$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
		session_name('rishe_admin');
		session_set_cookie_params([
			'lifetime' => 0,
			'path'     => '/',
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		]);
		ini_set('session.use_strict_mode', '1');
		session_start();
	}

	public static function attempt(string $email, string $password): bool {
		$key = 'admin-login|' . Rishe_Throttle::client_ip();
		if (!Rishe_Throttle::hit($key, 8, 900)) {
			return false;
		}
		$admin = Rishe_DB::row('SELECT * FROM rishe_admins WHERE email = ?', [mb_strtolower($email)]);
		if (!$admin || !password_verify($password, $admin['password_hash'])) {
			usleep(300000);
			return false;
		}
		Rishe_Throttle::clear($key);
		session_regenerate_id(true);
		$_SESSION['rishe_admin_id'] = (int) $admin['id'];
		$_SESSION['rishe_admin_ua'] = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
		Rishe_DB::update('rishe_admins', ['last_login_at' => rishe_now()], ['id' => $admin['id']]);
		if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
			Rishe_DB::update('rishe_admins', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => $admin['id']]);
		}
		return true;
	}

	public static function is_locked(): bool {
		$row = Rishe_DB::row('SELECT hits, window_start FROM rishe_throttle WHERE throttle_key = ?', [substr(hash('sha256', 'admin-login|' . Rishe_Throttle::client_ip()), 0, 64)]);
		return $row && (int) $row['hits'] > 8 && strtotime($row['window_start']) + 900 > time();
	}

	public static function user(): ?array {
		if (empty($_SESSION['rishe_admin_id'])) {
			return null;
		}
		if (($_SESSION['rishe_admin_ua'] ?? '') !== hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
			self::logout();
			return null;
		}
		return Rishe_DB::row('SELECT id, name, email FROM rishe_admins WHERE id = ?', [(int) $_SESSION['rishe_admin_id']]);
	}

	public static function logout(): void {
		$_SESSION = [];
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_regenerate_id(true);
			session_destroy();
		}
	}
}
