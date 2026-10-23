<?php
/**
 * Payment transactions.
 */

declare(strict_types=1);

$status = isset($_GET['status']) && in_array($_GET['status'], ['paid', 'pending', 'failed'], true) ? $_GET['status'] : 'paid';

$payments = Rishe_DB::rows(
	'SELECT p.*, u.name, u.mobile FROM rishe_payments p INNER JOIN rishe_users u ON u.id = p.user_id WHERE p.status = ? ORDER BY p.id DESC LIMIT 200',
	[$status]
);
$total_paid = (int) Rishe_DB::value("SELECT COALESCE(SUM(amount), 0) FROM rishe_payments WHERE status = 'paid'");

/**
 * Human label for what was bought.
 */
$item_label = static function (array $p): string {
	switch ($p['type']) {
		case 'subscription':
			$title = Rishe_DB::value('SELECT title FROM rishe_plans WHERE id = ?', [$p['item_id']]);
			return 'اشتراک ' . ($title ?: '#' . $p['item_id']);
		case 'course':
			$title = Rishe_DB::value('SELECT title FROM rishe_courses WHERE id = ?', [$p['item_id']]);
			return 'دوره‌ی ' . ($title ?: '#' . $p['item_id']);
		default:
			$title = Rishe_DB::value('SELECT course_title FROM rishe_certificates WHERE id = ?', [$p['item_id']]);
			return 'مدرک ' . ($title ?: '#' . $p['item_id']);
	}
};
?>
<div class="rishe-grid is-3">
	<div class="rishe-card rishe-stat"><span>جمع پرداخت‌های موفق</span><b><?php echo rishe_e(rishe_fa_digits(number_format($total_paid))); ?></b><small>تومان</small></div>
</div>

<nav class="rishe-tabs">
	<?php foreach (['paid' => 'موفق', 'pending' => 'نیمه‌تمام', 'failed' => 'ناموفق'] as $key => $label) : ?>
		<a class="<?php echo $status === $key ? 'is-active' : ''; ?>" href="<?php echo rishe_e(Rishe_Admin::url('payments', ['status' => $key])); ?>"><?php echo rishe_e($label); ?></a>
	<?php endforeach; ?>
</nav>

<section class="rishe-card">
	<div class="rishe-table-wrap"><table class="rishe-table">
		<thead><tr><th>#</th><th>کاربر</th><th>بابت</th><th>مبلغ</th><th>کد پیگیری</th><th>تاریخ</th><th>وضعیت</th></tr></thead>
		<tbody>
		<?php foreach ($payments as $p) : ?>
			<tr>
				<td class="is-num"><?php echo rishe_e(rishe_fa_digits($p['id'])); ?></td>
				<td><b><?php echo rishe_e($p['name']); ?></b><span class="rishe-sub ltr"><?php echo rishe_e($p['mobile']); ?></span></td>
				<td><?php echo rishe_e($item_label($p)); ?></td>
				<td class="is-num"><?php echo rishe_e(rishe_money((int) $p['amount'])); ?></td>
				<td class="is-num ltr"><?php echo rishe_e($p['ref_number'] ?: ($p['track_id'] ?: '—')); ?></td>
				<td class="is-num"><?php echo rishe_e(rishe_jalali($p['paid_at'] ?: $p['created_at'], true)); ?></td>
				<td><?php echo Rishe_Admin::status_pill($p['status']); ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if (!$payments) : ?><tr><td colspan="7" class="rishe-empty">تراکنشی نیست.</td></tr><?php endif; ?>
		</tbody>
	</table></div>
</section>
