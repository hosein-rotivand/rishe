<?php
/**
 * Admin's own account: name, email, password.
 *
 * @var array $admin
 */

declare(strict_types=1);

$error = '';
if (Rishe_Admin::is_post() && Rishe_Admin::action() === 'save_account') {
	$row     = Rishe_DB::row('SELECT * FROM rishe_admins WHERE id = ?', [$admin['id']]);
	$name    = rishe_str($_POST, 'name', 120);
	$email   = mb_strtolower(rishe_str($_POST, 'email', 190));
	$current = isset($_POST['current_password']) && is_string($_POST['current_password']) ? $_POST['current_password'] : '';
	$new     = isset($_POST['new_password']) && is_string($_POST['new_password']) ? $_POST['new_password'] : '';

	if (!password_verify($current, $row['password_hash'])) {
		$error = 'رمز فعلی اشتباه است.';
	} elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$error = 'نام و ایمیل معتبر وارد کنید.';
	} elseif ($new !== '' && mb_strlen($new) < 8) {
		$error = 'رمز جدید باید حداقل ۸ کاراکتر باشد.';
	} elseif (Rishe_DB::value('SELECT 1 FROM rishe_admins WHERE email = ? AND id <> ?', [$email, $admin['id']])) {
		$error = 'این ایمیل برای مدیر دیگری ثبت شده است.';
	} else {
		$data = ['name' => $name, 'email' => $email];
		if ($new !== '') {
			$data['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
		}
		Rishe_DB::update('rishe_admins', $data, ['id' => $admin['id']]);
		if ($new !== '') {
			session_regenerate_id(true);
		}
		Rishe_Admin::flash('حساب ذخیره شد.');
		Rishe_Admin::redirect('account');
	}
}
?>
<?php if ($error !== '') : ?><div class="rishe-flash is-bad"><?php echo rishe_e($error); ?></div><?php endif; ?>
<form method="post" class="rishe-card rishe-form" style="max-width:520px" autocomplete="off">
	<?php echo Rishe_Csrf::field(); ?>
	<input type="hidden" name="_action" value="save_account">
	<label class="rishe-field"><span>نام</span><input class="rishe-input" name="name" id="name" required value="<?php echo rishe_e($admin['name']); ?>"></label>
	<label class="rishe-field"><span>ایمیل ورود</span><input class="rishe-input ltr" type="email" name="email" id="email" required value="<?php echo rishe_e($admin['email']); ?>"></label>
	<label class="rishe-field"><span>رمز جدید</span><input class="rishe-input ltr" type="password" name="new_password" id="new_password" autocomplete="new-password"><small>خالی بگذارید تا تغییر نکند. حداقل ۸ کاراکتر.</small></label>
	<label class="rishe-field"><span>رمز فعلی</span><input class="rishe-input ltr" type="password" name="current_password" id="current_password" required autocomplete="current-password"><small>برای ذخیره‌ی هر تغییری لازم است.</small></label>
	<div class="rishe-form-foot"><button class="rishe-btn is-primary" type="submit">ذخیره</button></div>
</form>
