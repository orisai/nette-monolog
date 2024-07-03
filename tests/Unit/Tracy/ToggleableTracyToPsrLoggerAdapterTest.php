<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Unit\Tracy;

use OriNette\Monolog\Tracy\ToggleableTracyToPsrLoggerAdapter;
use PHPUnit\Framework\TestCase;
use Tests\OriNette\Monolog\Doubles\TracyTestLogger;

final class ToggleableTracyToPsrLoggerAdapterTest extends TestCase
{

	public function test(): void
	{
		$logger = new TracyTestLogger();
		$adapter = new ToggleableTracyToPsrLoggerAdapter($logger);

		self::assertCount(0, $logger->getRecords());

		$adapter->error('test');
		self::assertCount(1, $logger->getRecords());

		$adapter->enabled = false;
		$adapter->error('test');
		self::assertCount(1, $logger->getRecords());

		$adapter->enabled = true;
		$adapter->error('test');
		self::assertCount(2, $logger->getRecords());
	}

}
