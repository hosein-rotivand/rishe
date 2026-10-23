<?php
/**
 * Create / edit a question (lesson practice or course final exam).
 */

declare(strict_types=1);

$owner    = ($_GET['owner'] ?? '') === 'exam' ? 'exam' : 'lesson';
$owner_id = Rishe_Admin::id('owner_id');
$id       = Rishe_Admin::id();

if ($owner === 'lesson') {
	$parent = Rishe_DB::row('SELECT id, title FROM rishe_lessons WHERE id = ?', [$owner_id]);
	$back   = Rishe_Admin::url('lesson', ['id' => $owner_id]);
	$crumbs = $parent ? ['مرحله: ' . $parent['title'] => $back] : [];
} else {
	$parent = Rishe_DB::row('SELECT id, title FROM rishe_courses WHERE id = ?', [$owner_id]);
	$back   = Rishe_Admin::url('exam', ['id' => $owner_id]);
	$crumbs = $parent ? ['آزمون پایانی ' . $parent['title'] => $back] : [];
}
if (!$parent) {
	Rishe_Admin::flash('مورد والد پیدا نشد.', 'bad');
	Rishe_Admin::redirect('courses');
}

$question = $id ? Rishe_DB::row('SELECT * FROM rishe_questions WHERE id = ? AND owner_type = ? AND owner_id = ?', [$id, $owner, $owner_id]) : null;
if ($id && !$question) {
	rishe_redirect($back);
}

$error = '';
if (Rishe_Admin::is_post() && Rishe_Admin::action() === 'save_question') {
	$options = [];
	foreach ((array) ($_POST['options'] ?? []) as $i => $opt) {
		$opt = is_scalar($opt) ? trim(mb_substr((string) $opt, 0, 255)) : '';
		if ($opt !== '') {
			$options[(int) $i] = $opt;
		}
	}
	$answer_index = rishe_int($_POST, 'answer');
	$data = [
		'type'     => ($_POST['type'] ?? '') === 'listen' ? 'listen' : 'meaning',
		'prompt'   => rishe_str($_POST, 'prompt', 500),
		'say_text' => rishe_str($_POST, 'say_text', 500),
		'sort'     => rishe_int($_POST, 'sort'),
	];
	if ($data['prompt'] === '') {
		$error = 'متن سؤال را وارد کنید.';
	} elseif (count($options) < 2) {
		$error = 'حداقل دو گزینه وارد کنید.';
	} elseif (!isset($options[$answer_index])) {
		$error = 'گزینه‌ی درست را انتخاب کنید (گزینه‌ی انتخاب‌شده نباید خالی باشد).';
	} else {
		// Re-index options and keep the answer pointing at the same text
		$answer_text          = $options[$answer_index];
		$options              = array_values($options);
		$data['options_json'] = json_encode($options, JSON_UNESCAPED_UNICODE);
		$data['answer']       = (int) array_search($answer_text, $options, true);
		try {
			$audio = Rishe_Upload::audio('audio');
			if ($audio !== null || rishe_bool($_POST, 'remove_audio')) {
				Rishe_Upload::remove($question['audio_path'] ?? null);
				$data['audio_path'] = $audio;
			}
			if ($question) {
				Rishe_DB::update('rishe_questions', $data, ['id' => $id]);
			} else {
				$data['owner_type'] = $owner;
				$data['owner_id']   = $owner_id;
				Rishe_DB::insert('rishe_questions', $data);
			}
			Rishe_Admin::flash('سؤال ذخیره شد.');
			if (rishe_bool($_POST, 'add_another')) {
				Rishe_Admin::redirect('question', ['owner' => $owner, 'owner_id' => $owner_id]);
			}
			rishe_redirect($back);
		} catch (RuntimeException $e) {
			$error = $e->getMessage();
		}
	}
	$question = array_merge($question ?? [], $data, ['options_json' => json_encode(array_values($options), JSON_UNESCAPED_UNICODE), 'answer' => 0]);
}

$q    = $question ?: ['type' => 'meaning', 'prompt' => '', 'say_text' => '', 'audio_path' => null, 'options_json' => '[]', 'answer' => 0, 'sort' => 0];
$opts = json_decode((string) $q['options_json'], true) ?: [];

$title = $id ? 'ویرایش سؤال' : ($owner === 'exam' ? 'سؤال آزمون پایانی' : 'سؤال تمرین');
?>
<?php if ($error !== '') : ?><div class="rishe-flash is-bad"><?php echo rishe_e($error); ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="rishe-card rishe-form" style="max-width:760px">
	<?php echo Rishe_Csrf::field(); ?>
	<input type="hidden" name="_action" value="save_question">
	<div class="rishe-grid is-2">
		<label class="rishe-field"><span>نوع سؤال</span><select name="type" id="rishe-question-type">
			<option value="meaning" <?php echo $q['type'] === 'meaning' ? 'selected' : ''; ?>>انتخاب معنی یا پاسخ درست</option>
			<option value="listen" <?php echo $q['type'] === 'listen' ? 'selected' : ''; ?>>شنیداری: گوش بده و انتخاب کن</option>
		</select></label>
		<label class="rishe-field"><span>ترتیب</span><input class="rishe-input" type="number" name="sort" id="sort" value="<?php echo rishe_e($q['sort']); ?>"></label>
	</div>
	<label class="rishe-field"><span>متن سؤال</span><input class="rishe-input" name="prompt" id="prompt" required value="<?php echo rishe_e($q['prompt']); ?>" placeholder="معنی «Nice to meet you» کدام است؟"></label>

	<div class="rishe-grid is-2" data-listen-only>
		<div class="rishe-field">
			<span>فایل صوتی سؤال</span>
			<?php if ($q['audio_path']) : ?>
				<div class="rishe-audio"><audio controls preload="none" src="<?php echo rishe_e(rishe_upload_url($q['audio_path'])); ?>"></audio><label class="rishe-check"><input type="checkbox" name="remove_audio" value="1">حذف این فایل</label></div>
			<?php endif; ?>
			<input type="file" name="audio" id="audio" accept=".mp3,.m4a,.ogg,.wav,.webm,audio/*">
		</div>
		<label class="rishe-field"><span>متن جایگزین صدا</span><input class="rishe-input ltr" name="say_text" id="say_text" value="<?php echo rishe_e($q['say_text']); ?>" placeholder="Where are you from?"><small>اگر فایل صوتی نباشد، اپ این متن را با موتور گفتار گوشی می‌خواند.</small></label>
	</div>

	<div class="rishe-field">
		<span>گزینه‌ها <small>(دکمه‌ی کنار گزینه‌ی درست را بزنید)</small></span>
		<div class="rishe-options">
			<?php for ($i = 0; $i < 4; $i++) : ?>
				<label class="rishe-option">
					<input type="radio" name="answer" value="<?php echo $i; ?>" <?php echo (int) $q['answer'] === $i ? 'checked' : ''; ?> aria-label="گزینه‌ی درست">
					<input class="rishe-input" name="options[<?php echo $i; ?>]" id="option_<?php echo $i; ?>" value="<?php echo rishe_e($opts[$i] ?? ''); ?>" placeholder="گزینه‌ی <?php echo rishe_fa_digits($i + 1); ?><?php echo $i > 1 ? ' (اختیاری)' : ''; ?>">
				</label>
			<?php endfor; ?>
		</div>
	</div>
	<div class="rishe-form-foot">
		<button class="rishe-btn is-primary" type="submit">ذخیره</button>
		<?php if (!$id) : ?><button class="rishe-btn" type="submit" name="add_another" value="1">ذخیره و سؤال بعدی</button><?php endif; ?>
		<a href="<?php echo rishe_e($back); ?>">انصراف</a>
	</div>
</form>
