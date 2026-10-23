<?php
/**
 * API: app config, registration, login (password / OTP), profile.
 */

declare(strict_types=1);

final class Rishe_Api_Auth {

	private const OTP_TTL = 120;

	public static function config(): void {
		Rishe_Api::json([
			'ok'               => true,
			'app_name'         => Rishe_Settings::get('app_name'),
			'app_name_en'      => Rishe_Settings::get('app_name_en'),
			'tagline'          => Rishe_Settings::get('app_tagline'),
			'sms_enabled'      => Rishe_Sms::enabled(),
			'payments_enabled' => Rishe_Settings::payments_active(),
			'certificate_fee'  => Rishe_Certificates::fee(),
		]);
	}

	public static function register(): void {
		$in      = Rishe_Api::input();
		$name    = rishe_str($in, 'name', 120);
		$name_en = rishe_str($in, 'name_en', 120);
		$mobile  = rishe_normalize_mobile(rishe_str($in, 'mobile', 20));
		$pass    = isset($in['password']) && is_string($in['password']) ? $in['password'] : '';

		if (!Rishe_Throttle::hit('register|' . Rishe_Throttle::client_ip(), 10, 3600)) {
			Rishe_Api::error('تعداد تلاش‌ها زیاد است. کمی بعد دوباره امتحان کنید.', 429, 'throttled');
		}
		if (mb_strlen($name) < 3) {
			Rishe_Api::error('نام و نام خانوادگی را کامل وارد کنید.', 422, 'name');
		}
		if (!preg_match('/^[A-Za-z][A-Za-z .\'-]{2,119}$/', $name_en)) {
			Rishe_Api::error('نام انگلیسی را فقط با حروف انگلیسی وارد کنید.', 422, 'name_en');
		}
		if (!$mobile) {
			Rishe_Api::error('شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.', 422, 'mobile');
		}
		if (mb_strlen($pass) < 6) {
			Rishe_Api::error('رمز عبور باید حداقل ۶ کاراکتر باشد.', 422, 'password');
		}
		if (Rishe_DB::value('SELECT 1 FROM rishe_users WHERE mobile = ?', [$mobile])) {
			Rishe_Api::error('این شماره قبلاً ثبت‌نام کرده است. وارد حساب شوید.', 409, 'exists');
		}
		if (Rishe_Sms::enabled() && !self::check_otp($mobile, rishe_str($in, 'code', 10))) {
			Rishe_Api::error('کد تأیید نادرست یا منقضی است.', 422, 'code');
		}

		$id = Rishe_DB::insert('rishe_users', [
			'name'          => $name,
			'name_en'       => $name_en,
			'mobile'        => $mobile,
			'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
			'status'        => 'active',
			'created_at'    => rishe_now(),
		]);
		self::respond_with_token($id);
	}

	public static function login(): void {
		$in     = Rishe_Api::input();
		$mobile = rishe_normalize_mobile(rishe_str($in, 'mobile', 20));
		$pass   = isset($in['password']) && is_string($in['password']) ? $in['password'] : '';
		if (!$mobile || $pass === '') {
			Rishe_Api::error('شماره موبایل و رمز عبور را وارد کنید.', 422, 'invalid');
		}
		if (!Rishe_Throttle::hit('login|' . $mobile, 8, 900) || !Rishe_Throttle::hit('login-ip|' . Rishe_Throttle::client_ip(), 30, 900)) {
			Rishe_Api::error('تعداد تلاش‌ها زیاد است. ۱۵ دقیقه‌ی دیگر امتحان کنید.', 429, 'throttled');
		}
		$user = Rishe_DB::row('SELECT * FROM rishe_users WHERE mobile = ?', [$mobile]);
		if (!$user || empty($user['password_hash']) || !password_verify($pass, $user['password_hash'])) {
			Rishe_Api::error('شماره موبایل یا رمز عبور اشتباه است.', 401, 'credentials');
		}
		if ($user['status'] !== 'active') {
			Rishe_Api::error('حساب شما مسدود شده است.', 403, 'blocked');
		}
		Rishe_Throttle::clear('login|' . $mobile);
		self::respond_with_token((int) $user['id']);
	}

	public static function otp_send(): void {
		if (!Rishe_Sms::enabled()) {
			Rishe_Api::error('ورود با پیامک فعال نیست.', 422, 'sms_disabled');
		}
		$in     = Rishe_Api::input();
		$mobile = rishe_normalize_mobile(rishe_str($in, 'mobile', 20));
		if (!$mobile) {
			Rishe_Api::error('شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.', 422, 'mobile');
		}
		if (!Rishe_Throttle::hit('otp-min|' . $mobile, 1, 60)) {
			Rishe_Api::error('برای ارسال دوباره‌ی کد یک دقیقه صبر کنید.', 429, 'throttled');
		}
		if (!Rishe_Throttle::hit('otp-hour|' . $mobile, 5, 3600) || !Rishe_Throttle::hit('otp-ip|' . Rishe_Throttle::client_ip(), 20, 3600)) {
			Rishe_Api::error('تعداد درخواست کد زیاد است. بعداً تلاش کنید.', 429, 'throttled');
		}

		$code = (string) random_int(10000, 99999);
		Rishe_DB::delete('rishe_otps', ['mobile' => $mobile]);
		Rishe_DB::insert('rishe_otps', [
			'mobile'     => $mobile,
			'code_hash'  => self::hash_code($mobile, $code),
			'attempts'   => 0,
			'expires_at' => date('Y-m-d H:i:s', time() + self::OTP_TTL),
			'created_at' => rishe_now(),
		]);
		if (!Rishe_Sms::send_otp($mobile, $code)) {
			Rishe_Api::error('ارسال پیامک انجام نشد. کمی بعد دوباره تلاش کنید.', 502, 'sms_failed');
		}
		Rishe_Api::json([
			'ok'         => true,
			'registered' => (bool) Rishe_DB::value('SELECT 1 FROM rishe_users WHERE mobile = ?', [$mobile]),
			'ttl'        => self::OTP_TTL,
		]);
	}

	public static function otp_verify(): void {
		$in     = Rishe_Api::input();
		$mobile = rishe_normalize_mobile(rishe_str($in, 'mobile', 20));
		if (!$mobile || !self::check_otp($mobile, rishe_str($in, 'code', 10))) {
			Rishe_Api::error('کد تأیید نادرست یا منقضی است.', 422, 'code');
		}
		$user = Rishe_DB::row('SELECT * FROM rishe_users WHERE mobile = ?', [$mobile]);
		if (!$user) {
			Rishe_Api::error('با این شماره حسابی وجود ندارد. ابتدا عضو شوید.', 404, 'not_registered');
		}
		if ($user['status'] !== 'active') {
			Rishe_Api::error('حساب شما مسدود شده است.', 403, 'blocked');
		}
		self::respond_with_token((int) $user['id']);
	}

	public static function password_reset(): void {
		$in     = Rishe_Api::input();
		$mobile = rishe_normalize_mobile(rishe_str($in, 'mobile', 20));
		$pass   = isset($in['password']) && is_string($in['password']) ? $in['password'] : '';
		if (mb_strlen($pass) < 6) {
			Rishe_Api::error('رمز عبور باید حداقل ۶ کاراکتر باشد.', 422, 'password');
		}
		if (!$mobile || !self::check_otp($mobile, rishe_str($in, 'code', 10))) {
			Rishe_Api::error('کد تأیید نادرست یا منقضی است.', 422, 'code');
		}
		$user = Rishe_DB::row('SELECT id FROM rishe_users WHERE mobile = ?', [$mobile]);
		if (!$user) {
			Rishe_Api::error('با این شماره حسابی وجود ندارد.', 404, 'not_registered');
		}
		Rishe_DB::update('rishe_users', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)], ['id' => $user['id']]);
		Rishe_DB::delete('rishe_tokens', ['user_id' => $user['id']]);
		self::respond_with_token((int) $user['id']);
	}

	public static function logout(): void {
		Rishe_DB::delete('rishe_tokens', ['token_hash' => hash('sha256', Rishe_Api::bearer())]);
		Rishe_Api::json(['ok' => true]);
	}

	public static function me(): void {
		Rishe_Api::json(['ok' => true, 'user' => Rishe_Api::user_payload(Rishe_Api::require_user())]);
	}

	public static function update_me(): void {
		$user    = Rishe_Api::require_user();
		$in      = Rishe_Api::input();
		$name    = rishe_str($in, 'name', 120);
		$name_en = rishe_str($in, 'name_en', 120);
		$data    = [];

		if ($name !== '') {
			if (mb_strlen($name) < 3) {
				Rishe_Api::error('نام و نام خانوادگی را کامل وارد کنید.', 422, 'name');
			}
			$data['name'] = $name;
		}
		if ($name_en !== '') {
			if (!preg_match('/^[A-Za-z][A-Za-z .\'-]{2,119}$/', $name_en)) {
				Rishe_Api::error('نام انگلیسی را فقط با حروف انگلیسی وارد کنید.', 422, 'name_en');
			}
			$data['name_en'] = $name_en;
		}
		if (isset($in['new_password']) && is_string($in['new_password']) && $in['new_password'] !== '') {
			$current = isset($in['password']) && is_string($in['password']) ? $in['password'] : '';
			if (!empty($user['password_hash']) && !password_verify($current, $user['password_hash'])) {
				Rishe_Api::error('رمز فعلی اشتباه است.', 422, 'password');
			}
			if (mb_strlen($in['new_password']) < 6) {
				Rishe_Api::error('رمز جدید باید حداقل ۶ کاراکتر باشد.', 422, 'new_password');
			}
			$data['password_hash'] = password_hash($in['new_password'], PASSWORD_DEFAULT);
		}
		if ($data) {
			Rishe_DB::update('rishe_users', $data, ['id' => $user['id']]);
		}
		$fresh = Rishe_DB::row('SELECT * FROM rishe_users WHERE id = ?', [$user['id']]);
		Rishe_Api::json(['ok' => true, 'user' => Rishe_Api::user_payload($fresh)]);
	}

	private static function respond_with_token(int $user_id): void {
		$token = Rishe_Api::issue_token($user_id);
		$user  = Rishe_DB::row('SELECT * FROM rishe_users WHERE id = ?', [$user_id]);
		Rishe_Api::json(['ok' => true, 'token' => $token, 'user' => Rishe_Api::user_payload($user)]);
	}

	private static function hash_code(string $mobile, string $code): string {
		return hash_hmac('sha256', $mobile . '|' . $code, (string) Rishe_Config::get('app_key', ''));
	}

	/**
	 * Validate and consume an OTP code (max 5 attempts).
	 */
	private static function check_otp(string $mobile, string $code): bool {
		$code = rishe_latin_digits($code);
		$otp  = Rishe_DB::row('SELECT * FROM rishe_otps WHERE mobile = ? ORDER BY id DESC LIMIT 1', [$mobile]);
		if (!$otp || strtotime($otp['expires_at']) < time() || (int) $otp['attempts'] >= 5 || !preg_match('/^\d{5}$/', $code)) {
			return false;
		}
		if (!hash_equals($otp['code_hash'], self::hash_code($mobile, $code))) {
			Rishe_DB::query('UPDATE rishe_otps SET attempts = attempts + 1 WHERE id = ?', [$otp['id']]);
			return false;
		}
		Rishe_DB::delete('rishe_otps', ['mobile' => $mobile]);
		return true;
	}
}
