<?php
/**
 * Certificate eligibility, requests, approval and rendering.
 */

declare(strict_types=1);

final class Rishe_Certificates {

	public const STATUS_LABELS = [
		'pending_payment' => 'در انتظار پرداخت',
		'pending'         => 'در انتظار تأیید',
		'approved'        => 'تأیید و صادر شده',
		'rejected'        => 'رد شده',
	];

	/**
	 * @return array{eligible: bool, reason: string, score: ?int, completed: bool, exam_required: bool}
	 */
	public static function eligibility(int $user_id, array $course): array {
		$course_id     = (int) $course['id'];
		$completed     = Rishe_Learning::is_course_completed($course_id, $user_id);
		$exam_required = Rishe_Learning::exam_count($course_id) > 0;
		$score         = Rishe_Learning::best_exam_score($user_id, $course_id);

		if (!$completed) {
			return ['eligible' => false, 'reason' => 'ابتدا همه‌ی مراحل دوره را کامل کنید.', 'score' => $score, 'completed' => false, 'exam_required' => $exam_required];
		}
		if ($exam_required && ($score === null || $score < (int) $course['pass_score'])) {
			return ['eligible' => false, 'reason' => 'برای دریافت مدرک باید در آزمون پایانی حداقل نمره‌ی ' . rishe_fa_digits((int) $course['pass_score']) . ' را بگیرید.', 'score' => $score, 'completed' => true, 'exam_required' => true];
		}
		return ['eligible' => true, 'reason' => '', 'score' => $score, 'completed' => true, 'exam_required' => $exam_required];
	}

	public static function for_user_course(int $user_id, int $course_id): ?array {
		return Rishe_DB::row('SELECT * FROM rishe_certificates WHERE user_id = ? AND course_id = ?', [$user_id, $course_id]);
	}

	public static function by_code(string $code): ?array {
		return Rishe_DB::row('SELECT * FROM rishe_certificates WHERE code = ?', [strtoupper(trim($code))]);
	}

	public static function fee(): int {
		return Rishe_Settings::payments_active() ? max(0, Rishe_Settings::int('certificate_fee')) : 0;
	}

	/**
	 * Create (or return the existing) certificate request.
	 *
	 * @throws RuntimeException when the user is not eligible.
	 */
	public static function request(array $user, array $course): array {
		$existing = self::for_user_course((int) $user['id'], (int) $course['id']);
		if ($existing && $existing['status'] !== 'rejected') {
			return $existing;
		}
		$check = self::eligibility((int) $user['id'], $course);
		if (!$check['eligible']) {
			throw new RuntimeException($check['reason']);
		}
		if (trim((string) $user['name_en']) === '') {
			throw new RuntimeException('نام انگلیسی خود را در پروفایل وارد کنید؛ این نام روی مدرک چاپ می‌شود.');
		}

		$data = [
			'status'        => self::fee() > 0 ? 'pending_payment' : 'pending',
			'score'         => $check['score'],
			'hours'         => (int) $course['hours'],
			'name_fa'       => $user['name'],
			'name_en'       => $user['name_en'],
			'course_title'  => $course['title'],
			'course_code'   => $course['code'],
			'reject_reason' => null,
			'requested_at'  => rishe_now(),
			'approved_at'   => null,
		];

		if ($existing) {
			Rishe_DB::update('rishe_certificates', $data, ['id' => $existing['id']]);
			return self::for_user_course((int) $user['id'], (int) $course['id']);
		}

		$data['user_id']   = (int) $user['id'];
		$data['course_id'] = (int) $course['id'];
		$data['code']      = self::new_code();
		Rishe_DB::insert('rishe_certificates', $data);
		return self::for_user_course((int) $user['id'], (int) $course['id']);
	}

	public static function approve(int $id): void {
		Rishe_DB::query(
			"UPDATE rishe_certificates SET status = 'approved', approved_at = ?, reject_reason = NULL WHERE id = ? AND status IN ('pending', 'rejected')",
			[rishe_now(), $id]
		);
	}

	public static function reject(int $id, string $reason): void {
		Rishe_DB::query(
			"UPDATE rishe_certificates SET status = 'rejected', reject_reason = ? WHERE id = ? AND status IN ('pending', 'approved')",
			[$reason, $id]
		);
	}

	public static function mark_paid(int $id): void {
		Rishe_DB::query("UPDATE rishe_certificates SET status = 'pending' WHERE id = ? AND status = 'pending_payment'", [$id]);
	}

	private static function new_code(): string {
		$prefix = preg_replace('/[^A-Z]/', '', strtoupper(Rishe_Settings::get('cert_prefix', 'RS'))) ?: 'RS';
		do {
			$code = sprintf('%s-%d-%05d', $prefix, rishe_jalali_year(), random_int(0, 99999));
		} while (Rishe_DB::value('SELECT 1 FROM rishe_certificates WHERE code = ?', [$code]));
		return $code;
	}

	public static function verify_url(string $code): string {
		return rishe_url('verify/?code=' . rawurlencode($code));
	}

	/**
	 * Data shape shared by the API and the public verification page.
	 */
	public static function public_data(array $cert): array {
		return [
			'code'          => $cert['code'],
			'status'        => $cert['status'],
			'status_label'  => self::STATUS_LABELS[$cert['status']] ?? $cert['status'],
			'name_fa'       => $cert['name_fa'],
			'name_en'       => $cert['name_en'],
			'course_id'     => (int) $cert['course_id'],
			'course_title'  => $cert['course_title'],
			'course_code'   => $cert['course_code'],
			'hours'         => (int) $cert['hours'],
			'score'         => $cert['score'] === null ? null : (int) $cert['score'],
			'requested_at'  => rishe_jalali($cert['requested_at'], true),
			'issued_at'     => $cert['approved_at'] ? rishe_jalali($cert['approved_at']) : null,
			'reject_reason' => $cert['reject_reason'],
			'verify_url'    => self::verify_url($cert['code']),
			'brand'         => [
				'name'         => Rishe_Settings::get('app_name'),
				'name_en'      => Rishe_Settings::get('app_name_en'),
				'tagline'      => Rishe_Settings::get('app_tagline'),
				'signer_name'  => Rishe_Settings::get('cert_signer_name'),
				'signer_title' => Rishe_Settings::get('cert_signer_title'),
			],
		];
	}

	/**
	 * Certificate markup. Styles live in assets/certificate.css (and the app's copy of it).
	 */
	public static function render(array $data): string {
		$b     = $data['brand'];
		$score = $data['score'] !== null
			? ' و در آزمون پایانی نمره‌ی <strong>' . rishe_e(rishe_fa_digits($data['score'])) . ' از ۱۰۰</strong> را کسب کرده'
			: '';
		ob_start();
		?>
		<div class="rishe-cert-wrap"><div class="rishe-cert">
			<div class="rishe-cert-dots"></div><div class="rishe-cert-frame"></div>
			<div class="rishe-cert-in">
				<div class="rishe-cert-top">
					<div class="rishe-cert-brand">
						<span class="rishe-cert-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3v8M12 11c0 4-4 4-4 8M12 11c0 4 4 4 4 8M12 11v10"/></svg></span>
						<div><b><?php echo rishe_e($b['name_en']); ?></b><small><?php echo rishe_e($b['name'] . ' · ' . $b['tagline']); ?></small></div>
					</div>
					<div class="rishe-cert-meta">شماره‌ی مدرک: <b><?php echo rishe_e($data['code']); ?></b><br>تاریخ صدور: <?php echo rishe_e($data['issued_at'] ?? '—'); ?></div>
				</div>
				<div class="rishe-cert-title"><div class="rishe-cert-en">Certificate of Completion</div><div class="rishe-cert-rule"></div><div class="rishe-cert-fa">گواهی پایان دوره</div></div>
				<div class="rishe-cert-name"><b><?php echo rishe_e($data['name_fa']); ?></b><span><?php echo rishe_e($data['name_en']); ?></span></div>
				<div class="rishe-cert-text">دوره‌ی <strong>«<?php echo rishe_e($data['course_title']); ?><?php echo $data['course_code'] !== '' ? ' (' . rishe_e($data['course_code']) . ')' : ''; ?>»</strong> را به مدت <strong><?php echo rishe_e(rishe_fa_digits($data['hours'])); ?> ساعت</strong> با موفقیت گذرانده<?php echo $score; ?> است. این گواهی به پاس پشتکار و تعهد ایشان در طول دوره اعطا می‌شود.</div>
				<div class="rishe-cert-bottom">
					<div class="rishe-cert-sign">
						<svg class="rishe-cert-seal" viewBox="0 0 100 100"><defs><path id="rishe-seal" d="M50 50 m-36 0 a36 36 0 1 1 72 0 a36 36 0 1 1 -72 0"/></defs><circle cx="50" cy="50" r="46" fill="none" stroke="#0D6B4E" stroke-width="2" opacity=".75"/><circle cx="50" cy="50" r="28" fill="none" stroke="#0D6B4E" stroke-width="1.2" opacity=".6"/><text font-size="9.5" font-weight="700" fill="#0D6B4E" opacity=".8" letter-spacing="2.2"><textPath href="#rishe-seal"><?php echo rishe_e(strtoupper($b['name_en'])); ?> · VERIFIED · <?php echo rishe_e(strtoupper($b['name_en'])); ?> · VERIFIED ·</textPath></text><g transform="translate(38 36)" fill="none" stroke="#0D6B4E" stroke-width="2.2" stroke-linecap="round" opacity=".85"><path d="M12 3v8M12 11c0 4-4 4-4 8M12 11c0 4 4 4 4 8M12 11v10"/></g></svg>
						<div class="rishe-cert-sig"><span class="rishe-cert-line"></span><b><?php echo rishe_e($b['signer_name'] !== '' ? $b['signer_name'] : $b['signer_title']); ?></b><small><?php echo rishe_e($b['signer_name'] !== '' ? $b['signer_title'] : 'امضای الکترونیک'); ?></small></div>
					</div>
					<div class="rishe-cert-verify"><small>استعلام اعتبار</small><b><?php echo rishe_e(preg_replace('#^https?://#', '', $data['verify_url'])); ?></b></div>
				</div>
			</div>
		</div></div>
		<?php
		return (string) ob_get_clean();
	}
}
