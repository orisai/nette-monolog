<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Doubles;

use Monolog\Handler\Handler;
use Monolog\Logger;
use Monolog\ResettableInterface;
use Psr\Log\LogLevel;

/**
 * Does not extend AbstractHandler intentionally
 *
 * @phpstan-import-type Level from Logger
 * @phpstan-import-type LevelName from Logger
 * @phpstan-import-type Record from Logger
 */
class SimpleTestHandler extends Handler implements ResettableInterface
{

	private int $level;

	/**
	 * @var array<array<mixed>>
	 * @phpstan-var array<Record>
	 */
	private array $records = [];

	/**
	 * @param string|int $level
	 * @phpstan-param Level|LevelName|LogLevel::* $level
	 */
	public function __construct($level = Logger::DEBUG)
	{
		$this->level = Logger::toMonologLevel($level);
	}

	/**
	 * {@inheritDoc}
	 */
	public function isHandling(array $record): bool
	{
		return $record['level'] >= $this->level;
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle(array $record): bool
	{
		$handle = $record['level'] >= $this->level;

		if ($handle) {
			$this->records[] = $record;
		}

		return $handle;
	}

	/**
	 * @return array<array<mixed>>
	 * @phpstan-return array<Record>
	 */
	public function getRecords(): array
	{
		return $this->records;
	}

	public function reset(): void
	{
		$this->records = [];
	}

	public function close(): void
	{
		parent::close();
		$this->records = [];
	}

}
