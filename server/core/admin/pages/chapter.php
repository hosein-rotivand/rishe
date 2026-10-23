<?php
/**
 * A chapter: rename it and manage its lessons.
 */

declare(strict_types=1);

$id      = Rishe_Admin::id();
$chapter = Rishe_DB::row('SELECT * FROM rishe_chapters WHERE id = ?', [$id]);
if (!$chapter) {
	Rishe_Admin::flash('فصل پیدا نشد.', 'bad');
	Rishe_Admin::redirect('courses');
}
$course = Rishe_Learning::course((int) $chapter['course_id'], false);

if (Rishe_Admin::is_post()) {
	$action = Rishe_Admin::action();

	if ($action === 'save_chapter') {
		$title = rishe_str($_POST, 'title', 190);
		if ($title !== '') {
			Rishe_DB::update('rishe_chapters', ['title' => $title, 'sort' => rishe_int($_POST, 'sort')], ['id' => $id]);
			Rishe_Admin::flash('فصل ذخیره شد.');
		}
	}

	if ($action === 'add_lesson') {
		$title = rishe_str($_POST, 'title', 190);
		if ($title === '') {
			Rishe_Admin::flash('عنوان مرحله را وارد کنید.', 'bad');
		} else {
			$sort      = (int) Rishe_DB::value('SELECT COALESCE(MAX(sort), 0) + 1 FROM rishe_lessons WHERE chapter_id = ?', [$id]);
			$lesson_id = Rishe_DB::insert('rishe_lessons', [
				'chapter_id' => $id,
				'title'      => $title,
				'minutes'    => max(1, min(120, rishe_int($_POST, 'minutes', 5))),
				'is_free'    => rishe_bool($_POST, 'is_free'),
				'sort'       => $sort,
				'status'     => 'published',
			]);
			Rishe_Admin::flash('مرحله ساخته شد. حالا عبارت‌ها و سؤال‌هایش را اضافه کنید.');
			Rishe_Admin::redirect('lesson', ['id' => $lesson_id]);
		}
	}

	if ($action === 'delete_lesson') {
		$lesson_id = rishe_int($_POST, 'lesson_id');
		if (Rishe_DB::value('SELECT 1 FROM rishe_lessons WHERE id = ? AND chapter_id = ?', [$lesson_id, $id])) {
			Rishe_Admin::delete_lesson($lesson_id);
			Rishe_Admin::flash('مرحله حذف شد.');
		}
	}
	Rishe_Admin::redirect('chapter', ['id' => $id]);
}

$lessons = Rishe_DB::rows(
	"SELECT l.*,
		(SELECT COUNT(*) FROM rishe_phrases p WHERE p.lesson_id = l.id) AS phrases,
		(SELECT COUNT(*) FROM rishe_questions q WHERE q.owner_type = 'lesson' AND q.owner_id = l.id) AS questions
	 FROM rishe_lessons l WHERE l.chapter_id = ? ORDER BY l.sort, l.id",
	[$id]
);

$title  = 'فصل: ' . $chapter['title'];
$crumbs = ['دوره‌ها' => Rishe_Admin::url('courses'), $course['title'] => Rishe_Admin::url('course', ['id' => $course['id']])];
?>
<section class="rishe-card">
	<div class="rishe-card-head"><h2>مرحله‌های این فصل</h2></div>
	<div class="rishe-table-wrap"><table class="rishe-table">
		<thead><tr><th>#</th><th>مرحله</th><th>محتوا</th><th>دسترسی</th><th>وضعیت</th><th></th></tr></thead>
		<tbody>
		<?php foreach ($lessons as $i => $l) : ?>
			<tr>
				<td class="is-num"><?php echo rishe_e(rishe_fa_digits($i + 1)); ?></td>
				<td><a href="<?php echo rishe_e(Rishe_Admin::url('lesson', ['id' => $l['id']])); ?>"><b><?php echo rishe_e($l['title']); ?></b></a><span class="rishe-sub"><?php echo rishe_e(rishe_fa_digits($l['minutes'])); ?> دقیقه</span></td>
				<td class="is-num"><?php echo rishe_e(rishe_fa_digits($l['phrases'])); ?> عبارت · <?php echo rishe_e(rishe_fa_digits($l['questions'])); ?> سؤال</td>
				<td><?php echo (int) $l['is_free'] ? '<span class="rishe-pill is-ok">پیش‌نمایش رایگان</span>' : '<span class="rishe-pill is-mute">طبق دوره</span>'; ?></td>
				<td><?php echo Rishe_Admin::status_pill($l['status']); ?></td>
				<td><div class="rishe-actions">
					<a href="<?php echo rishe_e(Rishe_Admin::url('lesson', ['id' => $l['id']])); ?>">ویرایش</a>
					<?php echo Rishe_Admin::delete_button('delete_lesson', ['lesson_id' => $l['id']], 'حذف', 'مرحله همراه با عبارت‌ها، فایل‌های صوتی و سؤال‌هایش حذف شود؟'); ?>
				</div></td>
			</tr>
		<?php endforeach; ?>
		<?php if (!$lessons) : ?><tr><td colspan="6" class="rishe-empty">هنوز مرحله‌ای ندارد.</td></tr><?php endif; ?>
		</tbody>
	</table></div>
</section>

<div class="rishe-grid is-2" style="align-items:start">
	<form method="post" class="rishe-card rishe-form">
		<?php echo Rishe_Csrf::field(); ?>
		<input type="hidden" name="_action" value="add_lesson">
		<h2 style="margin:0">مرحله‌ی جدید</h2>
		<label class="rishe-field"><span>عنوان</span><input class="rishe-input" name="title" id="lesson_title" required placeholder="معرفی خود"></label>
		<label class="rishe-field"><span>زمان تقریبی (دقیقه)</span><input class="rishe-input" type="number" min="1" max="120" name="minutes" id="minutes" value="5"></label>
		<label class="rishe-check"><input type="checkbox" name="is_free" id="is_free" value="1">پیش‌نمایش رایگان، حتی در دوره‌ی ویژه</label>
		<div class="rishe-form-foot"><button class="rishe-btn is-primary" type="submit">ساخت مرحله</button></div>
	</form>

	<form method="post" class="rishe-card rishe-form">
		<?php echo Rishe_Csrf::field(); ?>
		<input type="hidden" name="_action" value="save_chapter">
		<h2 style="margin:0">ویرایش فصل</h2>
		<label class="rishe-field"><span>عنوان فصل</span><input class="rishe-input" name="title" id="chapter_title" required value="<?php echo rishe_e($chapter['title']); ?>"></label>
		<label class="rishe-field"><span>ترتیب</span><input class="rishe-input" type="number" name="sort" id="chapter_sort" value="<?php echo rishe_e($chapter['sort']); ?>"></label>
		<div class="rishe-form-foot"><button class="rishe-btn" type="submit">ذخیره</button></div>
	</form>
</div>
