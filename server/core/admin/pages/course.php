<?php
/**
 * Create / edit a course and manage its chapters.
 */

declare(strict_types=1);

$id     = Rishe_Admin::id();
$course = $id ? Rishe_Learning::course($id, false) : null;
if ($id && !$course) {
	Rishe_Admin::flash('دوره پیدا نشد.', 'bad');
	Rishe_Admin::redirect('courses');
}

if (Rishe_Admin::is_post()) {
	$action = Rishe_Admin::action();

	if ($action === 'save_course') {
		$data = [
			'title'       => rishe_str($_POST, 'title', 190),
			'title_en'    => rishe_str($_POST, 'title_en', 190),
			'code'        => strtoupper(rishe_str($_POST, 'code', 10)),
			'level'       => rishe_str($_POST, 'level', 20),
			'description' => rishe_str($_POST, 'description', 3000),
			'hours'       => max(0, min(9999, rishe_int($_POST, 'hours'))),
			'color'       => in_array($_POST['color'] ?? '', ['brand', 'amber', 'mute'], true) ? $_POST['color'] : 'brand',
			'is_premium'  => rishe_bool($_POST, 'is_premium'),
			'price'       => max(0, rishe_int($_POST, 'price')),
			'pass_score'  => max(0, min(100, rishe_int($_POST, 'pass_score', 70))),
			'sort'        => rishe_int($_POST, 'sort'),
			'status'      => ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft',
		];
		if ($data['title'] === '') {
			Rishe_Admin::flash('عنوان دوره را وارد کنید.', 'bad');
		} elseif ($course) {
			Rishe_DB::update('rishe_courses', $data, ['id' => $id]);
			Rishe_Admin::flash('دوره ذخیره شد.');
		} else {
			$data['created_at'] = rishe_now();
			$id = Rishe_DB::insert('rishe_courses', $data);
			Rishe_Admin::flash('دوره ساخته شد. حالا فصل‌ها را اضافه کنید.');
		}
		Rishe_Admin::redirect('course', $id ? ['id' => $id] : []);
	}

	if ($course && $action === 'add_chapter') {
		$title = rishe_str($_POST, 'title', 190);
		if ($title !== '') {
			$sort = (int) Rishe_DB::value('SELECT COALESCE(MAX(sort), 0) + 1 FROM rishe_chapters WHERE course_id = ?', [$id]);
			Rishe_DB::insert('rishe_chapters', ['course_id' => $id, 'title' => $title, 'sort' => $sort]);
			Rishe_Admin::flash('فصل اضافه شد.');
		}
		Rishe_Admin::redirect('course', ['id' => $id]);
	}

	if ($course && $action === 'delete_chapter') {
		$chapter_id = rishe_int($_POST, 'chapter_id');
		if (Rishe_DB::value('SELECT COUNT(*) FROM rishe_lessons WHERE chapter_id = ?', [$chapter_id])) {
			Rishe_Admin::flash('اول مرحله‌های این فصل را حذف کنید.', 'bad');
		} else {
			Rishe_DB::delete('rishe_chapters', ['id' => $chapter_id, 'course_id' => $id]);
			Rishe_Admin::flash('فصل حذف شد.');
		}
		Rishe_Admin::redirect('course', ['id' => $id]);
	}

	if ($course && $action === 'delete_course') {
		if (Rishe_DB::value('SELECT COUNT(*) FROM rishe_certificates WHERE course_id = ?', [$id])) {
			Rishe_Admin::flash('برای این دوره مدرک ثبت شده است؛ به جای حذف، آن را پیش‌نویس کنید.', 'bad');
			Rishe_Admin::redirect('course', ['id' => $id]);
		}
		Rishe_Admin::delete_course($id);
		Rishe_Admin::flash('دوره با همه‌ی محتوایش حذف شد.');
		Rishe_Admin::redirect('courses');
	}
}

$c = $course ?: [
	'title' => '', 'title_en' => '', 'code' => '', 'level' => 'A1', 'description' => '', 'hours' => 0, 'color' => 'brand',
	'is_premium' => 0, 'price' => 0, 'pass_score' => 70, 'sort' => 0, 'status' => 'draft',
];
$chapters = $course ? Rishe_DB::rows(
	'SELECT ch.*, (SELECT COUNT(*) FROM rishe_lessons l WHERE l.chapter_id = ch.id) AS lessons FROM rishe_chapters ch WHERE ch.course_id = ? ORDER BY ch.sort, ch.id',
	[$id]
) : [];
$exam_count = $course ? Rishe_Learning::exam_count($id) : 0;

$title  = $course ? $course['title'] : 'دوره‌ی جدید';
$crumbs = ['دوره‌ها' => Rishe_Admin::url('courses')];
?>
<div class="rishe-grid is-2" style="align-items:start">
	<form method="post" class="rishe-card rishe-form">
		<?php echo Rishe_Csrf::field(); ?>
		<input type="hidden" name="_action" value="save_course">
		<h2 style="margin:0">مشخصات دوره</h2>
		<div class="rishe-grid is-2">
			<label class="rishe-field"><span>عنوان فارسی</span><input class="rishe-input" name="title" id="title" required value="<?php echo rishe_e($c['title']); ?>" placeholder="انگلیسی از پایه"></label>
			<label class="rishe-field"><span>عنوان انگلیسی</span><input class="rishe-input ltr" name="title_en" id="title_en" value="<?php echo rishe_e($c['title_en']); ?>" placeholder="English Foundations"></label>
		</div>
		<div class="rishe-grid is-3">
			<label class="rishe-field"><span>کد کوتاه</span><input class="rishe-input ltr" name="code" id="code" maxlength="10" value="<?php echo rishe_e($c['code']); ?>" placeholder="A1"><small>روی کاور دوره و مدرک</small></label>
			<label class="rishe-field"><span>سطح</span><input class="rishe-input ltr" name="level" id="level" value="<?php echo rishe_e($c['level']); ?>" placeholder="A1"></label>
			<label class="rishe-field"><span>ساعت آموزش</span><input class="rishe-input" type="number" min="0" name="hours" id="hours" value="<?php echo rishe_e($c['hours']); ?>"><small>روی مدرک درج می‌شود</small></label>
		</div>
		<label class="rishe-field"><span>توضیح</span><textarea name="description" id="description"><?php echo rishe_e($c['description']); ?></textarea></label>
		<div class="rishe-grid is-3">
			<label class="rishe-field"><span>رنگ کاور</span><select name="color" id="color">
				<?php foreach (['brand' => 'سبز', 'amber' => 'کهربایی', 'mute' => 'خاکستری'] as $k => $v) : ?>
					<option value="<?php echo $k; ?>" <?php echo $c['color'] === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
				<?php endforeach; ?>
			</select></label>
			<label class="rishe-field"><span>حد نصاب آزمون</span><input class="rishe-input" type="number" min="0" max="100" name="pass_score" id="pass_score" value="<?php echo rishe_e($c['pass_score']); ?>"><small>از ۱۰۰، برای دریافت مدرک</small></label>
			<label class="rishe-field"><span>ترتیب نمایش</span><input class="rishe-input" type="number" name="sort" id="sort" value="<?php echo rishe_e($c['sort']); ?>"></label>
		</div>
		<div class="rishe-grid is-2">
			<div class="rishe-field">
				<span>دسترسی</span>
				<label class="rishe-check"><input type="checkbox" name="is_premium" id="is_premium" value="1" <?php echo (int) $c['is_premium'] ? 'checked' : ''; ?>>دوره‌ی ویژه (پولی)</label>
				<small>تا وقتی پرداخت در تنظیمات خاموش است، همه‌ی دوره‌ها رایگان نمایش داده می‌شوند.</small>
			</div>
			<label class="rishe-field"><span>قیمت خرید تکی (تومان)</span><input class="rishe-input" type="number" min="0" name="price" id="price" value="<?php echo rishe_e($c['price']); ?>"><small>۰ یعنی فقط با اشتراک</small></label>
		</div>
		<label class="rishe-field"><span>وضعیت</span><select name="status" id="status">
			<option value="draft" <?php echo $c['status'] === 'draft' ? 'selected' : ''; ?>>پیش‌نویس (در اپ دیده نمی‌شود)</option>
			<option value="published" <?php echo $c['status'] === 'published' ? 'selected' : ''; ?>>منتشر شده</option>
		</select></label>
		<div class="rishe-form-foot">
			<button class="rishe-btn is-primary" type="submit"><?php echo $course ? 'ذخیره‌ی تغییرات' : 'ساخت دوره'; ?></button>
		</div>
	</form>

	<?php if ($course) : ?>
	<div class="rishe-content">
		<section class="rishe-card">
			<div class="rishe-card-head"><h2>فصل‌ها</h2></div>
			<div class="rishe-table-wrap"><table class="rishe-table">
				<tbody>
				<?php foreach ($chapters as $i => $ch) : ?>
					<tr>
						<td class="is-num"><?php echo rishe_e(rishe_fa_digits($i + 1)); ?></td>
						<td><a href="<?php echo rishe_e(Rishe_Admin::url('chapter', ['id' => $ch['id']])); ?>"><b><?php echo rishe_e($ch['title']); ?></b></a><span class="rishe-sub"><?php echo rishe_e(rishe_fa_digits($ch['lessons'])); ?> مرحله</span></td>
						<td><div class="rishe-actions">
							<a href="<?php echo rishe_e(Rishe_Admin::url('chapter', ['id' => $ch['id']])); ?>">مرحله‌ها</a>
							<?php echo Rishe_Admin::delete_button('delete_chapter', ['chapter_id' => $ch['id']]); ?>
						</div></td>
					</tr>
				<?php endforeach; ?>
				<?php if (!$chapters) : ?><tr><td class="rishe-empty">هنوز فصلی ندارد.</td></tr><?php endif; ?>
				</tbody>
			</table></div>
			<form method="post" class="rishe-search" style="margin-top:14px">
				<?php echo Rishe_Csrf::field(); ?>
				<input type="hidden" name="_action" value="add_chapter">
				<input class="rishe-input" name="title" id="chapter_title" placeholder="عنوان فصل جدید، مثلاً «سلام و معرفی»" required>
				<button class="rishe-btn is-primary" type="submit">افزودن فصل</button>
			</form>
		</section>

		<section class="rishe-card">
			<div class="rishe-card-head"><h2>آزمون پایانی</h2><a class="rishe-btn is-sm" href="<?php echo rishe_e(Rishe_Admin::url('exam', ['id' => $id])); ?>">مدیریت سؤال‌ها</a></div>
			<p class="rishe-help"><?php echo $exam_count ? rishe_e(rishe_fa_digits($exam_count)) . ' سؤال. نمره‌ی آن روی مدرک چاپ می‌شود.' : 'این دوره آزمون پایانی ندارد؛ مدرک بعد از تکمیل همه‌ی مراحل قابل درخواست است.'; ?></p>
		</section>

		<section class="rishe-card">
			<div class="rishe-card-head"><h2>حذف دوره</h2><?php echo Rishe_Admin::delete_button('delete_course', [], 'حذف دوره', 'دوره با همه‌ی فصل‌ها، مرحله‌ها، فایل‌های صوتی و پیشرفت کاربران حذف شود؟ این کار برگشت‌پذیر نیست.'); ?></div>
			<p class="rishe-help">همه‌ی فصل‌ها، مرحله‌ها و فایل‌های صوتی دوره هم حذف می‌شوند. دوره‌ای که مدرک صادرشده دارد حذف نمی‌شود.</p>
		</section>
	</div>
	<?php endif; ?>
</div>
