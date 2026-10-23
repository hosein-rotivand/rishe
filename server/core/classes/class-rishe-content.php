<?php
/**
 * Ready-made content pack (content/curriculum.json + content/audio): preview and import.
 */

declare(strict_types=1);

final class Rishe_Content {

	public static function pack_path(): string {
		return RISHE_ROOT . '/content/curriculum.json';
	}

	public static function load(): ?array {
		$path = self::pack_path();
		if (!is_file($path)) {
			return null;
		}
		$data = json_decode((string) file_get_contents($path), true);
		return is_array($data) && !empty($data['courses']) ? $data : null;
	}

	/**
	 * Find an already imported copy of a pack course (same code + English title).
	 */
	public static function existing_course_id(array $course): ?int {
		$id = Rishe_DB::value('SELECT id FROM rishe_courses WHERE code = ? AND title_en = ? LIMIT 1', [$course['code'], $course['title_en']]);
		return $id === null ? null : (int) $id;
	}

	public static function counts(array $course): array {
		$lessons = 0;
		$phrases = 0;
		foreach ($course['chapters'] as $ch) {
			$lessons += count($ch['lessons']);
			foreach ($ch['lessons'] as $l) {
				$phrases += count($l['phrases']);
			}
		}
		return ['chapters' => count($course['chapters']), 'lessons' => $lessons, 'phrases' => $phrases];
	}

	/**
	 * Import the selected pack courses (by code). Already imported ones are skipped.
	 *
	 * @param string[] $codes
	 * @return string[] titles of imported courses
	 */
	public static function import(array $codes, bool $publish = true): array {
		$pack = self::load();
		if (!$pack) {
			throw new RuntimeException('بسته‌ی محتوا پیدا نشد.');
		}
		@set_time_limit(300);
		$imported = [];
		foreach ($pack['courses'] as $course) {
			if (!in_array($course['code'], $codes, true) || self::existing_course_id($course) !== null) {
				continue;
			}
			$copied = [];
			$pdo    = Rishe_DB::pdo();
			$pdo->beginTransaction();
			try {
				self::import_course($course, $publish, $copied);
				$pdo->commit();
				$imported[] = $course['title'];
			} catch (Throwable $e) {
				$pdo->rollBack();
				foreach ($copied as $path) {
					Rishe_Upload::remove($path);
				}
				throw $e instanceof RuntimeException ? $e : new RuntimeException('ورود محتوا انجام نشد: ' . $e->getMessage());
			}
		}
		return $imported;
	}

	private static function import_course(array $course, bool $publish, array &$copied): void {
		$course_id = Rishe_DB::insert('rishe_courses', [
			'title'       => $course['title'],
			'title_en'    => $course['title_en'],
			'code'        => $course['code'],
			'level'       => $course['level'],
			'description' => $course['description'],
			'hours'       => (int) $course['hours'],
			'color'       => in_array($course['color'], ['brand', 'amber', 'mute'], true) ? $course['color'] : 'brand',
			'is_premium'  => 0,
			'price'       => 0,
			'pass_score'  => 70,
			'sort'        => (int) $course['sort'],
			'status'      => $publish ? 'published' : 'draft',
			'created_at'  => rishe_now(),
		]);

		foreach ($course['chapters'] as $ci => $chapter) {
			$chapter_id = Rishe_DB::insert('rishe_chapters', ['course_id' => $course_id, 'title' => $chapter['title'], 'sort' => $ci + 1]);
			foreach ($chapter['lessons'] as $li => $lesson) {
				$lesson_id = Rishe_DB::insert('rishe_lessons', [
					'chapter_id' => $chapter_id,
					'title'      => $lesson['title'],
					'minutes'    => (int) $lesson['minutes'],
					'is_free'    => !empty($lesson['is_free']) ? 1 : 0,
					'sort'       => $li + 1,
					'status'     => 'published',
				]);
				foreach ($lesson['phrases'] as $pi => $p) {
					Rishe_DB::insert('rishe_phrases', [
						'lesson_id'          => $lesson_id,
						'en'                 => $p['en'],
						'fa'                 => $p['fa'],
						'ipa'                => $p['ipa'],
						'pron_fa'            => $p['pron_fa'],
						'words_json'         => json_encode((object) $p['words'], JSON_UNESCAPED_UNICODE),
						'audio_path'         => self::copy_audio($p['audio'] ?? null, $copied),
						'teacher_audio_path' => self::copy_audio($p['teacher_audio'] ?? null, $copied),
						'sort'               => $pi + 1,
					]);
				}
				self::insert_questions('lesson', $lesson_id, $lesson['questions'] ?? [], $copied);
			}
		}
		self::insert_questions('exam', $course_id, $course['exam'] ?? [], $copied);
	}

	private static function insert_questions(string $owner_type, int $owner_id, array $questions, array &$copied): void {
		foreach ($questions as $qi => $q) {
			Rishe_DB::insert('rishe_questions', [
				'owner_type'   => $owner_type,
				'owner_id'     => $owner_id,
				'type'         => $q['type'] === 'listen' ? 'listen' : 'meaning',
				'prompt'       => $q['prompt'],
				'say_text'     => $q['say'] ?? '',
				'audio_path'   => self::copy_audio($q['audio'] ?? null, $copied),
				'options_json' => json_encode(array_values($q['options']), JSON_UNESCAPED_UNICODE),
				'answer'       => (int) $q['answer'],
				'sort'         => $qi + 1,
			]);
		}
	}

	/**
	 * Copy a pack audio file into uploads so it can be edited or deleted like any upload.
	 */
	private static function copy_audio(?string $name, array &$copied): ?string {
		if (!$name || !preg_match('/^[a-f0-9]{20}\.mp3$/', $name)) {
			return null;
		}
		$source = RISHE_ROOT . '/content/audio/' . $name;
		if (!is_file($source)) {
			return null;
		}
		$rel = 'audio/' . date('Y/m');
		$dir = RISHE_ROOT . '/uploads/' . $rel;
		if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
			throw new RuntimeException('پوشه‌ی آپلود قابل ساخت نیست.');
		}
		$path = $rel . '/' . bin2hex(random_bytes(16)) . '.mp3';
		if (!copy($source, RISHE_ROOT . '/uploads/' . $path)) {
			throw new RuntimeException('کپی فایل صوتی انجام نشد.');
		}
		$copied[] = $path;
		return $path;
	}
}
