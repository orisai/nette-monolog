<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Doubles;

use Tracy\ILogger;

final class TracyTestLogger implements ILogger
{

	/** @var array<array{value: mixed, level: mixed}> */
	private array $records = [];

	/**
	 * @param mixed $value
	 * @param mixed $level
	 */
	public function log($value, $level = self::INFO): void
	{
		$this->records[] = [
			'value' => $value,
			'level' => $level,
		];
	}

	/**
	 * @return array<array{value: mixed, level: mixed}>
	 */
	public function getRecords(): array
	{
		return $this->records;
	}

}
