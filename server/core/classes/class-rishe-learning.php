<?php
/**
 * Course structure, lesson unlocking, access rules and learner stats.
 */

declare(strict_types=1);

final class Rishe_Learning {

	public const XP_PER_LESSON  = 10;
	public const XP_PER_CORRECT = 2;

	public static function course(int $id, bool $published_only = true): ?array {
		$sql = 'SELECT * FROM rishe_courses WHERE id = ?' . ($published_only ? " AND status = 'published'" : '');
		return Rishe_DB::row($sql, [$id]);
	}

	public static function published_courses(): array {
		return Rishe_DB::rows("SELECT * FROM rishe_courses WHERE status = 'published' ORDER BY sort, id");
	}

	/**
	 * Published lessons of a course in learning order.
	 */
	public static function ordered_lessons(int $course_id): array {
		return Rishe_DB::rows(
			"SELECT l.*, c.title AS chapter_title, c.id AS chapter_id
			 FROM rishe_lessons l
			 INNER JOIN rishe_chapters c ON c.id = l.chapter_id
			 WHERE c.course_id = ? AND l.status = 'published'
			 ORDER BY c.sort, c.id, l.sort, l.id",
			[$course_id]
		);
	}

	/**
	 * @return array<int, true> completed lesson ids
	 */
	public static function completed_ids(int $user_id, array $lesson_ids): array {
		if (!$lesson_ids) {
			return [];
		}
		$params = array_merge([$user_id], $lesson_ids);
		$rows   = Rishe_DB::rows(
			'SELECT lesson_id FROM rishe_progress WHERE user_id = ? AND lesson_id IN (' . Rishe_DB::placeholders($lesson_ids) . ')',
			$params
		);
		$out = [];
		foreach ($rows as $row) {
			$out[(int) $row['lesson_id']] = true;
		}
		return $out;
	}

	/**
	 * Lessons with a state: done | current | open | locked.
	 * A lesson unlocks when the previous lesson in the course is done.
	 */
	public static function lessons_with_state(int $course_id, int $user_id): array {
		$lessons = self::ordered_lessons($course_id);
		$done    = self::completed_ids($user_id, array_map('intval', array_column($lessons, 'id')));
		$prev_done      = true;
		$current_marked = false;
		foreach ($lessons as &$lesson) {
			$id = (int) $lesson['id'];
			if (isset($done[$id])) {
				$lesson['state'] = 'done';
			} elseif ($prev_done) {
				$lesson['state'] = $current_marked ? 'open' : 'current';
				$current_marked  = true;
			} else {
				$lesson['state'] = 'locked';
			}
			$prev_done = isset($done[$id]);
		}
		unset($lesson);
		return $lessons;
	}

	public static function progress_percent(array $lessons): int {
		if (!$lessons) {
			return 0;
		}
		$done = count(array_filter($lessons, static fn($l) => $l['state'] === 'done'));
		return (int) floor(($done / count($lessons)) * 100);
	}

	public static function active_subscription(int $user_id): ?array {
		return Rishe_DB::row(
			'SELECT s.*, p.title AS plan_title FROM rishe_subscriptions s
			 LEFT JOIN rishe_plans p ON p.id = s.plan_id
			 WHERE s.user_id = ? AND s.ends_at > ? ORDER BY s.ends_at DESC LIMIT 1',
			[$user_id, rishe_now()]
		);
	}

	public static function owns_course(int $user_id, int $course_id): bool {
		return (bool) Rishe_DB::value('SELECT 1 FROM rishe_purchases WHERE user_id = ? AND course_id = ?', [$user_id, $course_id]);
	}

	/**
	 * Full course access. Everything is free while payments are off.
	 */
	public static function has_course_access(int $user_id, array $course): bool {
		if (!Rishe_Settings::payments_active() || (int) $course['is_premium'] !== 1) {
			return true;
		}
		return self::active_subscription($user_id) !== null || self::owns_course($user_id, (int) $course['id']);
	}

	public static function can_open_lesson(int $user_id, array $course, array $lesson): bool {
		return (int) $lesson['is_free'] === 1 || self::has_course_access($user_id, $course);
	}

	public static function is_course_completed(int $course_id, int $user_id): bool {
		$lessons = self::lessons_with_state($course_id, $user_id);
		if (!$lessons) {
			return false;
		}
		foreach ($lessons as $lesson) {
			if ($lesson['state'] !== 'done') {
				return false;
			}
		}
		return true;
	}

	public static function exam_count(int $course_id): int {
		return (int) Rishe_DB::value("SELECT COUNT(*) FROM rishe_questions WHERE owner_type = 'exam' AND owner_id = ?", [$course_id]);
	}

	public static function best_exam_score(int $user_id, int $course_id): ?int {
		$score = Rishe_DB::value('SELECT MAX(score) FROM rishe_exam_results WHERE user_id = ? AND course_id = ?', [$user_id, $course_id]);
		return $score === null ? null : (int) $score;
	}

	public static function record_activity(int $user_id): void {
		Rishe_DB::query('INSERT IGNORE INTO rishe_activity (user_id, day) VALUES (?, ?)', [$user_id, date('Y-m-d')]);
	}

	/**
	 * Consecutive active days ending today (or yesterday if today has no activity yet).
	 */
	public static function streak(int $user_id): int {
		$days = Rishe_DB::rows('SELECT day FROM rishe_activity WHERE user_id = ? ORDER BY day DESC LIMIT 400', [$user_id]);
		if (!$days) {
			return 0;
		}
		$set = array_flip(array_column($days, 'day'));
		$cursor = new DateTime('today');
		if (!isset($set[$cursor->format('Y-m-d')])) {
			$cursor->modify('-1 day');
		}
		$count = 0;
		while (isset($set[$cursor->format('Y-m-d')])) {
			$count++;
			$cursor->modify('-1 day');
		}
		return $count;
	}

	public static function stats(int $user_id): array {
		return [
			'xp'     => (int) Rishe_DB::value('SELECT COALESCE(SUM(xp), 0) FROM rishe_progress WHERE user_id = ?', [$user_id]),
			'streak' => self::streak($user_id),
			'words'  => (int) Rishe_DB::value(
				'SELECT COUNT(*) FROM rishe_phrases ph INNER JOIN rishe_progress pr ON pr.lesson_id = ph.lesson_id WHERE pr.user_id = ?',
				[$user_id]
			),
		];
	}

	public static function decode_words(?string $json): array {
		$data = $json ? json_decode($json, true) : null;
		return is_array($data) ? $data : [];
	}

	/**
	 * "word = meaning" lines → JSON object.
	 */
	public static function words_from_text(string $text): string {
		$out = [];
		foreach (preg_split('/\R/u', $text) ?: [] as $line) {
			$parts = array_map('trim', explode('=', $line, 2));
			if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
				$out[mb_substr($parts[0], 0, 60)] = mb_substr($parts[1], 0, 120);
			}
		}
		return json_encode($out, JSON_UNESCAPED_UNICODE);
	}

	public static function words_to_text(?string $json): string {
		$lines = [];
		foreach (self::decode_words($json) as $word => $meaning) {
			$lines[] = $word . ' = ' . $meaning;
		}
		return implode("\n", $lines);
	}
}
