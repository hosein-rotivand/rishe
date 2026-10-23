<?php
/**
 * Zibal callback: verify the payment, then tell the user to return to the app.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/core/bootstrap.php';

$track_id = isset($_GET['trackId']) && is_string($_GET['trackId']) ? preg_replace('/[^0-9A-Za-z]/', '', $_GET['trackId']) : '';
$order_id = isset($_GET['orderId']) && is_string($_GET['orderId']) ? (int) $_GET['orderId'] : 0;
$success  = isset($_GET['success']) && (string) $_GET['success'] === '1';

$payment = null;
$error   = '';
try {
	if ($track_id === '' || $order_id <= 0) {
		throw new RuntimeException('اطلاعات بازگشت از درگاه ناقص است.');
	}
	$payment = Rishe_Payments::callback($track_id, $order_id, $success);
} catch (Throwable $e) {
	$error = $e instanceof RuntimeException ? $e->getMessage() : 'بررسی پرداخت انجام نشد. اگر مبلغ کسر شده، با پشتیبانی تماس بگیرید.';
	if (!$e instanceof RuntimeException) {
		error_log('Rishe callback: ' . $e->getMessage());
	}
}

$paid  = $payment && $payment['status'] === 'paid';
$title = $paid ? 'پرداخت موفق بود' : 'پرداخت انجام نشد';
$app   = Rishe_Settings::get('app_name');

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo rishe_e($title . ' | ' . $app); ?></title>
	<link rel="stylesheet" href="<?php echo rishe_e(rishe_url('assets/public.css')); ?>">
</head>
<body>
	<main class="rishe-public">
		<div class="rishe-public-card">
			<span class="rishe-public-badge <?php echo $paid ? 'is-ok' : 'is-bad'; ?>"><?php echo $paid ? '✓' : '!'; ?></span>
			<h1><?php echo rishe_e($title); ?></h1>
			<?php if ($paid) : ?>
				<p><?php echo rishe_e(Rishe_Payments::TYPE_LABELS[$payment['type']] ?? ''); ?> برای حساب شما فعال شد.</p>
				<dl>
					<dt>مبلغ</dt><dd><?php echo rishe_e(rishe_money((int) $payment['amount'])); ?></dd>
					<dt>کد پیگیری</dt><dd class="ltr"><?php echo rishe_e($payment['ref_number'] ?: $payment['track_id']); ?></dd>
				</dl>
			<?php else : ?>
				<p><?php echo rishe_e($error !== '' ? $error : 'پرداخت لغو شد یا تأیید نشد. اگر مبلغی کسر شده باشد، طی ۷۲ ساعت به حساب شما برمی‌گردد.'); ?></p>
			<?php endif; ?>
			<p class="rishe-public-hint">این صفحه را ببندید و به اپ <?php echo rishe_e($app); ?> برگردید؛ وضعیت خودکار به‌روز می‌شود.</p>
		</div>
	</main>
</body>
</html>
