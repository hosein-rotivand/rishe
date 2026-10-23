<?php
/**
 * OTP sending through Melipayamak pattern (shared service line) API (optional).
 * Docs: https://www.melipayamak.com/api/ (REST: SendSMS/BaseServiceNumber)
 */

declare(strict_types=1);

final class Rishe_Sms {

	private const ENDPOINT = 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber';

	public static function enabled(): bool {
		return Rishe_Settings::enabled('sms_enabled')
			&& Rishe_Settings::get('sms_username') !== ''
			&& Rishe_Settings::get('sms_password') !== ''
			&& Rishe_Settings::get('sms_body_id') !== '';
	}

	/**
	 * Send the code with the approved pattern; {0} in the pattern is replaced by the code.
	 */
	public static function send_otp(string $mobile, string $code): bool {
		if (!self::enabled()) {
			return false;
		}
		$ch = curl_init(self::ENDPOINT);
		curl_setopt_array($ch, [
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => http_build_query([
				'username' => Rishe_Settings::get('sms_username'),
				'password' => Rishe_Settings::get('sms_password'),
				'to'       => $mobile,
				'text'     => $code,
				'bodyId'   => Rishe_Settings::get('sms_body_id'),
			]),
			CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 15,
			CURLOPT_CONNECTTIMEOUT => 8,
		]);
		$body   = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($body === false || $status !== 200) {
			error_log('Rishe SMS failed: HTTP ' . $status);
			return false;
		}
		$data = json_decode((string) $body, true);
		// Success: RetStatus 1 and Value is the message id (a long number); short Values are error codes.
		$ok = is_array($data) && (int) ($data['RetStatus'] ?? 0) === 1 && strlen((string) ($data['Value'] ?? '')) > 5;
		if (!$ok) {
			error_log('Rishe SMS rejected: ' . substr((string) $body, 0, 200));
		}
		return $ok;
	}
}
