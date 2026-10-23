<?php
/**
 * Admin layout.
 *
 * @var string $page
 * @var string $title
 * @var string $content
 * @var array  $admin
 * @var array  $crumbs
 * @var string $app
 * @var int    $pending
 * @var string $active_menu
 */

declare(strict_types=1);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo rishe_e($title . ' | پنل ' . $app); ?></title>
	<link rel="stylesheet" href="<?php echo rishe_e(rishe_url('assets/admin.css?v=' . RISHE_VERSION)); ?>">
</head>
<body class="rishe-admin">
	<aside class="rishe-side" id="rishe-side">
		<a class="rishe-brand" href="<?php echo rishe_e(Rishe_Admin::url('dashboard')); ?>">
			<span class="rishe-brand-mark"><?php echo Rishe_Admin::icon('root'); ?></span>
			<span><b><?php echo rishe_e($app); ?></b><small>پنل مدیریت</small></span>
		</a>
		<nav class="rishe-menu">
			<?php foreach (Rishe_Admin::PAGES as $slug => [$label, $icon, $in_menu]) : ?>
				<?php if (!$in_menu) { continue; } ?>
				<a href="<?php echo rishe_e(Rishe_Admin::url($slug)); ?>" class="<?php echo $active_menu === $slug ? 'is-active' : ''; ?>">
					<?php echo Rishe_Admin::icon($icon); ?><span><?php echo rishe_e($label); ?></span>
					<?php if ($slug === 'certificates' && $pending > 0) : ?><em class="rishe-count"><?php echo rishe_e(rishe_fa_digits($pending)); ?></em><?php endif; ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<div class="rishe-side-foot">
			<a href="<?php echo rishe_e(Rishe_Admin::url('account')); ?>" class="rishe-me"><span class="rishe-avatar"><?php echo rishe_e(mb_substr($admin['name'], 0, 1)); ?></span><span><b><?php echo rishe_e($admin['name']); ?></b><small class="ltr"><?php echo rishe_e($admin['email']); ?></small></span></a>
			<form method="post" action="<?php echo rishe_e(Rishe_Admin::url('logout')); ?>"><?php echo Rishe_Csrf::field(); ?><button class="rishe-icon-btn" title="خروج" aria-label="خروج"><?php echo Rishe_Admin::icon('logout'); ?></button></form>
		</div>
	</aside>

	<div class="rishe-main">
		<header class="rishe-top">
			<button class="rishe-icon-btn rishe-burger" type="button" aria-label="منو" data-toggle-side>☰</button>
			<div>
				<?php if ($crumbs) : ?>
					<nav class="rishe-crumbs">
						<?php foreach ($crumbs as $label => $href) : ?>
							<a href="<?php echo rishe_e($href); ?>"><?php echo rishe_e($label); ?></a><span>/</span>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>
				<h1><?php echo rishe_e($title); ?></h1>
			</div>
		</header>

		<?php foreach (Rishe_Admin::flashes() as $flash) : ?>
			<div class="rishe-flash is-<?php echo rishe_e($flash['type']); ?>"><?php echo rishe_e($flash['message']); ?></div>
		<?php endforeach; ?>

		<main class="rishe-content"><?php echo $content; ?></main>
	</div>

	<script src="<?php echo rishe_e(rishe_url('assets/admin.js?v=' . RISHE_VERSION)); ?>"></script>
</body>
</html>
