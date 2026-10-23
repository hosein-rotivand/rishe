<?php
/**
 * Key/value settings stored in rishe_settings.
 */

declare(strict_types=1);

final class Rishe_Settings {

	/** Default values for every known setting. */
	public const DEFAULTS = [
		'app_name'          => 'ریشه',
		'app_name_en'       => 'Rishe',
		'app_tagline'       => 'آموزش آنلاین زبان انگلیسی',
		'payments_enabled'  => '0',
		'zibal_merchant'    => '',
		'certificate_fee'   => '0',
		'cert_prefix'       => 'RS',
		'cert_signer_name'  => '',
		'cert_signer_title' => 'مدیر آموزش',
		'sms_enabled'       => '0',
		'sms_api_key'       => '',
		'sms_template'      => '',
		'upload_max_mb'     => '10',
	];

	/** @var array<string, string>|null */
	private static ?array $cache = null;

	public static function all(): array {
		if (self::$cache === null) {
			self::$cache = self::DEFAULTS;
			foreach (Rishe_DB::rows('SELECT setting_key, setting_value FROM rishe_settings') as $row) {
				self::$cache[$row['setting_key']] = (string) $row['setting_value'];
			}
		}
		return self::$cache;
	}

	public static function get(string $key, string $default = ''): string {
		$all = self::all();
		return array_key_exists($key, $all) ? $all[$key] : $default;
	}

	public static function int(string $key): int {
		return (int) self::get($key, '0');
	}

	public static function enabled(string $key): bool {
		return self::get($key, '0') === '1';
	}

	public static function set(string $key, string $value): void {
		Rishe_DB::query(
			'INSERT INTO rishe_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
			[$key, $value]
		);
		self::$cache = null;
	}

	/**
	 * Payments are active only when switched on AND a merchant key exists.
	 */
	public static function payments_active(): bool {
		return self::enabled('payments_enabled') && self::get('zibal_merchant') !== '';
	}
}
