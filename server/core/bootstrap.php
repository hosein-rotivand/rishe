<?php
/**
 * Rishe bootstrap: config, autoloader, helpers.
 */

declare(strict_types=1);

define('RISHE_VERSION', '1.0.0');
define('RISHE_ROOT', dirname(__DIR__));
define('RISHE_CORE', __DIR__);

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Tehran');

spl_autoload_register(static function (string $class): void {
	if (strpos($class, 'Rishe_') !== 0) {
		return;
	}
	$file = RISHE_CORE . '/classes/class-' . strtolower(str_replace('_', '-', $class)) . '.php';
	if (is_file($file)) {
		require $file;
	}
});

require RISHE_CORE . '/functions.php';

if (is_file(RISHE_ROOT . '/config.php')) {
	Rishe_Config::load(require RISHE_ROOT . '/config.php');
} elseif (!defined('RISHE_INSTALLING')) {
	http_response_code(503);
	header('Content-Type: text/html; charset=utf-8');
	echo '<!doctype html><meta charset="utf-8"><body style="font-family:Tahoma;direction:rtl;padding:40px">'
		. 'برنامه هنوز نصب نشده است. فایل <code>install.php</code> را در مرورگر باز کنید.</body>';
	exit;
}

if (Rishe_Config::get('debug')) {
	ini_set('display_errors', '1');
	error_reporting(E_ALL);
} else {
	ini_set('display_errors', '0');
}
