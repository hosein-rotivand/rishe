<?php
/**
 * Copy to config.php (install.php does this for you).
 */

return [
	'db'       => [
		'host' => 'localhost',
		'port' => 3306,
		'name' => 'rishe',
		'user' => 'rishe_user',
		'pass' => 'change-me',
	],
	// Public URL of this folder, without trailing slash
	'base_url' => 'https://example.com/rishe',
	// Random secret, used for hashing OTP codes
	'app_key'  => 'change-me-to-a-long-random-string',
	'debug'    => false,
];
