<?php
/**
 * A lesson: settings, phrases (with audio) and practice questions.
 */

declare(strict_types=1);

$id     = Rishe_Admin::id();
$lesson = Rishe_DB::row('SELECT * FROM rishe_lessons WHERE id = ?', [$id]);
if (!$lesson) {
	Rishe_Admin::flash('مرحله پیدا نشد.', 'bad');
	Rishe_Admin::redirect('courses');
}
$chapter = Rishe_DB::row('SELECT * FROM rishe_chapters WHERE id = ?', [$lesson['chapter_id']]);
$course  = Rishe_Learning::course((int) $chapter['course_id'], false);

if (Rishe_Admin::is_post()) {
	$action = Rishe_Admin::action();

	if ($action === 'save_lesson') {
		$title = rishe_str($_POST, 'title', 190);
		if ($title === '') {
			Rishe_Admin::flash('عنوان مرحله را وارد کنید.', 'bad');
		} else {
			Rishe_DB::update('rishe_lessons', [
				'title'   => $title,
				'minutes' => max(1, min(120, rishe_int($_POST, 'minutes', 5))),
				'is_free' => rishe_bool($_POST, 'is_free'),
				'sort'    => rishe_int($_POST, 'sort'),
				'status'  => ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'published',
			], ['id' => $id]);
			Rishe_Admin::flash('مرحله ذخیره شد.');
		}
	}

	if ($action === 'delete_phrase') {
		$phrase = Rishe_DB::row('SELECT * FROM rishe_phrases WHERE id = ? AND lesson_id = ?', [rishe_int($_POST, 'phrase_id'), $id]);
		if ($phrase) {
			Rishe_Upload::remove($phrase['audio_path']);
			Rishe_Upload::remove($phrase['teacher_audio_path']);
			Rishe_DB::delete('rishe_phrases', ['id' => $phrase['id']]);
			Rishe_Admin::flash('عبارت حذف شد.');
		}
	}

	if ($action === 'delete_question') {
		$q = Rishe_DB::row("SELECT * FROM rishe_questions WHERE id = ? AND owner_type = 'lesson' AND owner_id = ?", [rishe_int($_POST, 'question_id'), $id]);
		if ($q) {
			Rishe_Upload::remove($q['audio_path']);
			Rishe_DB::delete('rishe_questions', ['id' => $q['id']]);
			Rishe_Admin::flash('سؤال حذف شد.');
		}
	}
	Rishe_Admin::redirect('lesson', ['id' => $id]);
}

$phrases   = Rishe_DB::rows('SELECT * FROM rishe_phrases WHERE lesson_id = ? ORDER BY sort, id', [$id]);
$questions = Rishe_DB::rows("SELECT * FROM rishe_questions WHERE owner_type = 'lesson' AND owner_id = ? ORDER BY sort, id", [$id]);

$title  = 'مرحله: ' . $lesson['title'];
$crumbs = [
	'دوره‌ها'        => Rishe_Admin::url('courses'),
	$course['title'] => Rishe_Admin::url('course', ['id' => $course['id']]),
	$chapter['title'] => Rishe_Admin::url('chapter', ['id' => $chapter['id']]),
];
?>
<section class="rishe-card">
	<div class="rishe-card-head">
		<h2>عبارت‌ها</h2>
		<a class="rishe-btn is-primary" href="<?php echo rishe_e(Rishe_Admin::url('phrase', ['lesson' => $id])); ?>"><?php echo Rishe_Admin::icon('plus'); ?>عبارت جدید</a>
	</div>
	<p class="rishe-help" style="margin-bottom:10px">هر عبارت یک کارت آموزشی در اپ است: جمله‌ی انگلیسی، صدای آن، تلفظ، معنی فارسی و توضیح صوتی مدرس.</p>
	<div class="rishe-table-wrap"><table class="rishe-table">
		<thead><tr><th>#</th><th>عبارت</th><th>تلفظ</th><th>صدا</th><th></th></tr></thead>
		<tbody>
		<?php foreach ($phrases as $i => $p) : ?>
			<tr>
				<td class="is-num"><?php echo rishe_e(rishe_fa_digits($i + 1)); ?></td>
				<td><b class="ltr" style="display:block"><?php echo rishe_e($p['en']); ?></b><span class="rishe-sub"><?php echo rishe_e($p['fa']); ?></span></td>
				<td><?php echo rishe_e($p['pron_fa']); ?><span class="rishe-sub ltr"><?php echo rishe_e($p['ipa']); ?></span></td>
				<td>
					<?php echo $p['audio_path'] ? '<span class="rishe-pill is-ok">عبارت</span>' : '<span class="rishe-pill is-bad">بدون صدا</span>'; ?>
					<?php echo $p['teacher_audio_path'] ? '<span class="rishe-pill is-ok">مدرس</span>' : ''; ?>
				</td>
				<td><div class="rishe-actions">
					<a href="<?php echo rishe_e(Rishe_Admin::url('phrase', ['lesson' => $id, 'id' => $p['id']])); ?>">ویرایش</a>
					<?php echo Rishe_Admin::delete_button('delete_phrase', ['phrase_id' => $p['id']]); ?>
				</div></td>
			</tr>
		<?php endforeach; ?>
		<?php if (!$phrases) : ?><tr><td colspan="5" class="rishe-empty">هنوز عبارتی ندارد.</td></tr><?php endif; ?>
		</tbody>
	</table></div>
</section>

<section class="rishe-card">
	<div class="rishe-card-head">
		<h2>تمرین‌های پایان مرحله</h2>
		<a class="rishe-btn" href="<?php echo rishe_e(Rishe_Admin::url('question', ['owner' => 'lesson', 'owner_id' => $id])); ?>"><?php echo Rishe_Admin::icon('plus'); ?>سؤال جدید</a>
	</div>
	<div class="rishe-table-wrap"><table class="rishe-table">
		<thead><tr><th>#</th><th>سؤال</th><th>نوع</th><th>پاسخ درست</th><th></th></tr></thead>
		<tbody>
		<?php foreach ($questions as $i => $q) : $opts = json_decode($q['options_json'], true) ?: []; ?>
			<tr>
				<td class="is-num"><?php echo rishe_e(rishe_fa_digits($i + 1)); ?></td>
				<td><?php echo rishe_e($q['prompt']); ?></td>
				<td><?php echo $q['type'] === 'listen' ? 'شنیداری' : 'معنی'; ?></td>
				<td><?php echo rishe_e($opts[(int) $q['answer']] ?? '—'); ?></td>
				<td><div class="rishe-actions">
					<a href="<?php echo rishe_e(Rishe_Admin::url('question', ['owner' => 'lesson', 'owner_id' => $id, 'id' => $q['id']])); ?>">ویرایش</a>
					<?php echo Rishe_Admin::delete_button('delete_question', ['question_id' => $q['id']]); ?>
				</div></td>
			</tr>
		<?php endforeach; ?>
		<?php if (!$questions) : ?><tr><td colspan="5" class="rishe-empty">تمرینی ندارد. مرحله بدون تمرین هم قابل تکمیل است.</td></tr><?php endif; ?>
		</tbody>
	</table></div>
</section>

<form method="post" class="rishe-card rishe-form" style="max-width:640px">
	<?php echo Rishe_Csrf::field(); ?>
	<input type="hidden" name="_action" value="save_lesson">
	<h2 style="margin:0">تنظیمات مرحله</h2>
	<div class="rishe-grid is-2">
		<label class="rishe-field"><span>عنوان</span><input class="rishe-input" name="title" id="title" required value="<?php echo rishe_e($lesson['title']); ?>"></label>
		<label class="rishe-field"><span>زمان (دقیقه)</span><input class="rishe-input" type="number" min="1" max="120" name="minutes" id="minutes" value="<?php echo rishe_e($lesson['minutes']); ?>"></label>
		<label class="rishe-field"><span>ترتیب</span><input class="rishe-input" type="number" name="sort" id="sort" value="<?php echo rishe_e($lesson['sort']); ?>"></label>
		<label class="rishe-field"><span>وضعیت</span><select name="status" id="status">
			<option value="published" <?php echo $lesson['status'] === 'published' ? 'selected' : ''; ?>>منتشر شده</option>
			<option value="draft" <?php echo $lesson['status'] === 'draft' ? 'selected' : ''; ?>>پیش‌نویس</option>
		</select></label>
	</div>
	<label class="rishe-check"><input type="checkbox" name="is_free" id="is_free" value="1" <?php echo (int) $lesson['is_free'] ? 'checked' : ''; ?>>پیش‌نمایش رایگان، حتی در دوره‌ی ویژه</label>
	<div class="rishe-form-foot"><button class="rishe-btn is-primary" type="submit">ذخیره</button></div>
</form>
