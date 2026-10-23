<?php
/**
 * Read-only access to config.php values using dot notation.
 */

declare(strict_types=1);

final class Rishe_Config {

	/** @var array<string, mixed> */
	private static array $data = [];

	public static function load(array $data): void {
		self::$data = $data;
	}

	/**
	 * @param mixed $default
	 * @return mixed
	 */
	public static function get(string $path, $default = null) {
		$value = self::$data;
		foreach (explode('.', $path) as $part) {
			if (!is_array($value) || !array_key_exists($part, $value)) {
				return $default;
			}
			$value = $value[$part];
		}
		return $value;
	}
}
