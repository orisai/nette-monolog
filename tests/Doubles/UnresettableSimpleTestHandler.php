<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Doubles;

use Monolog\Handler\Handler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Psr\Log\LogLevel;

/**
 * Does not extend AbstractHandler intentionally
 */
class UnresettableSimpleTestHandler extends Handler
{

	private int $level;

	/** @var array<array<mixed>|LogRecord> */
	protected array $records = [];

	/**
	 * @param int|string|Level $level
	 * @phpstan-param int|string|Level|LogLevel::* $level
	 */
	public function __construct($level = Logger::DEBUG)
	{
		$monologLevel = Logger::toMonologLevel($level);
		$this->level = $monologLevel instanceof Level
			? $monologLevel->value
			: $monologLevel;
	}

	/**
	 * @param array<mixed>|LogRecord $record
	 */
	public function isHandling($record): bool
	{
		return $record instanceof LogRecord
			? $record->level->value >= $this->level
			: $record['level'] >= $this->level;
	}

	/**
	 * @param array<mixed>|LogRecord $record
	 */
	public function handle($record): bool
	{
		$handle = $record instanceof LogRecord
			? $record->level->value >= $this->level
			: $record['level'] >= $this->level;

		if ($handle) {
			$this->records[] = $record;
		}

		return $handle;
	}

	/**
	 * @return array<array<mixed>|LogRecord>
	 */
	public function getRecords(): array
	{
		return $this->records;
	}

	public function close(): void
	{
		parent::close();
		$this->records = [];
	}

}
