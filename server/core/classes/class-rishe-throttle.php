<?php
/**
 * Fixed-window rate limiter backed by rishe_throttle.
 */

declare(strict_types=1);

final class Rishe_Throttle {

	/**
	 * Register a hit and report whether the caller is still under the limit.
	 */
	public static function hit(string $key, int $max, int $window_seconds): bool {
		$key = substr(hash('sha256', $key), 0, 64);
		$row = Rishe_DB::row('SELECT hits, window_start FROM rishe_throttle WHERE throttle_key = ?', [$key]);
		$now = time();

		if (!$row || strtotime($row['window_start']) + $window_seconds < $now) {
			Rishe_DB::query(
				'REPLACE INTO rishe_throttle (throttle_key, hits, window_start) VALUES (?, 1, ?)',
				[$key, date('Y-m-d H:i:s', $now)]
			);
			return true;
		}

		Rishe_DB::query('UPDATE rishe_throttle SET hits = hits + 1 WHERE throttle_key = ?', [$key]);
		return ((int) $row['hits'] + 1) <= $max;
	}

	public static function clear(string $key): void {
		Rishe_DB::delete('rishe_throttle', ['throttle_key' => substr(hash('sha256', $key), 0, 64)]);
	}

	public static function client_ip(): string {
		return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
	}
}
