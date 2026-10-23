<?php
/**
 * Zibal payment gateway client.
 * Docs: https://help.zibal.ir/IPG/API/
 */

declare(strict_types=1);

final class Rishe_Zibal {

	private const API = 'https://gateway.zibal.ir/v1/';
	private const START = 'https://gateway.zibal.ir/start/';

	private string $merchant;

	public function __construct(string $merchant) {
		$this->merchant = $merchant;
	}

	/**
	 * Create a payment request. Amount is in Rial.
	 *
	 * @throws RuntimeException
	 */
	public function request(int $amount_rial, string $callback, string $order_id, string $description = '', string $mobile = ''): string {
		$payload = [
			'merchant'    => $this->merchant,
			'amount'      => $amount_rial,
			'callbackUrl' => $callback,
			'orderId'     => $order_id,
			'description' => $description,
		];
		if ($mobile !== '') {
			$payload['mobile'] = $mobile;
		}
		$res = $this->post('request', $payload);
		if ((int) ($res['result'] ?? 0) !== 100 || empty($res['trackId'])) {
			throw new RuntimeException('درگاه پرداخت خطا داد: ' . (string) ($res['message'] ?? 'نامشخص'));
		}
		return (string) $res['trackId'];
	}

	public function start_url(string $track_id): string {
		return self::START . rawurlencode($track_id);
	}

	/**
	 * Verify a payment. result 100 = verified now, 201 = already verified.
	 */
	public function verify(string $track_id): array {
		return $this->post('verify', ['merchant' => $this->merchant, 'trackId' => $track_id]);
	}

	private function post(string $endpoint, array $payload): array {
		$ch = curl_init(self::API . $endpoint);
		curl_setopt_array($ch, [
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => json_encode($payload),
			CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 20,
			CURLOPT_CONNECTTIMEOUT => 10,
		]);
		$body  = curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);
		if ($body === false) {
			throw new RuntimeException('اتصال به درگاه برقرار نشد: ' . $error);
		}
		$data = json_decode((string) $body, true);
		if (!is_array($data)) {
			throw new RuntimeException('پاسخ نامعتبر از درگاه.');
		}
		return $data;
	}
}
