<?php
/**
 * Settings: brand, certificates, payments (Zibal), SMS (Kavenegar), uploads.
 */

declare(strict_types=1);

if (Rishe_Admin::is_post() && Rishe_Admin::action() === 'save_settings') {
	$text = [
		'app_name'          => 60,
		'app_name_en'       => 60,
		'app_tagline'       => 120,
		'cert_signer_name'  => 120,
		'cert_signer_title' => 120,
		'zibal_merchant'    => 100,
		'sms_username'      => 100,
		'sms_password'      => 200,
		'sms_body_id'       => 20,
	];
	foreach ($text as $key => $max) {
		Rishe_Settings::set($key, rishe_str($_POST, $key, $max));
	}
	$prefix = preg_replace('/[^A-Z]/', '', strtoupper(rishe_str($_POST, 'cert_prefix', 6))) ?: 'RS';
	Rishe_Settings::set('cert_prefix', $prefix);
	Rishe_Settings::set('certificate_fee', (string) max(0, rishe_int($_POST, 'certificate_fee')));
	Rishe_Settings::set('upload_max_mb', (string) max(1, min(100, rishe_int($_POST, 'upload_max_mb', 10))));
	Rishe_Settings::set('payments_enabled', (string) rishe_bool($_POST, 'payments_enabled'));
	Rishe_Settings::set('sms_enabled', (string) rishe_bool($_POST, 'sms_enabled'));

	Rishe_Admin::flash('تنظیمات ذخیره شد.');
	if (rishe_bool($_POST, 'payments_enabled') && rishe_str($_POST, 'zibal_merchant', 100) === '') {
		Rishe_Admin::flash('پرداخت روشن است اما کلید مرچنت خالی است؛ تا وارد نشود پرداخت غیرفعال می‌ماند.', 'warn');
	}
	Rishe_Admin::redirect('settings');
}

$s          = Rishe_Settings::all();
$upload_cap = ini_get('upload_max_filesize');
?>
<form method="post" class="rishe-form" autocomplete="off">
	<?php echo Rishe_Csrf::field(); ?>
	<input type="hidden" name="_action" value="save_settings">

	<div class="rishe-grid is-2" style="align-items:start">
		<section class="rishe-card rishe-form">
			<h2 style="margin:0">برند</h2>
			<div class="rishe-grid is-2">
				<label class="rishe-field"><span>نام اپ</span><input class="rishe-input" name="app_name" id="app_name" value="<?php echo rishe_e($s['app_name']); ?>"></label>
				<label class="rishe-field"><span>نام انگلیسی</span><input class="rishe-input ltr" name="app_name_en" id="app_name_en" value="<?php echo rishe_e($s['app_name_en']); ?>"></label>
			</div>
			<label class="rishe-field"><span>شعار</span><input class="rishe-input" name="app_tagline" id="app_tagline" value="<?php echo rishe_e($s['app_tagline']); ?>"></label>
		</section>

		<section class="rishe-card rishe-form">
			<h2 style="margin:0">مدرک</h2>
			<div class="rishe-grid is-2">
				<label class="rishe-field"><span>نام امضاکننده</span><input class="rishe-input" name="cert_signer_name" id="cert_signer_name" value="<?php echo rishe_e($s['cert_signer_name']); ?>" placeholder="مثلاً حسین روتیوند"></label>
				<label class="rishe-field"><span>سمت امضاکننده</span><input class="rishe-input" name="cert_signer_title" id="cert_signer_title" value="<?php echo rishe_e($s['cert_signer_title']); ?>"></label>
				<label class="rishe-field"><span>پیشوند کد مدرک</span><input class="rishe-input ltr" name="cert_prefix" id="cert_prefix" maxlength="6" value="<?php echo rishe_e($s['cert_prefix']); ?>"><small>مثلاً RS ← RS-1405-04821</small></label>
				<label class="rishe-field"><span>هزینه‌ی صدور (تومان)</span><input class="rishe-input" type="number" min="0" name="certificate_fee" id="certificate_fee" value="<?php echo rishe_e($s['certificate_fee']); ?>"><small>۰ یعنی رایگان. فقط وقتی پرداخت روشن است گرفته می‌شود.</small></label>
			</div>
		</section>

		<section class="rishe-card rishe-form">
			<h2 style="margin:0">پرداخت (درگاه زیبال)</h2>
			<label class="rishe-check"><input type="checkbox" name="payments_enabled" id="payments_enabled" value="1" <?php echo $s['payments_enabled'] === '1' ? 'checked' : ''; ?>>فعال‌سازی پرداخت و محتوای ویژه</label>
			<p class="rishe-help">تا وقتی خاموش است، همه‌ی دوره‌ها رایگان‌اند، طرح‌های اشتراک نمایش داده نمی‌شوند و صدور مدرک هزینه ندارد.</p>
			<label class="rishe-field"><span>کلید مرچنت (merchant)</span><input class="rishe-input ltr" name="zibal_merchant" id="zibal_merchant" value="<?php echo rishe_e($s['zibal_merchant']); ?>" placeholder="xxxxxxxxxxxxxxxxxxxxxxxx"><small>از پنل زیبال بگیرید. برای تست، مقدار <b class="ltr">zibal</b> درگاه آزمایشی را فعال می‌کند.</small></label>
			<p class="rishe-help">آدرس بازگشت: <span class="ltr"><?php echo rishe_e(rishe_url('pay/callback.php')); ?></span></p>
		</section>

		<section class="rishe-card rishe-form">
			<h2 style="margin:0">پیامک (ملی پیامک)</h2>
			<label class="rishe-check"><input type="checkbox" name="sms_enabled" id="sms_enabled" value="1" <?php echo $s['sms_enabled'] === '1' ? 'checked' : ''; ?>>ورود با کد یک‌بارمصرف و تأیید شماره در عضویت</label>
			<div class="rishe-grid is-2">
				<label class="rishe-field"><span>نام کاربری</span><input class="rishe-input ltr" name="sms_username" id="sms_username" value="<?php echo rishe_e($s['sms_username']); ?>" autocomplete="off"></label>
				<label class="rishe-field"><span>رمز / کلید API</span><input class="rishe-input ltr" type="password" name="sms_password" id="sms_password" value="<?php echo rishe_e($s['sms_password']); ?>" autocomplete="new-password"></label>
			</div>
			<label class="rishe-field"><span>کد الگو (bodyId)</span><input class="rishe-input ltr" name="sms_body_id" id="sms_body_id" inputmode="numeric" value="<?php echo rishe_e($s['sms_body_id']); ?>" placeholder="123456"><small>در پنل ملی پیامک، از بخش «وب‌سرویس خدماتی» یک الگو مثل «کد ورود شما به ریشه: {0}» بسازید و بعد از تأیید، کد آن را اینجا وارد کنید. کد پیامک جای <span class="ltr">{0}</span> قرار می‌گیرد.</small></label>
		</section>

		<section class="rishe-card rishe-form">
			<h2 style="margin:0">آپلود</h2>
			<label class="rishe-field"><span>حداکثر حجم فایل صوتی (مگابایت)</span><input class="rishe-input" type="number" min="1" max="100" name="upload_max_mb" id="upload_max_mb" value="<?php echo rishe_e($s['upload_max_mb']); ?>"><small>محدودیت هاست شما: <span class="ltr"><?php echo rishe_e((string) $upload_cap); ?></span></small></label>
		</section>
	</div>

	<div class="rishe-form-foot"><button class="rishe-btn is-primary" type="submit">ذخیره‌ی تنظیمات</button></div>
</form>
