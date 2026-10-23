<?php
/**
 * Learners: search, progress overview, block / unblock.
 */

declare(strict_types=1);

if (Rishe_Admin::is_post()) {
	$user_id = rishe_int($_POST, 'user_id');
	$action  = Rishe_Admin::action();
	if (in_array($action, ['block', 'unblock'], true) && $user_id) {
		Rishe_DB::update('rishe_users', ['status' => $action === 'block' ? 'blocked' : 'active'], ['id' => $user_id]);
		if ($action === 'block') {
			Rishe_DB::delete('rishe_tokens', ['user_id' => $user_id]);
		}
		Rishe_Admin::flash($action === 'block' ? 'کاربر مسدود شد و از اپ خارج شد.' : 'کاربر فعال شد.');
	}
	if ($action === 'grant_sub' && $user_id) {
		$days = max(1, min(3650, rishe_int($_POST, 'days', 30)));
		$current = Rishe_Learning::active_subscription($user_id);
		$start   = $current ? $current['ends_at'] : rishe_now();
		Rishe_DB::insert('rishe_subscriptions', [
			'user_id'   => $user_id,
			'plan_id'   => 0,
			'starts_at' => $start,
			'ends_at'   => date('Y-m-d H:i:s', strtotime($start . ' +' . $days . ' days')),
		]);
		Rishe_Admin::flash('اشتراک ' . rishe_fa_digits($days) . ' روزه به کاربر داده شد.');
	}
	Rishe_Admin::redirect('users', array_filter(['q' => $_GET['q'] ?? '', 'pg' => $_GET['pg'] ?? '']));
}

$q        = rishe_str($_GET, 'q', 100);
$page_num = max(1, rishe_int($_GET, 'pg', 1));
$per_page = 30;
$where    = '';
$params   = [];
if ($q !== '') {
	$where  = 'WHERE u.name LIKE ? OR u.name_en LIKE ? OR u.mobile LIKE ?';
	$like   = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], rishe_latin_digits($q)) . '%';
	$params = [$like, $like, $like];
}
$total = (int) Rishe_DB::value('SELECT COUNT(*) FROM rishe_users u ' . $where, $params);
$users = Rishe_DB::rows(
	'SELECT u.*,
		(SELECT COUNT(*) FROM rishe_progress p WHERE p.user_id = u.id) AS lessons_done,
		(SELECT COALESCE(SUM(xp), 0) FROM rishe_progress p WHERE p.user_id = u.id) AS xp,
		(SELECT MAX(day) FROM rishe_activity a WHERE a.user_id = u.id) AS last_day,
		(SELECT MAX(ends_at) FROM rishe_subscriptions s WHERE s.user_id = u.id) AS sub_end
	 FROM rishe_users u ' . $where . ' ORDER BY u.id DESC LIMIT ' . (int) $per_page . ' OFFSET ' . (int) (($page_num - 1) * $per_page),
	$params
);
$pages = max(1, (int) ceil($total / $per_page));
$payments_on = Rishe_Settings::payments_active();
?>
<section class="rishe-card">
	<div class="rishe-card-head">
		<h2><?php echo rishe_e(rishe_fa_digits(number_format($total))); ?> کاربر</h2>
		<form method="get" class="rishe-search">
			<input type="hidden" name="p" value="users">
			<input class="rishe-input" name="q" id="q" value="<?php echo rishe_e($q); ?>" placeholder="نام یا شماره موبایل">
			<button class="rishe-btn" type="submit">جستجو</button>
		</form>
	</div>
	<div class="rishe-table-wrap"><table class="rishe-table">
		<thead><tr><th>کاربر</th><th>موبایل</th><th>مرحله‌ی تکمیل‌شده</th><th>امتیاز</th><th>آخرین فعالیت</th><th>اشتراک</th><th>وضعیت</th><th></th></tr></thead>
		<tbody>
		<?php foreach ($users as $u) : $sub_active = $u['sub_end'] && strtotime($u['sub_end']) > time(); ?>
			<tr>
				<td><b><?php echo rishe_e($u['name']); ?></b><span class="rishe-sub ltr"><?php echo rishe_e($u['name_en']); ?></span></td>
				<td class="is-num ltr"><?php echo rishe_e($u['mobile']); ?></td>
				<td class="is-num"><?php echo rishe_e(rishe_fa_digits($u['lessons_done'])); ?></td>
				<td class="is-num"><?php echo rishe_e(rishe_fa_digits(number_format((int) $u['xp']))); ?></td>
				<td class="is-num"><?php echo rishe_e($u['last_day'] ? rishe_jalali($u['last_day']) : '—'); ?></td>
				<td><?php echo $sub_active ? '<span class="rishe-pill is-ok">تا ' . rishe_e(rishe_jalali($u['sub_end'])) . '</span>' : '—'; ?></td>
				<td><?php echo Rishe_Admin::status_pill($u['status']); ?></td>
				<td><div class="rishe-actions">
					<form method="post" class="rishe-inline"><?php echo Rishe_Csrf::field(); ?><input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
						<?php if ($u['status'] === 'active') : ?>
							<input type="hidden" name="_action" value="block"><button class="rishe-link is-danger" type="submit">مسدود</button>
						<?php else : ?>
							<input type="hidden" name="_action" value="unblock"><button class="rishe-link" type="submit">فعال‌سازی</button>
						<?php endif; ?>
					</form>
					<?php if ($payments_on) : ?>
						<form method="post" class="rishe-inline" data-confirm="اشتراک ۳۰ روزه‌ی رایگان به این کاربر داده شود؟"><?php echo Rishe_Csrf::field(); ?><input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>"><input type="hidden" name="_action" value="grant_sub"><input type="hidden" name="days" value="30"><button class="rishe-link" type="submit">+۳۰ روز اشتراک</button></form>
					<?php endif; ?>
				</div></td>
			</tr>
		<?php endforeach; ?>
		<?php if (!$users) : ?><tr><td colspan="8" class="rishe-empty">کاربری پیدا نشد.</td></tr><?php endif; ?>
		</tbody>
	</table></div>
	<?php if ($pages > 1) : ?>
		<div class="rishe-actions" style="margin-top:14px">
			<?php for ($i = 1; $i <= $pages; $i++) : ?>
				<a class="rishe-btn is-sm <?php echo $i === $page_num ? 'is-primary' : ''; ?>" href="<?php echo rishe_e(Rishe_Admin::url('users', array_filter(['q' => $q, 'pg' => $i]))); ?>"><?php echo rishe_e(rishe_fa_digits($i)); ?></a>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
</section>
