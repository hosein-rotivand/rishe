<?php
/**
 * OTP sending through Kavenegar "verify lookup" (optional).
 */

declare(strict_types=1);

final class Rishe_Sms {

	public static function enabled(): bool {
		return Rishe_Settings::enabled('sms_enabled')
			&& Rishe_Settings::get('sms_api_key') !== ''
			&& Rishe_Settings::get('sms_template') !== '';
	}

	public static function send_otp(string $mobile, string $code): bool {
		if (!self::enabled()) {
			return false;
		}
		$url = sprintf(
			'https://api.kavenegar.com/v1/%s/verify/lookup.json?%s',
			rawurlencode(Rishe_Settings::get('sms_api_key')),
			http_build_query([
				'receptor' => $mobile,
				'token'    => $code,
				'template' => Rishe_Settings::get('sms_template'),
			])
		);
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 15,
			CURLOPT_CONNECTTIMEOUT => 8,
		]);
		$body = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($body === false || $code !== 200) {
			error_log('Rishe SMS failed: HTTP ' . $code);
			return false;
		}
		$data = json_decode((string) $body, true);
		return (int) ($data['return']['status'] ?? 0) === 200;
	}
}
