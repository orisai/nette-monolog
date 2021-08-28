<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Doubles;

use Tracy\ILogger;

final class TracyTestLogger implements ILogger
{

	/** @var array<array{0: mixed, 1: mixed}> */
	private array $records = [];

	/**
	 * @param mixed $value
	 * @param mixed $level
	 */
	public function log($value, $level = self::INFO): void
	{
		$this->records[] = [$value, $level];
	}

	/**
	 * @return array<array{0: mixed, 1: mixed}>
	 */
	public function getRecords(): array
	{
		return $this->records;
	}

}
