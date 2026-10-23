<?php
/**
 * Thin PDO wrapper. Every query goes through prepared statements.
 */

declare(strict_types=1);

final class Rishe_DB {

	private static ?PDO $pdo = null;

	public static function connect(array $db): PDO {
		$dsn = sprintf(
			'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
			$db['host'] ?? 'localhost',
			(int) ($db['port'] ?? 3306),
			$db['name'] ?? ''
		);
		return new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES   => false,
		]);
	}

	public static function pdo(): PDO {
		if (self::$pdo === null) {
			self::$pdo = self::connect((array) Rishe_Config::get('db', []));
		}
		return self::$pdo;
	}

	public static function query(string $sql, array $params = []): PDOStatement {
		$stmt = self::pdo()->prepare($sql);
		$stmt->execute($params);
		return $stmt;
	}

	public static function row(string $sql, array $params = []): ?array {
		$row = self::query($sql, $params)->fetch();
		return $row === false ? null : $row;
	}

	public static function rows(string $sql, array $params = []): array {
		return self::query($sql, $params)->fetchAll();
	}

	/**
	 * @return mixed
	 */
	public static function value(string $sql, array $params = []) {
		$value = self::query($sql, $params)->fetchColumn();
		return $value === false ? null : $value;
	}

	public static function insert(string $table, array $data): int {
		self::assert_identifier($table);
		$cols = array_keys($data);
		array_walk($cols, [self::class, 'assert_identifier']);
		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES (%s)',
			$table,
			implode(', ', $cols),
			implode(', ', array_fill(0, count($cols), '?'))
		);
		self::query($sql, array_values($data));
		return (int) self::pdo()->lastInsertId();
	}

	public static function update(string $table, array $data, array $where): int {
		self::assert_identifier($table);
		$set    = [];
		$params = [];
		foreach ($data as $col => $value) {
			self::assert_identifier($col);
			$set[]    = $col . ' = ?';
			$params[] = $value;
		}
		$cond = [];
		foreach ($where as $col => $value) {
			self::assert_identifier($col);
			$cond[]   = $col . ' = ?';
			$params[] = $value;
		}
		$sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $set), implode(' AND ', $cond));
		return self::query($sql, $params)->rowCount();
	}

	public static function delete(string $table, array $where): int {
		self::assert_identifier($table);
		$cond   = [];
		$params = [];
		foreach ($where as $col => $value) {
			self::assert_identifier($col);
			$cond[]   = $col . ' = ?';
			$params[] = $value;
		}
		return self::query(sprintf('DELETE FROM %s WHERE %s', $table, implode(' AND ', $cond)), $params)->rowCount();
	}

	/**
	 * Build "?, ?, ?" for IN () clauses.
	 */
	public static function placeholders(array $values): string {
		return implode(', ', array_fill(0, max(1, count($values)), '?'));
	}

	private static function assert_identifier(string $name): void {
		if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
			throw new InvalidArgumentException('Invalid SQL identifier.');
		}
	}
}
