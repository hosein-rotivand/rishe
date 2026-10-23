<?php
/**
 * Public certificate verification page: /verify/?code=RS-1405-00000
 */

declare(strict_types=1);

require dirname(__DIR__) . '/core/bootstrap.php';

$code = isset($_GET['code']) && is_string($_GET['code']) ? strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $_GET['code'])) : '';
$cert = $code !== '' ? Rishe_Certificates::by_code($code) : null;
$ok   = $cert && $cert['status'] === 'approved';
$app  = Rishe_Settings::get('app_name');

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<title>استعلام مدرک | <?php echo rishe_e($app); ?></title>
	<link rel="stylesheet" href="<?php echo rishe_e(rishe_url('assets/public.css')); ?>">
	<link rel="stylesheet" href="<?php echo rishe_e(rishe_url('assets/certificate.css')); ?>">
</head>
<body>
	<main class="rishe-public rishe-public-wide">
		<form class="rishe-public-search" method="get">
			<label for="code">کد مدرک</label>
			<input id="code" name="code" class="ltr" placeholder="RS-1405-00000" value="<?php echo rishe_e($code); ?>">
			<button type="submit">استعلام</button>
		</form>

		<?php if ($code !== '' && $ok) : ?>
			<div class="rishe-public-status is-ok">✓ این مدرک معتبر است و توسط <?php echo rishe_e($app); ?> صادر شده است.</div>
			<?php echo Rishe_Certificates::render(Rishe_Certificates::public_data($cert)); ?>
		<?php elseif ($code !== '') : ?>
			<div class="rishe-public-status is-bad">مدرکی با این کد پیدا نشد یا هنوز تأیید نشده است.</div>
		<?php endif; ?>
	</main>
</body>
</html>
