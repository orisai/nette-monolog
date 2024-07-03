<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Doubles;

use Psr\Log\AbstractLogger;
use Stringable;

class TestLogger extends AbstractLogger
{

	/** @var list<array{level: mixed, message: string|Stringable, context: array<mixed>}> */
	public array $records = [];

	/** @var array<list<array{level: mixed, message: string|Stringable, context: array<mixed>}>> */
	public array $recordsByLevel = [];

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
