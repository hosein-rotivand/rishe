<?php
/**
 * Dashboard: key numbers and latest activity.
 */

declare(strict_types=1);

$users_total   = (int) Rishe_DB::value('SELECT COUNT(*) FROM rishe_users');
$users_week    = (int) Rishe_DB::value('SELECT COUNT(*) FROM rishe_users WHERE created_at >= ?', [date('Y-m-d H:i:s', strtotime('-7 days'))]);
$active_today  = (int) Rishe_DB::value('SELECT COUNT(*) FROM rishe_activity WHERE day = ?', [date('Y-m-d')]);
$lessons_done  = (int) Rishe_DB::value('SELECT COUNT(*) FROM rishe_progress');
$pending_certs = (int) Rishe_DB::value("SELECT COUNT(*) FROM rishe_certificates WHERE status = 'pending'");
$issued_certs  = (int) Rishe_DB::value("SELECT COUNT(*) FROM rishe_certificates WHERE status = 'approved'");
$revenue_month = (int) Rishe_DB::value("SELECT COALESCE(SUM(amount), 0) FROM rishe_payments WHERE status = 'paid' AND paid_at >= ?", [date('Y-m-d H:i:s', strtotime('-30 days'))]);
$active_subs   = (int) Rishe_DB::value('SELECT COUNT(DISTINCT user_id) FROM rishe_subscriptions WHERE ends_at > ?', [rishe_now()]);
$courses_count = (int) Rishe_DB::value("SELECT COUNT(*) FROM rishe_courses WHERE status = 'published'");

$recent_certs = Rishe_DB::rows(
	"SELECT c.*, u.mobile FROM rishe_certificates c INNER JOIN rishe_users u ON u.id = c.user_id WHERE c.status = 'pending' ORDER BY c.requested_at ASC LIMIT 6"
);
$recent_users = Rishe_DB::rows('SELECT * FROM rishe_users ORDER BY id DESC LIMIT 6');
?>
<?php if ($courses_count === 0) : ?>
	<div class="rishe-note">هنوز دوره‌ی منتشرشده‌ای ندارید. از بخش <a href="<?php echo rishe_e(Rishe_Admin::url('course')); ?>">ساخت دوره</a> شروع کنید: دوره ← فصل ← مرحله ← عبارت‌ها و سؤال‌ها.</div>
<?php endif; ?>
<?php if (Rishe_Settings::enabled('payments_enabled') && Rishe_Settings::get('zibal_merchant') === '') : ?>
	<div class="rishe-note">پرداخت روشن است اما کلید مرچنت زیبال وارد نشده؛ تا وارد نشود همه‌ی محتوا رایگان می‌ماند. <a href="<?php echo rishe_e(Rishe_Admin::url('settings')); ?>">تنظیمات</a></div>
<?php endif; ?>

<div class="rishe-grid is-4">
	<div class="rishe-card rishe-stat"><span>کاربران</span><b><?php echo rishe_e(rishe_fa_digits(number_format($users_total))); ?></b><small><?php echo rishe_e(rishe_fa_digits($users_week)); ?> نفر در ۷ روز اخیر</small></div>
	<div class="rishe-card rishe-stat"><span>فعال امروز</span><b><?php echo rishe_e(rishe_fa_digits($active_today)); ?></b><small><?php echo rishe_e(rishe_fa_digits(number_format($lessons_done))); ?> مرحله تا امروز تکمیل شده</small></div>
	<div class="rishe-card rishe-stat"><span>مدرک در انتظار تأیید</span><b><?php echo rishe_e(rishe_fa_digits($pending_certs)); ?></b><small><?php echo rishe_e(rishe_fa_digits($issued_certs)); ?> مدرک صادر شده</small></div>
	<div class="rishe-card rishe-stat"><span>درآمد ۳۰ روز اخیر</span><b><?php echo rishe_e(rishe_fa_digits(number_format($revenue_month))); ?></b><small>تومان · <?php echo rishe_e(rishe_fa_digits($active_subs)); ?> اشتراک فعال</small></div>
</div>

<div class="rishe-grid is-2">
	<section class="rishe-card">
		<div class="rishe-card-head"><h2>درخواست‌های مدرک</h2><a href="<?php echo rishe_e(Rishe_Admin::url('certificates')); ?>">همه</a></div>
		<div class="rishe-table-wrap"><table class="rishe-table">
			<tbody>
			<?php foreach ($recent_certs as $c) : ?>
				<tr>
					<td><b><?php echo rishe_e($c['name_fa']); ?></b><span class="rishe-sub"><?php echo rishe_e($c['course_title']); ?></span></td>
					<td class="is-num"><?php echo rishe_e(rishe_jalali($c['requested_at'])); ?></td>
					<td><a href="<?php echo rishe_e(Rishe_Admin::url('certificates', ['status' => 'pending'])); ?>">بررسی</a></td>
				</tr>
			<?php endforeach; ?>
			<?php if (!$recent_certs) : ?><tr><td class="rishe-empty">درخواستی در انتظار نیست.</td></tr><?php endif; ?>
			</tbody>
		</table></div>
	</section>
	<section class="rishe-card">
		<div class="rishe-card-head"><h2>کاربران جدید</h2><a href="<?php echo rishe_e(Rishe_Admin::url('users')); ?>">همه</a></div>
		<div class="rishe-table-wrap"><table class="rishe-table">
			<tbody>
			<?php foreach ($recent_users as $u) : ?>
				<tr>
					<td><b><?php echo rishe_e($u['name']); ?></b><span class="rishe-sub ltr"><?php echo rishe_e($u['mobile']); ?></span></td>
					<td class="is-num"><?php echo rishe_e(rishe_jalali($u['created_at'])); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if (!$recent_users) : ?><tr><td class="rishe-empty">هنوز کسی ثبت‌نام نکرده است.</td></tr><?php endif; ?>
			</tbody>
		</table></div>
	</section>
</div>
