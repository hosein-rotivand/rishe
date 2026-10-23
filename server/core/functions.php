<?php
/**
 * Global helper functions.
 */

declare(strict_types=1);

/**
 * Escape a value for HTML output.
 */
function rishe_e($value): string {
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Build an absolute URL inside the installation.
 */
function rishe_url(string $path = ''): string {
	$base = rtrim((string) Rishe_Config::get('base_url', ''), '/');
	return $base . '/' . ltrim($path, '/');
}

/**
 * Public URL of an uploaded file (relative path stored in DB).
 */
function rishe_upload_url(?string $path): ?string {
	if ($path === null || $path === '') {
		return null;
	}
	return rishe_url('uploads/' . ltrim($path, '/'));
}

function rishe_redirect(string $url): void {
	header('Location: ' . $url);
	exit;
}

function rishe_now(): string {
	return date('Y-m-d H:i:s');
}

/**
 * Convert Persian / Arabic digits to Latin digits.
 */
function rishe_latin_digits(string $value): string {
	$fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
	$en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
	return str_replace($fa, $en, $value);
}

/**
 * Convert Latin digits to Persian digits for display.
 */
function rishe_fa_digits($value): string {
	return str_replace(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], (string) $value);
}

/**
 * Normalize and validate an Iranian mobile number. Returns null when invalid.
 */
function rishe_normalize_mobile(string $mobile): ?string {
	$mobile = preg_replace('/\D+/', '', rishe_latin_digits(trim($mobile)));
	if (strpos($mobile, '98') === 0 && strlen($mobile) === 12) {
		$mobile = '0' . substr($mobile, 2);
	}
	if (strlen($mobile) === 10 && $mobile[0] === '9') {
		$mobile = '0' . $mobile;
	}
	return preg_match('/^09\d{9}$/', $mobile) ? $mobile : null;
}

/**
 * Format a Toman amount: 120000 -> ۱۲۰٬۰۰۰ تومان
 */
function rishe_money(int $toman): string {
	return rishe_fa_digits(number_format($toman, 0, '.', '٬')) . ' تومان';
}

/**
 * Gregorian to Jalali conversion.
 *
 * @return int[] [year, month, day]
 */
function rishe_gregorian_to_jalali(int $gy, int $gm, int $gd): array {
	$g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
	$gy2   = ($gm > 2) ? ($gy + 1) : $gy;
	$days  = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
	$jy    = -1595 + (33 * intdiv($days, 12053));
	$days %= 12053;
	$jy   += 4 * intdiv($days, 1461);
	$days %= 1461;
	if ($days > 365) {
		$jy  += intdiv($days - 1, 365);
		$days = ($days - 1) % 365;
	}
	if ($days < 186) {
		$jm = 1 + intdiv($days, 31);
		$jd = 1 + ($days % 31);
	} else {
		$jm = 7 + intdiv($days - 186, 30);
		$jd = 1 + (($days - 186) % 30);
	}
	return [$jy, $jm, $jd];
}

/**
 * Format a datetime string as a Persian Jalali date, e.g. «۳۰ مهر ۱۴۰۵».
 */
function rishe_jalali(?string $datetime, bool $with_time = false): string {
	if (!$datetime) {
		return '—';
	}
	$ts = strtotime($datetime);
	if ($ts === false) {
		return '—';
	}
	[$jy, $jm, $jd] = rishe_gregorian_to_jalali((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
	$months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
	$out    = $jd . ' ' . $months[$jm - 1] . ' ' . $jy;
	if ($with_time) {
		$out .= '، ' . date('H:i', $ts);
	}
	return rishe_fa_digits($out);
}

/**
 * Short numeric Jalali date, e.g. 1405-07-30 (used in certificate codes).
 */
function rishe_jalali_year(?string $datetime = null): int {
	$ts = $datetime ? strtotime($datetime) : time();
	return rishe_gregorian_to_jalali((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts))[0];
}

/**
 * Read a trimmed string from an array (POST / JSON body).
 */
function rishe_str(array $source, string $key, int $max = 500): string {
	$value = isset($source[$key]) && is_scalar($source[$key]) ? trim((string) $source[$key]) : '';
	$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
	return mb_substr($value, 0, $max);
}

function rishe_int(array $source, string $key, int $default = 0): int {
	if (!isset($source[$key]) || !is_scalar($source[$key])) {
		return $default;
	}
	$value = rishe_latin_digits((string) $source[$key]);
	return is_numeric($value) ? (int) $value : $default;
}

function rishe_bool(array $source, string $key): int {
	return !empty($source[$key]) ? 1 : 0;
}
