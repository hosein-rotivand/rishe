<?php
/**
 * Admin login screen.
 *
 * @var string $error
 */

declare(strict_types=1);

$app = Rishe_Settings::get('app_name');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title>ورود به پنل <?php echo rishe_e($app); ?></title>
	<link rel="stylesheet" href="<?php echo rishe_e(rishe_url('assets/admin.css?v=' . RISHE_VERSION)); ?>">
</head>
<body class="rishe-auth">
	<form method="post" class="rishe-auth-card" autocomplete="on">
		<?php echo Rishe_Csrf::field(); ?>
		<div class="rishe-brand">
			<span class="rishe-brand-mark"><?php echo Rishe_Admin::icon('root'); ?></span>
			<span><b><?php echo rishe_e($app); ?></b><small>پنل مدیریت</small></span>
		</div>
		<?php if ($error !== '') : ?><div class="rishe-flash is-bad"><?php echo rishe_e($error); ?></div><?php endif; ?>
		<label class="rishe-field"><span>ایمیل</span><input class="rishe-input ltr" type="email" name="email" id="email" required autofocus></label>
		<label class="rishe-field"><span>رمز عبور</span><input class="rishe-input ltr" type="password" name="password" id="password" required></label>
		<button class="rishe-btn is-primary is-block" type="submit">ورود</button>
	</form>
</body>
</html>
