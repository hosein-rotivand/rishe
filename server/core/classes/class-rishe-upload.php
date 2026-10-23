<?php
/**
 * Validated audio uploads into /uploads/audio.
 */

declare(strict_types=1);

final class Rishe_Upload {

	/** Allowed extension => accepted MIME types. */
	private const AUDIO_TYPES = [
		'mp3'  => ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg'],
		'm4a'  => ['audio/mp4', 'audio/x-m4a', 'audio/m4a', 'video/mp4'],
		'ogg'  => ['audio/ogg', 'application/ogg', 'video/ogg'],
		'wav'  => ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'],
		'webm' => ['audio/webm', 'video/webm'],
	];

	/**
	 * Store an uploaded audio file. Returns the relative path, null when no file was sent.
	 *
	 * @throws RuntimeException on an invalid file.
	 */
	public static function audio(string $field): ?string {
		if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
			return null;
		}
		$file = $_FILES[$field];
		if (is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
			throw new RuntimeException('بارگذاری فایل صوتی انجام نشد. حجم فایل را بررسی کنید.');
		}
		$max = max(1, Rishe_Settings::int('upload_max_mb')) * 1024 * 1024;
		if ($file['size'] > $max) {
			throw new RuntimeException('حجم فایل صوتی بیشتر از حد مجاز است.');
		}
		if (!is_uploaded_file($file['tmp_name'])) {
			throw new RuntimeException('فایل نامعتبر است.');
		}
		$ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
		if (!isset(self::AUDIO_TYPES[$ext])) {
			throw new RuntimeException('فقط فایل‌های mp3، m4a، ogg، wav و webm مجاز هستند.');
		}
		$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
		if (!in_array($mime, self::AUDIO_TYPES[$ext], true) && $mime !== 'application/octet-stream') {
			throw new RuntimeException('نوع فایل صوتی معتبر نیست.');
		}

		$dir = RISHE_ROOT . '/uploads/audio/' . date('Y/m');
		if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
			throw new RuntimeException('پوشه‌ی آپلود قابل ساخت نیست.');
		}
		$name = bin2hex(random_bytes(16)) . '.' . $ext;
		if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
			throw new RuntimeException('ذخیره‌ی فایل انجام نشد.');
		}
		return 'audio/' . date('Y/m') . '/' . $name;
	}

	/**
	 * Delete a previously stored upload (only inside /uploads/audio).
	 */
	public static function remove(?string $path): void {
		if (!$path || !preg_match('#^audio/\d{4}/\d{2}/[a-f0-9]{32}\.[a-z0-9]{2,4}$#', $path)) {
			return;
		}
		$full = RISHE_ROOT . '/uploads/' . $path;
		if (is_file($full)) {
			unlink($full);
		}
	}
}
