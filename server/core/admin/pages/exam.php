<?php
/**
 * Final exam questions of a course.
 */

declare(strict_types=1);

$id     = Rishe_Admin::id();
$course = Rishe_Learning::course($id, false);
if (!$course) {
	Rishe_Admin::flash('دوره پیدا نشد.', 'bad');
	Rishe_Admin::redirect('courses');
}

if (Rishe_Admin::is_post() && Rishe_Admin::action() === 'delete_question') {
	$q = Rishe_DB::row("SELECT * FROM rishe_questions WHERE id = ? AND owner_type = 'exam' AND owner_id = ?", [rishe_int($_POST, 'question_id'), $id]);
	if ($q) {
		Rishe_Upload::remove($q['audio_path']);
		Rishe_DB::delete('rishe_questions', ['id' => $q['id']]);
		Rishe_Admin::flash('سؤال حذف شد.');
	}
	Rishe_Admin::redirect('exam', ['id' => $id]);
}

$questions = Rishe_DB::rows("SELECT * FROM rishe_questions WHERE owner_type = 'exam' AND owner_id = ? ORDER BY sort, id", [$id]);

$title  = 'آزمون پایانی: ' . $course['title'];
$crumbs = ['دوره‌ها' => Rishe_Admin::url('courses'), $course['title'] => Rishe_Admin::url('course', ['id' => $id])];
?>
<section class="rishe-card">
	<div class="rishe-card-head">
		<h2>سؤال‌های آزمون</h2>
		<a class="rishe-btn is-primary" href="<?php echo rishe_e(Rishe_Admin::url('question', ['owner' => 'exam', 'owner_id' => $id])); ?>"><?php echo Rishe_Admin::icon('plus'); ?>سؤال جدید</a>
	</div>
	<p class="rishe-help" style="margin-bottom:10px">آزمون بعد از تکمیل همه‌ی مراحل باز می‌شود و پاسخ‌ها روی سرور تصحیح می‌شوند. حد نصاب فعلی: <?php echo rishe_e(rishe_fa_digits($course['pass_score'])); ?> از ۱۰۰.</p>
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
					<a href="<?php echo rishe_e(Rishe_Admin::url('question', ['owner' => 'exam', 'owner_id' => $id, 'id' => $q['id']])); ?>">ویرایش</a>
					<?php echo Rishe_Admin::delete_button('delete_question', ['question_id' => $q['id']]); ?>
				</div></td>
			</tr>
		<?php endforeach; ?>
		<?php if (!$questions) : ?><tr><td colspan="5" class="rishe-empty">سؤالی ندارد. بدون آزمون، مدرک بعد از تکمیل مراحل قابل درخواست است.</td></tr><?php endif; ?>
		</tbody>
	</table></div>
</section>
