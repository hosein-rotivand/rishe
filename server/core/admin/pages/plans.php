<?php
/**
 * Subscription plans.
 */

declare(strict_types=1);

if (Rishe_Admin::is_post()) {
	$action = Rishe_Admin::action();
	if ($action === 'save_plan') {
		$data = [
			'title'  => rishe_str($_POST, 'title', 120),
			'days'   => max(1, min(3650, rishe_int($_POST, 'days', 30))),
			'price'  => max(0, rishe_int($_POST, 'price')),
			'status' => ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active',
			'sort'   => rishe_int($_POST, 'sort'),
		];
		$plan_id = rishe_int($_POST, 'plan_id');
		if ($data['title'] === '' || $data['price'] < 1000) {
			Rishe_Admin::flash('عنوان و قیمت (حداقل ۱٬۰۰۰ تومان) را وارد کنید.', 'bad');
		} elseif ($plan_id) {
			Rishe_DB::update('rishe_plans', $data, ['id' => $plan_id]);
			Rishe_Admin::flash('طرح ذخیره شد.');
		} else {
			Rishe_DB::insert('rishe_plans', $data);
			Rishe_Admin::flash('طرح اضافه شد.');
		}
	}
	if ($action === 'delete_plan') {
		Rishe_DB::delete('rishe_plans', ['id' => rishe_int($_POST, 'plan_id')]);
		Rishe_Admin::flash('طرح حذف شد. اشتراک‌های فعال قبلی سر جایشان می‌مانند.');
	}
	Rishe_Admin::redirect('plans');
}

$plans   = Rishe_DB::rows('SELECT p.*, (SELECT COUNT(*) FROM rishe_subscriptions s WHERE s.plan_id = p.id) AS sold FROM rishe_plans p ORDER BY p.sort, p.price');
$edit_id = Rishe_Admin::id('edit');
$edit    = $edit_id ? Rishe_DB::row('SELECT * FROM rishe_plans WHERE id = ?', [$edit_id]) : null;
$e       = $edit ?: ['id' => 0, 'title' => '', 'days' => 30, 'price' => 0, 'status' => 'active', 'sort' => 0];
?>
<?php if (!Rishe_Settings::payments_active()) : ?>
	<div class="rishe-note">پرداخت فعلاً خاموش است، پس این طرح‌ها در اپ نمایش داده نمی‌شوند. برای فعال‌سازی، در <a href="<?php echo rishe_e(Rishe_Admin::url('settings')); ?>">تنظیمات</a> پرداخت را روشن و کلید مرچنت زیبال را وارد کنید.</div>
<?php endif; ?>

<div class="rishe-grid is-2" style="align-items:start">
	<section class="rishe-card">
		<div class="rishe-card-head"><h2>طرح‌ها</h2></div>
		<div class="rishe-table-wrap"><table class="rishe-table">
			<thead><tr><th>طرح</th><th>مدت</th><th>قیمت</th><th>فروش</th><th>وضعیت</th><th></th></tr></thead>
			<tbody>
			<?php foreach ($plans as $p) : ?>
				<tr>
					<td><b><?php echo rishe_e($p['title']); ?></b></td>
					<td class="is-num"><?php echo rishe_e(rishe_fa_digits($p['days'])); ?> روز</td>
					<td class="is-num"><?php echo rishe_e(rishe_money((int) $p['price'])); ?></td>
					<td class="is-num"><?php echo rishe_e(rishe_fa_digits($p['sold'])); ?></td>
					<td><?php echo Rishe_Admin::status_pill($p['status']); ?></td>
					<td><div class="rishe-actions">
						<a href="<?php echo rishe_e(Rishe_Admin::url('plans', ['edit' => $p['id']])); ?>">ویرایش</a>
						<?php echo Rishe_Admin::delete_button('delete_plan', ['plan_id' => $p['id']]); ?>
					</div></td>
				</tr>
			<?php endforeach; ?>
			<?php if (!$plans) : ?><tr><td colspan="6" class="rishe-empty">طرحی تعریف نشده است.</td></tr><?php endif; ?>
			</tbody>
		</table></div>
	</section>

	<form method="post" class="rishe-card rishe-form">
		<?php echo Rishe_Csrf::field(); ?>
		<input type="hidden" name="_action" value="save_plan">
		<input type="hidden" name="plan_id" value="<?php echo (int) $e['id']; ?>">
		<h2 style="margin:0"><?php echo $edit ? 'ویرایش طرح' : 'طرح جدید'; ?></h2>
		<label class="rishe-field"><span>عنوان</span><input class="rishe-input" name="title" id="title" required value="<?php echo rishe_e($e['title']); ?>" placeholder="اشتراک سه‌ماهه"></label>
		<div class="rishe-grid is-2">
			<label class="rishe-field"><span>مدت (روز)</span><input class="rishe-input" type="number" min="1" name="days" id="days" value="<?php echo rishe_e($e['days']); ?>"></label>
			<label class="rishe-field"><span>قیمت (تومان)</span><input class="rishe-input" type="number" min="1000" name="price" id="price" value="<?php echo rishe_e($e['price']); ?>"></label>
			<label class="rishe-field"><span>ترتیب</span><input class="rishe-input" type="number" name="sort" id="sort" value="<?php echo rishe_e($e['sort']); ?>"></label>
			<label class="rishe-field"><span>وضعیت</span><select name="status" id="status">
				<option value="active" <?php echo $e['status'] === 'active' ? 'selected' : ''; ?>>فعال</option>
				<option value="inactive" <?php echo $e['status'] === 'inactive' ? 'selected' : ''; ?>>غیرفعال</option>
			</select></label>
		</div>
		<p class="rishe-help">اشتراک فعال، همه‌ی دوره‌های ویژه را باز می‌کند. خرید دوباره، مدت را به انتهای اشتراک فعلی اضافه می‌کند.</p>
		<div class="rishe-form-foot">
			<button class="rishe-btn is-primary" type="submit">ذخیره</button>
			<?php if ($edit) : ?><a href="<?php echo rishe_e(Rishe_Admin::url('plans')); ?>">انصراف</a><?php endif; ?>
		</div>
	</form>
</div>
