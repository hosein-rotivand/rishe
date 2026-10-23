<?php
/**
 * Admin panel front controller: admin/?p=<page>
 */

declare(strict_types=1);

require dirname(__DIR__) . '/core/bootstrap.php';

Rishe_Admin_Auth::start_session();

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$page = isset($_GET['p']) && is_string($_GET['p']) ? $_GET['p'] : 'dashboard';

if ($page === 'logout') {
	Rishe_Admin::guard_post();
	if (Rishe_Admin::is_post()) {
		Rishe_Admin_Auth::logout();
	}
	rishe_redirect(Rishe_Admin::url('login'));
}

if ($page === 'login') {
	if (Rishe_Admin_Auth::user()) {
		Rishe_Admin::redirect('dashboard');
	}
	$error = '';
	if (Rishe_Admin::is_post()) {
		Rishe_Admin::guard_post();
		$email = rishe_str($_POST, 'email', 190);
		$pass  = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
		if (Rishe_Admin_Auth::attempt($email, $pass)) {
			Rishe_Admin::redirect('dashboard');
		}
		$error = Rishe_Admin_Auth::is_locked()
			? 'تعداد تلاش‌ها زیاد بود. ۱۵ دقیقه‌ی دیگر دوباره امتحان کنید.'
			: 'ایمیل یا رمز عبور اشتباه است.';
	}
	require RISHE_CORE . '/admin/views/login.php';
	exit;
}

$admin = Rishe_Admin_Auth::user();
if (!$admin) {
	Rishe_Admin::redirect('login');
}

if (!isset(Rishe_Admin::PAGES[$page])) {
	$page = 'dashboard';
}

Rishe_Admin::guard_post();

$title  = Rishe_Admin::PAGES[$page][0];
$crumbs = [];

ob_start();
require RISHE_CORE . '/admin/pages/' . $page . '.php';
$content = (string) ob_get_clean();

Rishe_Admin::layout($page, $title, $content, $admin, $crumbs);
