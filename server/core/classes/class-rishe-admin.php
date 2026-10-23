<?php
/**
 * Admin panel helpers: routing URLs, flash messages, POST guard, layout.
 */

declare(strict_types=1);

final class Rishe_Admin {

	/** page slug => [menu label, icon, show in menu] */
	public const PAGES = [
		'dashboard'    => ['داشبورد', 'home', true],
		'courses'      => ['دوره‌ها', 'book', true],
		'content'      => ['دوره‌های آماده', 'library', true],
		'course'       => ['ویرایش دوره', 'book', false],
		'chapter'      => ['فصل', 'book', false],
		'lesson'       => ['مرحله', 'book', false],
		'phrase'       => ['عبارت', 'book', false],
		'question'     => ['سؤال', 'book', false],
		'exam'         => ['آزمون پایانی', 'book', false],
		'users'        => ['کاربران', 'users', true],
		'certificates' => ['مدارک', 'award', true],
		'plans'        => ['طرح‌های اشتراک', 'card', true],
		'payments'     => ['پرداخت‌ها', 'receipt', true],
		'settings'     => ['تنظیمات', 'gear', true],
		'account'      => ['حساب مدیر', 'user', false],
	];

	public const ICONS = [
		'home'    => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/>',
		'book'    => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20v3H6.5"/>',
		'users'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c1-3.5 3.5-5 6.5-5s5.5 1.5 6.5 5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 15c2 .6 3 2 3.5 5"/>',
		'award'   => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-3 5 3-1.5-7"/>',
		'card'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>',
		'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
		'gear'    => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
		'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
		'logout'  => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
		'plus'    => '<path d="M12 5v14M5 12h14"/>',
		'root'    => '<path d="M12 3v8M12 11c0 4-4 4-4 8M12 11c0 4 4 4 4 8M12 11v10"/>',
		'library' => '<path d="M4 4h4v16H4zM10 4h4v16h-4z"/><path d="m16 5 3.8-1 3 15.5-3.8 1z"/>',
		'audio'   => '<path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4z"/><path d="M15.5 9a4 4 0 0 1 0 6"/>',
	];

	public static function icon(string $name): string {
		return '<svg class="rishe-icon" viewBox="0 0 24 24" aria-hidden="true">' . (self::ICONS[$name] ?? '') . '</svg>';
	}

	public static function url(string $page, array $params = []): string {
		return rishe_url('admin/') . '?' . http_build_query(array_merge(['p' => $page], $params));
	}

	public static function redirect(string $page, array $params = []): void {
		rishe_redirect(self::url($page, $params));
	}

	public static function flash(string $message, string $type = 'ok'): void {
		$_SESSION['rishe_flash'][] = ['type' => $type, 'message' => $message];
	}

	public static function flashes(): array {
		$items = $_SESSION['rishe_flash'] ?? [];
		unset($_SESSION['rishe_flash']);
		return $items;
	}

	public static function is_post(): bool {
		return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
	}

	/**
	 * Stop any POST without a valid CSRF token.
	 */
	public static function guard_post(): void {
		if (!self::is_post()) {
			return;
		}
		if (!Rishe_Csrf::verify()) {
			http_response_code(400);
			exit('درخواست نامعتبر است. صفحه را دوباره باز کنید.');
		}
	}

	public static function action(): string {
		return isset($_POST['_action']) && is_string($_POST['_action']) ? $_POST['_action'] : '';
	}

	public static function id(string $key = 'id'): int {
		$value = $_GET[$key] ?? $_POST[$key] ?? 0;
		return is_scalar($value) ? max(0, (int) $value) : 0;
	}

	/**
	 * Inline confirm form button for destructive actions.
	 */
	public static function delete_button(string $action, array $fields, string $label = 'حذف', string $confirm = 'از حذف این مورد مطمئن هستید؟'): string {
		$html = '<form method="post" class="rishe-inline" data-confirm="' . rishe_e($confirm) . '">' . Rishe_Csrf::field()
			. '<input type="hidden" name="_action" value="' . rishe_e($action) . '">';
		foreach ($fields as $name => $value) {
			$html .= '<input type="hidden" name="' . rishe_e($name) . '" value="' . rishe_e($value) . '">';
		}
		return $html . '<button type="submit" class="rishe-link is-danger">' . rishe_e($label) . '</button></form>';
	}

	public static function status_pill(string $status): string {
		$map = [
			'published'       => ['منتشر شده', 'ok'],
			'draft'           => ['پیش‌نویس', 'mute'],
			'active'          => ['فعال', 'ok'],
			'inactive'        => ['غیرفعال', 'mute'],
			'blocked'         => ['مسدود', 'bad'],
			'pending'         => ['در انتظار', 'warn'],
			'pending_payment' => ['در انتظار پرداخت', 'mute'],
			'approved'        => ['تأیید شده', 'ok'],
			'rejected'        => ['رد شده', 'bad'],
			'paid'            => ['موفق', 'ok'],
			'failed'          => ['ناموفق', 'bad'],
		];
		[$label, $tone] = $map[$status] ?? [$status, 'mute'];
		return '<span class="rishe-pill is-' . $tone . '">' . rishe_e($label) . '</span>';
	}

	/**
	 * Delete a lesson with its phrases, questions, audio files and progress rows.
	 */
	public static function delete_lesson(int $lesson_id): void {
		foreach (Rishe_DB::rows('SELECT audio_path, teacher_audio_path FROM rishe_phrases WHERE lesson_id = ?', [$lesson_id]) as $p) {
			Rishe_Upload::remove($p['audio_path']);
			Rishe_Upload::remove($p['teacher_audio_path']);
		}
		foreach (Rishe_DB::rows("SELECT audio_path FROM rishe_questions WHERE owner_type = 'lesson' AND owner_id = ?", [$lesson_id]) as $q) {
			Rishe_Upload::remove($q['audio_path']);
		}
		Rishe_DB::delete('rishe_phrases', ['lesson_id' => $lesson_id]);
		Rishe_DB::delete('rishe_questions', ['owner_type' => 'lesson', 'owner_id' => $lesson_id]);
		Rishe_DB::delete('rishe_progress', ['lesson_id' => $lesson_id]);
		Rishe_DB::delete('rishe_lessons', ['id' => $lesson_id]);
	}

	/**
	 * Delete a course with all its chapters, lessons, exam questions and audio files.
	 */
	public static function delete_course(int $course_id): void {
		foreach (Rishe_DB::rows('SELECT l.id FROM rishe_lessons l INNER JOIN rishe_chapters c ON c.id = l.chapter_id WHERE c.course_id = ?', [$course_id]) as $l) {
			self::delete_lesson((int) $l['id']);
		}
		foreach (Rishe_DB::rows("SELECT audio_path FROM rishe_questions WHERE owner_type = 'exam' AND owner_id = ?", [$course_id]) as $q) {
			Rishe_Upload::remove($q['audio_path']);
		}
		Rishe_DB::delete('rishe_questions', ['owner_type' => 'exam', 'owner_id' => $course_id]);
		Rishe_DB::delete('rishe_exam_results', ['course_id' => $course_id]);
		Rishe_DB::delete('rishe_chapters', ['course_id' => $course_id]);
		Rishe_DB::delete('rishe_courses', ['id' => $course_id]);
	}

	public static function layout(string $page, string $title, string $content, array $admin, array $crumbs = []): void {
		$app = Rishe_Settings::get('app_name');
		$pending = (int) Rishe_DB::value("SELECT COUNT(*) FROM rishe_certificates WHERE status = 'pending'");
		$active_menu = in_array($page, ['course', 'chapter', 'lesson', 'phrase', 'question', 'exam'], true) ? 'courses' : $page;
		require RISHE_CORE . '/admin/views/layout.php';
	}
}
