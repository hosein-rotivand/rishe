<?php
/**
 * CSRF tokens for admin forms (session based).
 */

declare(strict_types=1);

final class Rishe_Csrf {

	public static function token(): string {
		if (empty($_SESSION['rishe_csrf'])) {
			$_SESSION['rishe_csrf'] = bin2hex(random_bytes(32));
		}
		return $_SESSION['rishe_csrf'];
	}

	public static function field(): string {
		return '<input type="hidden" name="_csrf" value="' . rishe_e(self::token()) . '">';
	}

	public static function verify(): bool {
		$sent = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : '';
		return $sent !== '' && !empty($_SESSION['rishe_csrf']) && hash_equals($_SESSION['rishe_csrf'], $sent);
	}
}
