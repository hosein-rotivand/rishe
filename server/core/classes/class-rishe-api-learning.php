<?php
/**
 * API: courses, lessons, progress and final exams.
 */

declare(strict_types=1);

final class Rishe_Api_Learning {

	public static function courses(): void {
		$user = Rishe_Api::require_user();
		$out  = [];
		foreach (Rishe_Learning::published_courses() as $course) {
			$lessons = Rishe_Learning::lessons_with_state((int) $course['id'], (int) $user['id']);
			$next    = null;
			foreach ($lessons as $lesson) {
				if (in_array($lesson['state'], ['current', 'open'], true)) {
					$next = ['id' => (int) $lesson['id'], 'title' => $lesson['title'], 'chapter_title' => $lesson['chapter_title'], 'minutes' => (int) $lesson['minutes']];
					break;
				}
			}
			$item             = self::course_summary($course, (int) $user['id']);
			$item['progress'] = Rishe_Learning::progress_percent($lessons);
			$item['lessons']  = count($lessons);
			$item['started']  = $item['progress'] > 0;
			$item['next']     = $next;
			$out[]            = $item;
		}
		Rishe_Api::json(['ok' => true, 'courses' => $out]);
	}

	public static function course(string $id): void {
		$user   = Rishe_Api::require_user();
		$course = Rishe_Learning::course((int) $id);
		if (!$course) {
			Rishe_Api::error('دوره پیدا نشد.', 404, 'not_found');
		}
		$lessons  = Rishe_Learning::lessons_with_state((int) $course['id'], (int) $user['id']);
		$access   = Rishe_Learning::has_course_access((int) $user['id'], $course);
		$chapters = [];
		foreach (Rishe_DB::rows('SELECT id, title FROM rishe_chapters WHERE course_id = ? ORDER BY sort, id', [$course['id']]) as $ch) {
			$chapters[(int) $ch['id']] = ['id' => (int) $ch['id'], 'title' => $ch['title'], 'lessons' => []];
		}
		foreach ($lessons as $lesson) {
			$cid = (int) $lesson['chapter_id'];
			if (!isset($chapters[$cid])) {
				continue;
			}
			$chapters[$cid]['lessons'][] = [
				'id'       => (int) $lesson['id'],
				'title'    => $lesson['title'],
				'minutes'  => (int) $lesson['minutes'],
				'is_free'  => (bool) $lesson['is_free'],
				'state'    => $lesson['state'],
				'can_open' => $lesson['state'] !== 'locked' && ($access || (int) $lesson['is_free'] === 1),
				'phrases'  => (int) Rishe_DB::value('SELECT COUNT(*) FROM rishe_phrases WHERE lesson_id = ?', [$lesson['id']]),
			];
		}
		$chapters = array_values(array_filter($chapters, static fn($c) => $c['lessons']));

		$cert  = Rishe_Certificates::for_user_course((int) $user['id'], (int) $course['id']);
		$check = Rishe_Certificates::eligibility((int) $user['id'], $course);

		$data                = self::course_summary($course, (int) $user['id']);
		$data['description'] = (string) $course['description'];
		$data['progress']    = Rishe_Learning::progress_percent($lessons);
		$data['lessons']     = count($lessons);
		$data['done']        = count(array_filter($lessons, static fn($l) => $l['state'] === 'done'));
		$data['chapters']    = $chapters;
		$data['exam']        = [
			'questions'  => Rishe_Learning::exam_count((int) $course['id']),
			'best_score' => $check['score'],
			'pass_score' => (int) $course['pass_score'],
			'available'  => $check['completed'],
		];
		$data['certificate'] = [
			'eligible' => $check['eligible'],
			'reason'   => $check['reason'],
			'status'   => $cert ? $cert['status'] : null,
		];
		Rishe_Api::json(['ok' => true, 'course' => $data]);
	}

	public static function lesson(string $id): void {
		$user = Rishe_Api::require_user();
		[$lesson, $course] = self::open_lesson((int) $id, $user);

		$phrases = [];
		foreach (Rishe_DB::rows('SELECT * FROM rishe_phrases WHERE lesson_id = ? ORDER BY sort, id', [$lesson['id']]) as $p) {
			$phrases[] = [
				'id'                => (int) $p['id'],
				'en'                => $p['en'],
				'fa'                => $p['fa'],
				'ipa'               => $p['ipa'],
				'pron_fa'           => $p['pron_fa'],
				'words'             => (object) Rishe_Learning::decode_words($p['words_json']),
				'audio_url'         => rishe_upload_url($p['audio_path']),
				'teacher_audio_url' => rishe_upload_url($p['teacher_audio_path']),
			];
		}
		$questions = [];
		foreach (Rishe_DB::rows("SELECT * FROM rishe_questions WHERE owner_type = 'lesson' AND owner_id = ? ORDER BY sort, id", [$lesson['id']]) as $q) {
			$questions[] = self::question_payload($q, true);
		}
		Rishe_Api::json([
			'ok'        => true,
			'lesson'    => [
				'id'            => (int) $lesson['id'],
				'title'         => $lesson['title'],
				'chapter_title' => $lesson['chapter_title'],
				'course_id'     => (int) $course['id'],
				'course_title'  => $course['title'],
				'state'         => $lesson['state'],
			],
			'phrases'   => $phrases,
			'questions' => $questions,
		]);
	}

	public static function complete_lesson(string $id): void {
		$user = Rishe_Api::require_user();
		[$lesson, $course] = self::open_lesson((int) $id, $user);

		$in      = Rishe_Api::input();
		$total   = (int) Rishe_DB::value("SELECT COUNT(*) FROM rishe_questions WHERE owner_type = 'lesson' AND owner_id = ?", [$lesson['id']]);
		$correct = max(0, min($total, rishe_int($in, 'correct')));
		$xp      = Rishe_Learning::XP_PER_LESSON + ($correct * Rishe_Learning::XP_PER_CORRECT);
		$first   = $lesson['state'] !== 'done';

		if ($first) {
			Rishe_DB::query(
				'INSERT IGNORE INTO rishe_progress (user_id, lesson_id, correct, total, xp, completed_at) VALUES (?, ?, ?, ?, ?, ?)',
				[$user['id'], $lesson['id'], $correct, $total, $xp, rishe_now()]
			);
		}
		Rishe_Learning::record_activity((int) $user['id']);

		$next = null;
		foreach (Rishe_Learning::lessons_with_state((int) $course['id'], (int) $user['id']) as $l) {
			if (in_array($l['state'], ['current', 'open'], true)) {
				$next = ['id' => (int) $l['id'], 'title' => $l['title']];
				break;
			}
		}
		Rishe_Api::json([
			'ok'               => true,
			'xp_gained'        => $first ? $xp : 0,
			'correct'          => $correct,
			'total'            => $total,
			'stats'            => Rishe_Learning::stats((int) $user['id']),
			'next'             => $next,
			'course_completed' => Rishe_Learning::is_course_completed((int) $course['id'], (int) $user['id']),
		]);
	}

	public static function exam(string $id): void {
		$user   = Rishe_Api::require_user();
		$course = self::exam_course((int) $id, $user);
		$qs     = [];
		foreach (Rishe_DB::rows("SELECT * FROM rishe_questions WHERE owner_type = 'exam' AND owner_id = ? ORDER BY sort, id", [$course['id']]) as $q) {
			$qs[] = self::question_payload($q, false);
		}
		Rishe_Api::json([
			'ok'         => true,
			'course'     => ['id' => (int) $course['id'], 'title' => $course['title'], 'pass_score' => (int) $course['pass_score']],
			'questions'  => $qs,
			'best_score' => Rishe_Learning::best_exam_score((int) $user['id'], (int) $course['id']),
		]);
	}

	public static function submit_exam(string $id): void {
		$user   = Rishe_Api::require_user();
		$course = self::exam_course((int) $id, $user);
		if (!Rishe_Throttle::hit('exam|' . $user['id'] . '|' . $course['id'], 10, 3600)) {
			Rishe_Api::error('تعداد دفعات آزمون در این ساعت تمام شده است.', 429, 'throttled');
		}
		$answers = Rishe_Api::input()['answers'] ?? [];
		if (!is_array($answers)) {
			$answers = [];
		}
		$rows    = Rishe_DB::rows("SELECT id, answer FROM rishe_questions WHERE owner_type = 'exam' AND owner_id = ?", [$course['id']]);
		$correct = 0;
		foreach ($rows as $q) {
			$given = $answers[(string) $q['id']] ?? $answers[(int) $q['id']] ?? null;
			if ($given !== null && is_numeric($given) && (int) $given === (int) $q['answer']) {
				$correct++;
			}
		}
		$score = (int) round(($correct / count($rows)) * 100);
		Rishe_DB::insert('rishe_exam_results', [
			'user_id'    => (int) $user['id'],
			'course_id'  => (int) $course['id'],
			'score'      => $score,
			'created_at' => rishe_now(),
		]);
		Rishe_Learning::record_activity((int) $user['id']);
		Rishe_Api::json([
			'ok'         => true,
			'score'      => $score,
			'correct'    => $correct,
			'total'      => count($rows),
			'passed'     => $score >= (int) $course['pass_score'],
			'pass_score' => (int) $course['pass_score'],
			'best_score' => Rishe_Learning::best_exam_score((int) $user['id'], (int) $course['id']),
		]);
	}

	private static function course_summary(array $course, int $user_id): array {
		return [
			'id'         => (int) $course['id'],
			'title'      => $course['title'],
			'title_en'   => $course['title_en'],
			'code'       => $course['code'],
			'level'      => $course['level'],
			'hours'      => (int) $course['hours'],
			'color'      => $course['color'],
			'is_premium' => Rishe_Settings::payments_active() && (int) $course['is_premium'] === 1,
			'price'      => (int) $course['price'],
			'has_access' => Rishe_Learning::has_course_access($user_id, $course),
		];
	}

	/**
	 * @return array{0: array, 1: array} [lesson with state, course]
	 */
	private static function open_lesson(int $lesson_id, array $user): array {
		$row = Rishe_DB::row(
			"SELECT l.id, c.course_id FROM rishe_lessons l INNER JOIN rishe_chapters c ON c.id = l.chapter_id WHERE l.id = ? AND l.status = 'published'",
			[$lesson_id]
		);
		$course = $row ? Rishe_Learning::course((int) $row['course_id']) : null;
		if (!$row || !$course) {
			Rishe_Api::error('مرحله پیدا نشد.', 404, 'not_found');
		}
		$lesson = null;
		foreach (Rishe_Learning::lessons_with_state((int) $course['id'], (int) $user['id']) as $l) {
			if ((int) $l['id'] === $lesson_id) {
				$lesson = $l;
			}
		}
		if (!$lesson || $lesson['state'] === 'locked') {
			Rishe_Api::error('اول مرحله‌ی قبلی را تمام کنید.', 403, 'locked');
		}
		if (!Rishe_Learning::can_open_lesson((int) $user['id'], $course, $lesson)) {
			Rishe_Api::error('این مرحله مخصوص اشتراک ویژه است.', 402, 'premium');
		}
		return [$lesson, $course];
	}

	private static function exam_course(int $course_id, array $user): array {
		$course = Rishe_Learning::course($course_id);
		if (!$course) {
			Rishe_Api::error('دوره پیدا نشد.', 404, 'not_found');
		}
		if (Rishe_Learning::exam_count($course_id) === 0) {
			Rishe_Api::error('این دوره آزمون پایانی ندارد.', 404, 'no_exam');
		}
		if (!Rishe_Learning::is_course_completed($course_id, (int) $user['id'])) {
			Rishe_Api::error('آزمون پایانی بعد از تکمیل همه‌ی مراحل باز می‌شود.', 403, 'locked');
		}
		return $course;
	}

	private static function question_payload(array $q, bool $with_answer): array {
		$options = json_decode((string) $q['options_json'], true);
		$data    = [
			'id'        => (int) $q['id'],
			'type'      => $q['type'],
			'prompt'    => $q['prompt'],
			'say_text'  => $q['say_text'],
			'audio_url' => rishe_upload_url($q['audio_path']),
			'options'   => is_array($options) ? array_values($options) : [],
		];
		if ($with_answer) {
			$data['answer'] = (int) $q['answer'];
		}
		return $data;
	}
}
