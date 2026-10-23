<?php
/**
 * Payment lifecycle: create → redirect to Zibal → verify → fulfil.
 */

declare(strict_types=1);

final class Rishe_Payments {

	public const TYPE_LABELS = [
		'subscription' => 'اشتراک',
		'course'       => 'خرید دوره',
		'certificate'  => 'هزینه‌ی صدور مدرک',
	];

	/**
	 * Resolve what is being bought and its price (Toman).
	 *
	 * @throws RuntimeException
	 * @return array{amount: int, description: string}
	 */
	public static function price(array $user, string $type, int $item_id): array {
		switch ($type) {
			case 'subscription':
				$plan = Rishe_DB::row("SELECT * FROM rishe_plans WHERE id = ? AND status = 'active'", [$item_id]);
				if (!$plan) {
					throw new RuntimeException('طرح اشتراک پیدا نشد.');
				}
				return ['amount' => (int) $plan['price'], 'description' => 'اشتراک ' . $plan['title']];

			case 'course':
				$course = Rishe_Learning::course($item_id);
				if (!$course || (int) $course['is_premium'] !== 1 || (int) $course['price'] <= 0) {
					throw new RuntimeException('این دوره قابل خرید نیست.');
				}
				if (Rishe_Learning::owns_course((int) $user['id'], $item_id)) {
					throw new RuntimeException('این دوره را قبلاً خریده‌اید.');
				}
				return ['amount' => (int) $course['price'], 'description' => 'خرید دوره ' . $course['title']];

			case 'certificate':
				$cert = Rishe_DB::row("SELECT * FROM rishe_certificates WHERE id = ? AND user_id = ? AND status = 'pending_payment'", [$item_id, (int) $user['id']]);
				if (!$cert) {
					throw new RuntimeException('درخواست مدرکی برای پرداخت پیدا نشد.');
				}
				$fee = Rishe_Certificates::fee();
				if ($fee <= 0) {
					Rishe_Certificates::mark_paid((int) $cert['id']);
					throw new RuntimeException('صدور مدرک رایگان است و درخواست شما ثبت شد.');
				}
				return ['amount' => $fee, 'description' => 'هزینه‌ی صدور مدرک ' . $cert['course_title']];
		}
		throw new RuntimeException('نوع پرداخت نامعتبر است.');
	}

	/**
	 * @throws RuntimeException
	 * @return string gateway URL the user should open
	 */
	public static function start(array $user, string $type, int $item_id): string {
		if (!Rishe_Settings::payments_active()) {
			throw new RuntimeException('پرداخت در حال حاضر فعال نیست.');
		}
		$price = self::price($user, $type, $item_id);
		if ($price['amount'] < 1000) {
			throw new RuntimeException('مبلغ پرداخت باید حداقل ۱٬۰۰۰ تومان باشد.');
		}

		$payment_id = Rishe_DB::insert('rishe_payments', [
			'user_id'    => (int) $user['id'],
			'type'       => $type,
			'item_id'    => $item_id,
			'amount'     => $price['amount'],
			'gateway'    => 'zibal',
			'status'     => 'pending',
			'created_at' => rishe_now(),
		]);

		$zibal    = new Rishe_Zibal(Rishe_Settings::get('zibal_merchant'));
		$track_id = $zibal->request(
			$price['amount'] * 10,
			rishe_url('pay/callback.php'),
			(string) $payment_id,
			$price['description'],
			(string) $user['mobile']
		);
		Rishe_DB::update('rishe_payments', ['track_id' => $track_id], ['id' => $payment_id]);
		return $zibal->start_url($track_id);
	}

	/**
	 * Handle the gateway callback. Returns the payment row after processing.
	 *
	 * @throws RuntimeException
	 */
	public static function callback(string $track_id, int $order_id, bool $success): array {
		$payment = Rishe_DB::row('SELECT * FROM rishe_payments WHERE id = ? AND track_id = ?', [$order_id, $track_id]);
		if (!$payment) {
			throw new RuntimeException('تراکنش پیدا نشد.');
		}
		if ($payment['status'] !== 'pending') {
			return $payment;
		}
		if (!$success) {
			Rishe_DB::update('rishe_payments', ['status' => 'failed'], ['id' => $payment['id']]);
			return Rishe_DB::row('SELECT * FROM rishe_payments WHERE id = ?', [$payment['id']]);
		}

		$res    = (new Rishe_Zibal(Rishe_Settings::get('zibal_merchant')))->verify($track_id);
		$result = (int) ($res['result'] ?? 0);
		$amount = (int) ($res['amount'] ?? 0);

		if (in_array($result, [100, 201], true) && $amount === (int) $payment['amount'] * 10) {
			$pdo = Rishe_DB::pdo();
			$pdo->beginTransaction();
			try {
				$updated = Rishe_DB::query(
					"UPDATE rishe_payments SET status = 'paid', paid_at = ?, ref_number = ?, card_number = ? WHERE id = ? AND status = 'pending'",
					[rishe_now(), (string) ($res['refNumber'] ?? ''), (string) ($res['cardNumber'] ?? ''), $payment['id']]
				)->rowCount();
				if ($updated === 1) {
					self::fulfil($payment);
				}
				$pdo->commit();
			} catch (Throwable $e) {
				$pdo->rollBack();
				throw $e;
			}
		} else {
			Rishe_DB::update('rishe_payments', ['status' => 'failed'], ['id' => $payment['id']]);
		}
		return Rishe_DB::row('SELECT * FROM rishe_payments WHERE id = ?', [$payment['id']]);
	}

	private static function fulfil(array $payment): void {
		$user_id = (int) $payment['user_id'];
		switch ($payment['type']) {
			case 'subscription':
				$plan    = Rishe_DB::row('SELECT * FROM rishe_plans WHERE id = ?', [(int) $payment['item_id']]);
				$days    = $plan ? (int) $plan['days'] : 30;
				$current = Rishe_Learning::active_subscription($user_id);
				$start   = $current ? $current['ends_at'] : rishe_now();
				Rishe_DB::insert('rishe_subscriptions', [
					'user_id'    => $user_id,
					'plan_id'    => (int) $payment['item_id'],
					'payment_id' => (int) $payment['id'],
					'starts_at'  => $start,
					'ends_at'    => date('Y-m-d H:i:s', strtotime($start . ' +' . $days . ' days')),
				]);
				break;

			case 'course':
				Rishe_DB::query(
					'INSERT IGNORE INTO rishe_purchases (user_id, course_id, payment_id, created_at) VALUES (?, ?, ?, ?)',
					[$user_id, (int) $payment['item_id'], (int) $payment['id'], rishe_now()]
				);
				break;

			case 'certificate':
				Rishe_Certificates::mark_paid((int) $payment['item_id']);
				break;
		}
	}
}
