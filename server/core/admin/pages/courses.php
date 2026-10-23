<?php
/**
 * Course list.
 */

declare(strict_types=1);

$courses = Rishe_DB::rows(
	'SELECT c.*,
		(SELECT COUNT(*) FROM rishe_chapters ch WHERE ch.course_id = c.id) AS chapters,
		(SELECT COUNT(*) FROM rishe_lessons l INNER JOIN rishe_chapters ch ON ch.id = l.chapter_id WHERE ch.course_id = c.id) AS lessons,
		(SELECT COUNT(*) FROM rishe_questions q WHERE q.owner_type = \'exam\' AND q.owner_id = c.id) AS exam
	 FROM rishe_courses c ORDER BY c.sort, c.id'
);
?>
<section class="rishe-card">
	<div class="rishe-card-head">
		<h2>همه‌ی دوره‌ها</h2>
		<a class="rishe-btn is-primary" href="<?php echo rishe_e(Rishe_Admin::url('course')); ?>"><?php echo Rishe_Admin::icon('plus'); ?>دوره‌ی جدید</a>
	</div>
	<div class="rishe-table-wrap"><table class="rishe-table">
		<thead><tr><th>دوره</th><th>سطح</th><th>فصل / مرحله</th><th>آزمون پایانی</th><th>دسترسی</th><th>وضعیت</th><th></th></tr></thead>
		<tbody>
		<?php foreach ($courses as $c) : ?>
			<tr>
				<td><a href="<?php echo rishe_e(Rishe_Admin::url('course', ['id' => $c['id']])); ?>"><b><?php echo rishe_e($c['title']); ?></b></a><span class="rishe-sub ltr"><?php echo rishe_e($c['title_en']); ?></span></td>
				<td><?php echo rishe_e($c['level']); ?></td>
				<td class="is-num"><?php echo rishe_e(rishe_fa_digits($c['chapters'] . ' / ' . $c['lessons'])); ?></td>
				<td class="is-num"><?php echo (int) $c['exam'] > 0 ? rishe_e(rishe_fa_digits($c['exam'])) . ' سؤال' : '—'; ?></td>
				<td><?php echo (int) $c['is_premium'] ? '<span class="rishe-pill is-warn">ویژه</span>' : '<span class="rishe-pill is-mute">رایگان</span>'; ?></td>
				<td><?php echo Rishe_Admin::status_pill($c['status']); ?></td>
				<td><a href="<?php echo rishe_e(Rishe_Admin::url('course', ['id' => $c['id']])); ?>">مدیریت</a></td>
			</tr>
		<?php endforeach; ?>
		<?php if (!$courses) : ?><tr><td colspan="7" class="rishe-empty">هنوز دوره‌ای نساخته‌اید.</td></tr><?php endif; ?>
		</tbody>
	</table></div>
</section>
