<?php
/**
 * Create / edit a phrase with its audio files.
 */

declare(strict_types=1);

$lesson_id = Rishe_Admin::id('lesson');
$id        = Rishe_Admin::id();
$lesson    = Rishe_DB::row('SELECT * FROM rishe_lessons WHERE id = ?', [$lesson_id]);
if (!$lesson) {
	Rishe_Admin::flash('مرحله پیدا نشد.', 'bad');
	Rishe_Admin::redirect('courses');
}
$phrase = $id ? Rishe_DB::row('SELECT * FROM rishe_phrases WHERE id = ? AND lesson_id = ?', [$id, $lesson_id]) : null;
if ($id && !$phrase) {
	Rishe_Admin::redirect('lesson', ['id' => $lesson_id]);
}

$error = '';
if (Rishe_Admin::is_post() && Rishe_Admin::action() === 'save_phrase') {
	$data = [
		'en'         => rishe_str($_POST, 'en', 500),
		'fa'         => rishe_str($_POST, 'fa', 500),
		'ipa'        => rishe_str($_POST, 'ipa', 255),
		'pron_fa'    => rishe_str($_POST, 'pron_fa', 255),
		'words_json' => Rishe_Learning::words_from_text(rishe_str($_POST, 'words', 3000)),
		'sort'       => rishe_int($_POST, 'sort'),
	];
	if ($data['en'] === '') {
		$error = 'متن انگلیسی عبارت را وارد کنید.';
	} else {
		try {
			$audio = Rishe_Upload::audio('audio');
			try {
				$teacher = Rishe_Upload::audio('teacher_audio');
			} catch (RuntimeException $e) {
				Rishe_Upload::remove($audio);
				throw $e;
			}
			if ($audio !== null || rishe_bool($_POST, 'remove_audio')) {
				Rishe_Upload::remove($phrase['audio_path'] ?? null);
				$data['audio_path'] = $audio;
			}
			if ($teacher !== null || rishe_bool($_POST, 'remove_teacher_audio')) {
				Rishe_Upload::remove($phrase['teacher_audio_path'] ?? null);
				$data['teacher_audio_path'] = $teacher;
			}
			if ($phrase) {
				Rishe_DB::update('rishe_phrases', $data, ['id' => $id]);
			} else {
				$data['lesson_id'] = $lesson_id;
				if ($data['sort'] === 0) {
					$data['sort'] = (int) Rishe_DB::value('SELECT COALESCE(MAX(sort), 0) + 1 FROM rishe_phrases WHERE lesson_id = ?', [$lesson_id]);
				}
				Rishe_DB::insert('rishe_phrases', $data);
			}
			Rishe_Admin::flash('عبارت ذخیره شد.');
			if (rishe_bool($_POST, 'add_another')) {
				Rishe_Admin::redirect('phrase', ['lesson' => $lesson_id]);
			}
			Rishe_Admin::redirect('lesson', ['id' => $lesson_id]);
		} catch (RuntimeException $e) {
			$error = $e->getMessage();
		}
	}
	$phrase = array_merge($phrase ?? [], $data, ['words_json' => $data['words_json']]);
}

$p = $phrase ?: ['en' => '', 'fa' => '', 'ipa' => '', 'pron_fa' => '', 'words_json' => '', 'audio_path' => null, 'teacher_audio_path' => null, 'sort' => 0];
$max_mb = max(1, Rishe_Settings::int('upload_max_mb'));

$title  = $id ? 'ویرایش عبارت' : 'عبارت جدید';
$crumbs = ['مرحله: ' . $lesson['title'] => Rishe_Admin::url('lesson', ['id' => $lesson_id])];
?>
<?php if ($error !== '') : ?><div class="rishe-flash is-bad"><?php echo rishe_e($error); ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="rishe-card rishe-form" style="max-width:760px">
	<?php echo Rishe_Csrf::field(); ?>
	<input type="hidden" name="_action" value="save_phrase">
	<label class="rishe-field"><span>عبارت انگلیسی</span><input class="rishe-input ltr" name="en" id="en" required value="<?php echo rishe_e($p['en']); ?>" placeholder="Nice to meet you."></label>
	<label class="rishe-field"><span>معنی فارسی</span><input class="rishe-input" name="fa" id="fa" value="<?php echo rishe_e($p['fa']); ?>" placeholder="از آشنایی با شما خوشوقتم."></label>
	<div class="rishe-grid is-2">
		<label class="rishe-field"><span>تلفظ به فارسی</span><input class="rishe-input" name="pron_fa" id="pron_fa" value="<?php echo rishe_e($p['pron_fa']); ?>" placeholder="نایس تو میت یو"></label>
		<label class="rishe-field"><span>آوانگاری (IPA)</span><input class="rishe-input ltr" name="ipa" id="ipa" value="<?php echo rishe_e($p['ipa']); ?>" placeholder="/naɪs tə miːt juː/"></label>
	</div>
	<label class="rishe-field"><span>معنی واژه‌ها</span><textarea class="ltr" name="words" id="words" placeholder="Nice = خوب، خوشایند&#10;meet = ملاقات کردن"><?php echo rishe_e(Rishe_Learning::words_to_text($p['words_json'])); ?></textarea><small>هر خط یک واژه: <span class="ltr">word = معنی</span>. در اپ با زدن روی واژه نمایش داده می‌شود.</small></label>

	<div class="rishe-grid is-2">
		<div class="rishe-field">
			<span>صدای عبارت</span>
			<?php if ($p['audio_path']) : ?>
				<div class="rishe-audio"><audio controls preload="none" src="<?php echo rishe_e(rishe_upload_url($p['audio_path'])); ?>"></audio><label class="rishe-check"><input type="checkbox" name="remove_audio" value="1">حذف این فایل</label></div>
			<?php endif; ?>
			<input type="file" name="audio" id="audio" accept=".mp3,.m4a,.ogg,.wav,.webm,audio/*">
			<small>تلفظ بومی جمله. mp3، m4a، ogg، wav یا webm تا <?php echo rishe_e(rishe_fa_digits($max_mb)); ?> مگابایت.</small>
		</div>
		<div class="rishe-field">
			<span>توضیح صوتی مدرس</span>
			<?php if ($p['teacher_audio_path']) : ?>
				<div class="rishe-audio"><audio controls preload="none" src="<?php echo rishe_e(rishe_upload_url($p['teacher_audio_path'])); ?>"></audio><label class="rishe-check"><input type="checkbox" name="remove_teacher_audio" value="1">حذف این فایل</label></div>
			<?php endif; ?>
			<input type="file" name="teacher_audio" id="teacher_audio" accept=".mp3,.m4a,.ogg,.wav,.webm,audio/*">
			<small>اختیاری؛ ویس توضیح فارسی شما برای این عبارت.</small>
		</div>
	</div>
	<label class="rishe-field" style="max-width:200px"><span>ترتیب</span><input class="rishe-input" type="number" name="sort" id="sort" value="<?php echo rishe_e($p['sort']); ?>"></label>
	<div class="rishe-form-foot">
		<button class="rishe-btn is-primary" type="submit">ذخیره</button>
		<?php if (!$id) : ?><button class="rishe-btn" type="submit" name="add_another" value="1">ذخیره و عبارت بعدی</button><?php endif; ?>
		<a href="<?php echo rishe_e(Rishe_Admin::url('lesson', ['id' => $lesson_id])); ?>">انصراف</a>
	</div>
</form>
