<?php
/**
 * API: certificates, subscription plans and payments.
 */

declare(strict_types=1);

final class Rishe_Api_Account {

	public static function certificates(): void {
		$user    = Rishe_Api::require_user();
		$courses = [];
		foreach (Rishe_Learning::published_courses() as $course) {
			$lessons = Rishe_Learning::lessons_with_state((int) $course['id'], (int) $user['id']);
			$percent = Rishe_Learning::progress_percent($lessons);
			$cert    = Rishe_Certificates::for_user_course((int) $user['id'], (int) $course['id']);
			if ($percent === 0 && !$cert) {
				continue;
			}
			$check     = Rishe_Certificates::eligibility((int) $user['id'], $course);
			$courses[] = [
				'id'          => (int) $course['id'],
				'title'       => $course['title'],
				'code'        => $course['code'],
				'color'       => $course['color'],
				'hours'       => (int) $course['hours'],
				'progress'    => $percent,
				'pass_score'  => (int) $course['pass_score'],
				'exam'        => ['required' => $check['exam_required'], 'best_score' => $check['score']],
				'eligible'    => $check['eligible'],
				'reason'      => $check['reason'],
				'certificate' => $cert ? array_merge(Rishe_Certificates::public_data($cert), ['id' => (int) $cert['id']]) : null,
			];
		}
		Rishe_Api::json([
			'ok'      => true,
			'fee'     => Rishe_Certificates::fee(),
			'courses' => $courses,
		]);
	}

	public static function request_certificate(): void {
		$user   = Rishe_Api::require_user();
		$course = Rishe_Learning::course(rishe_int(Rishe_Api::input(), 'course_id'));
		if (!$course) {
			Rishe_Api::error('دوره پیدا نشد.', 404, 'not_found');
		}
		$cert = Rishe_Certificates::request($user, $course);
		Rishe_Api::json([
			'ok'            => true,
			'needs_payment' => $cert['status'] === 'pending_payment',
			'fee'           => Rishe_Certificates::fee(),
			'certificate'   => array_merge(Rishe_Certificates::public_data($cert), ['id' => (int) $cert['id']]),
		]);
	}

	public static function certificate(string $code): void {
		$user = Rishe_Api::require_user();
		$cert = Rishe_Certificates::by_code($code);
		if (!$cert || (int) $cert['user_id'] !== (int) $user['id']) {
			Rishe_Api::error('مدرک پیدا نشد.', 404, 'not_found');
		}
		Rishe_Api::json(['ok' => true, 'certificate' => array_merge(Rishe_Certificates::public_data($cert), ['id' => (int) $cert['id']])]);
	}

	public static function plans(): void {
		Rishe_Api::require_user();
		$plans = [];
		if (Rishe_Settings::payments_active()) {
			foreach (Rishe_DB::rows("SELECT * FROM rishe_plans WHERE status = 'active' ORDER BY sort, price") as $p) {
				$plans[] = ['id' => (int) $p['id'], 'title' => $p['title'], 'days' => (int) $p['days'], 'price' => (int) $p['price']];
			}
		}
		Rishe_Api::json(['ok' => true, 'enabled' => Rishe_Settings::payments_active(), 'plans' => $plans]);
	}

	public static function start_payment(): void {
		$user = Rishe_Api::require_user();
		$in   = Rishe_Api::input();
		$type = rishe_str($in, 'type', 20);
		if (!isset(Rishe_Payments::TYPE_LABELS[$type])) {
			Rishe_Api::error('نوع پرداخت نامعتبر است.', 422, 'type');
		}
		if (!Rishe_Throttle::hit('pay|' . $user['id'], 10, 600)) {
			Rishe_Api::error('تعداد درخواست پرداخت زیاد است. کمی بعد تلاش کنید.', 429, 'throttled');
		}
		$url = Rishe_Payments::start($user, $type, rishe_int($in, 'item_id'));
		Rishe_Api::json(['ok' => true, 'url' => $url]);
	}
}
