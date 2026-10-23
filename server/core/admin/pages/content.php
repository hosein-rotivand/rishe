<?php
/**
 * Ready-made content library: import the bundled beginner-to-advanced courses.
 */

declare(strict_types=1);

$pack = Rishe_Content::load();

if (Rishe_Admin::is_post() && Rishe_Admin::action() === 'import' && $pack) {
	$codes = array_values(array_filter((array) ($_POST['codes'] ?? []), 'is_string'));
	try {
		$done = Rishe_Content::import($codes, rishe_bool($_POST, 'publish') === 1);
		Rishe_Admin::flash($done ? 'اضافه شد: ' . implode('، ', $done) : 'دوره‌ای برای افزودن انتخاب نشده یا قبلاً اضافه شده است.', $done ? 'ok' : 'warn');
	} catch (RuntimeException $e) {
		Rishe_Admin::flash($e->getMessage(), 'bad');
	}
	Rishe_Admin::redirect('content');
}
?>
<?php if (!$pack) : ?>
	<div class="rishe-note">بسته‌ی محتوا (<span class="ltr">content/curriculum.json</span>) روی سرور نیست. پوشه‌ی <span class="ltr">content</span> را از بسته‌ی نصب آپلود کنید.</div>
<?php else : ?>
<section class="rishe-card">
	<div class="rishe-card-head"><h2>دوره‌های آماده: از صفر تا مکالمه‌ی حرفه‌ای</h2><span class="rishe-pill is-mute ltr">v<?php echo rishe_e($pack['version']); ?></span></div>
	<p class="rishe-help" style="margin-bottom:14px">هر عبارت صدای انگلیسی، تلفظ (IPA و فارسی)، معنی فارسی، معنی واژه‌ها و توضیح صوتی فارسی دارد. هر مرحله ۳ تمرین و هر دوره آزمون پایانی دارد. بعد از افزودن، همه‌چیز در بخش «دوره‌ها» قابل ویرایش است و می‌توانید صداها را با صدای خودتان جایگزین کنید.</p>
	<form method="post" class="rishe-form">
		<?php echo Rishe_Csrf::field(); ?>
		<input type="hidden" name="_action" value="import">
		<div class="rishe-table-wrap"><table class="rishe-table">
			<thead><tr><th></th><th>دوره</th><th>سطح</th><th>محتوا</th><th>وضعیت</th></tr></thead>
			<tbody>
			<?php foreach ($pack['courses'] as $course) : $n = Rishe_Content::counts($course); $existing = Rishe_Content::existing_course_id($course); ?>
				<tr>
					<td><?php if ($existing === null) : ?><input type="checkbox" name="codes[]" value="<?php echo rishe_e($course['code']); ?>" checked aria-label="انتخاب"><?php endif; ?></td>
					<td><b><?php echo rishe_e($course['title']); ?></b><span class="rishe-sub"><?php echo rishe_e(mb_substr($course['description'], 0, 90)); ?>…</span></td>
					<td class="ltr"><?php echo rishe_e($course['level']); ?></td>
					<td class="is-num"><?php echo rishe_e(rishe_fa_digits($n['chapters'])); ?> فصل · <?php echo rishe_e(rishe_fa_digits($n['lessons'])); ?> مرحله · <?php echo rishe_e(rishe_fa_digits($n['phrases'])); ?> عبارت</td>
					<td><?php echo $existing !== null ? '<a class="rishe-pill is-ok" href="' . rishe_e(Rishe_Admin::url('course', ['id' => $existing])) . '">اضافه شده</a>' : '<span class="rishe-pill is-mute">اضافه نشده</span>'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table></div>
		<label class="rishe-check"><input type="checkbox" name="publish" value="1" checked>بلافاصله در اپ منتشر شود</label>
		<div class="rishe-form-foot"><button class="rishe-btn is-primary" type="submit">افزودن دوره‌های انتخاب‌شده</button><span class="rishe-help">ممکن است تا یک دقیقه طول بکشد.</span></div>
	</form>
</section>
<?php endif; ?>
