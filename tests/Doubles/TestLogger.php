<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Doubles;

use Psr\Log\AbstractLogger;

class TestLogger extends AbstractLogger
{

	/** @var array<array<mixed>> */
	public array $records = [];

	/** @var array<array<array<mixed>>> */
	public array $recordsByLevel = [];

	/**
	 * {@inheritDoc}
	 */
	public function log($level, $message, array $context = []): void
	{
		$record = [
			'level' => $level,
			'message' => $message,
			'context' => $context,
		];

		$this->recordsByLevel[$record['level']][] = $record;
		$this->records[] = $record;
	}

}
