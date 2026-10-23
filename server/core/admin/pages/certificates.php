<?php
/**
 * Certificate requests: review, approve, reject.
 */

declare(strict_types=1);

$status = isset($_GET['status']) && is_string($_GET['status']) && isset(Rishe_Certificates::STATUS_LABELS[$_GET['status']]) ? $_GET['status'] : 'pending';

if (Rishe_Admin::is_post()) {
	$cert_id = rishe_int($_POST, 'cert_id');
	switch (Rishe_Admin::action()) {
		case 'approve':
			Rishe_Certificates::approve($cert_id);
			Rishe_Admin::flash('مدرک تأیید و صادر شد.');
			break;
		case 'reject':
			$reason = rishe_str($_POST, 'reason', 255);
			Rishe_Certificates::reject($cert_id, $reason !== '' ? $reason : 'اطلاعات درخواست کامل نبود.');
			Rishe_Admin::flash('درخواست رد شد. کاربر می‌تواند دوباره درخواست بدهد.', 'warn');
			break;
	}
	Rishe_Admin::redirect('certificates', ['status' => $status]);
}

$counts = [];
foreach (Rishe_DB::rows('SELECT status, COUNT(*) AS n FROM rishe_certificates GROUP BY status') as $row) {
	$counts[$row['status']] = (int) $row['n'];
}
$certs = Rishe_DB::rows(
	'SELECT c.*, u.mobile FROM rishe_certificates c INNER JOIN rishe_users u ON u.id = c.user_id WHERE c.status = ? ORDER BY c.requested_at DESC LIMIT 200',
	[$status]
);
?>
<nav class="rishe-tabs">
	<?php foreach (Rishe_Certificates::STATUS_LABELS as $key => $label) : ?>
		<a class="<?php echo $status === $key ? 'is-active' : ''; ?>" href="<?php echo rishe_e(Rishe_Admin::url('certificates', ['status' => $key])); ?>"><?php echo rishe_e($label); ?> (<?php echo rishe_e(rishe_fa_digits($counts[$key] ?? 0)); ?>)</a>
	<?php endforeach; ?>
</nav>

<section class="rishe-card">
	<div class="rishe-table-wrap"><table class="rishe-table">
		<thead><tr><th>نام روی مدرک</th><th>دوره</th><th>نمره</th><th>کد مدرک</th><th>تاریخ درخواست</th><th></th></tr></thead>
		<tbody>
		<?php foreach ($certs as $c) : ?>
			<tr>
				<td><b><?php echo rishe_e($c['name_fa']); ?></b><span class="rishe-sub ltr"><?php echo rishe_e($c['name_en'] . ' · ' . $c['mobile']); ?></span></td>
				<td><?php echo rishe_e($c['course_title']); ?><span class="rishe-sub"><?php echo rishe_e(rishe_fa_digits($c['hours'])); ?> ساعت</span></td>
				<td class="is-num"><?php echo $c['score'] !== null ? rishe_e(rishe_fa_digits($c['score'])) : 'بدون آزمون'; ?></td>
				<td class="is-num ltr"><?php echo rishe_e($c['code']); ?></td>
				<td class="is-num"><?php echo rishe_e(rishe_jalali($c['requested_at'], true)); ?></td>
				<td>
					<div class="rishe-actions">
						<?php if (in_array($c['status'], ['pending', 'rejected'], true)) : ?>
							<form method="post" class="rishe-inline"><?php echo Rishe_Csrf::field(); ?><input type="hidden" name="_action" value="approve"><input type="hidden" name="cert_id" value="<?php echo (int) $c['id']; ?>"><button class="rishe-btn is-primary is-sm" type="submit">تأیید و صدور</button></form>
						<?php endif; ?>
						<?php if (in_array($c['status'], ['pending', 'approved'], true)) : ?>
							<button class="rishe-link is-danger" type="button" data-reject="<?php echo (int) $c['id']; ?>"><?php echo $c['status'] === 'approved' ? 'ابطال' : 'رد'; ?></button>
						<?php endif; ?>
						<?php if ($c['status'] === 'approved') : ?>
							<a href="<?php echo rishe_e(Rishe_Certificates::verify_url($c['code'])); ?>" target="_blank" rel="noopener">مشاهده</a>
						<?php endif; ?>
					</div>
					<?php if ($c['status'] === 'rejected' && $c['reject_reason']) : ?><span class="rishe-sub">دلیل: <?php echo rishe_e($c['reject_reason']); ?></span><?php endif; ?>
					<form method="post" class="rishe-reject" id="rishe-reject-<?php echo (int) $c['id']; ?>">
						<?php echo Rishe_Csrf::field(); ?><input type="hidden" name="_action" value="reject"><input type="hidden" name="cert_id" value="<?php echo (int) $c['id']; ?>">
						<input class="rishe-input" name="reason" placeholder="دلیل (به کاربر نمایش داده می‌شود)" maxlength="255">
						<button class="rishe-btn is-sm is-danger" type="submit">ثبت</button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php if (!$certs) : ?><tr><td colspan="6" class="rishe-empty">موردی در این بخش نیست.</td></tr><?php endif; ?>
		</tbody>
	</table></div>
</section>
<p class="rishe-help">نام فارسی و انگلیسی، ساعت دوره و نمره در لحظه‌ی درخواست ثبت می‌شوند و بعداً تغییر نمی‌کنند. قبل از تأیید، املای نام انگلیسی را بررسی کنید.</p>
