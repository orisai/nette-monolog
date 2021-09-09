<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Unit;

use Monolog\Handler\BufferHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use OriNette\Monolog\HandlerAdapter;
use PHPUnit\Framework\TestCase;
use Tests\OriNette\Monolog\Doubles\SimpleTestHandler;

final class HandlerAdapterTest extends TestCase
{

	public function testBase(): void
	{
		$wrappedHandler = new SimpleTestHandler();
		$handler = new HandlerAdapter($wrappedHandler, Logger::DEBUG, true, []);
		$logger = new Logger('main', [$handler]);

		$logger->debug('debug');
		$logger->warning('warning');
		self::assertCount(2, $wrappedHandler->getRecords());

		self::assertSame($wrappedHandler, $handler->getHandler());
		self::assertSame([], $handler->getProcessors());
	}

	public function testLevel(): void
	{
		$wrappedHandler = new SimpleTestHandler();
		$handler = new HandlerAdapter($wrappedHandler, Logger::WARNING, true, []);
		$logger = new Logger('main', [$handler]);

		$logger->debug('debug');
		$logger->notice('notice');
		$logger->warning('warning');
		$logger->error('error');
		self::assertCount(2, $wrappedHandler->getRecords());
	}

	public function testBubbling(): void
	{
		$wrappedHandler = new SimpleTestHandler();
		$otherHandler = new SimpleTestHandler();
		$handler = new HandlerAdapter($wrappedHandler, Logger::DEBUG, true, []);
		$logger = new Logger('main', [$handler, $otherHandler]);

		$logger->debug('debug');
		self::assertCount(1, $wrappedHandler->getRecords());
		self::assertCount(1, $otherHandler->getRecords());
	}

	public function testNotBubbling(): void
	{
		$wrappedHandler = new SimpleTestHandler();
		$otherHandler = new SimpleTestHandler();
		$handler = new HandlerAdapter($wrappedHandler, Logger::DEBUG, false, []);
		$logger = new Logger('main', [$handler, $otherHandler]);

		$logger->debug('debug');
		self::assertCount(1, $wrappedHandler->getRecords());
		self::assertCount(0, $otherHandler->getRecords());
	}

	public function testNotHandling(): void
	{
		$wrappedHandler = new SimpleTestHandler();
		$otherHandler = new SimpleTestHandler();
		$handler = new HandlerAdapter($wrappedHandler, Logger::NOTICE, false, []);
		$logger = new Logger('main', [$handler, $otherHandler]);

		$logger->debug('debug');
		self::assertCount(0, $wrappedHandler->getRecords());
		self::assertCount(1, $otherHandler->getRecords());
	}

	public function testBatchLogging(): void
	{
		$wrappedHandler = new SimpleTestHandler();
		$handler = new HandlerAdapter($wrappedHandler, Logger::DEBUG, true, []);
		$bufferHandler = new BufferHandler($handler, 2, Logger::DEBUG, true, true);
		$logger = new Logger('main', [$bufferHandler]);

		$logger->debug('debug');
		$logger->warning('warning');
		self::assertCount(0, $wrappedHandler->getRecords());

		$logger->warning('warning');
		self::assertCount(2, $wrappedHandler->getRecords());
	}

	public function testFlush(): void
	{
		$wrappedHandler = new SimpleTestHandler();
		$handler = new HandlerAdapter($wrappedHandler, Logger::DEBUG, true, []);
		$logger = new Logger('main', [$handler]);

		$logger->debug('debug');
		self::assertCount(1, $wrappedHandler->getRecords());

		$logger->reset();
		self::assertCount(0, $wrappedHandler->getRecords());

		$logger->debug('debug');
		self::assertCount(1, $wrappedHandler->getRecords());

		$logger->close();
		self::assertCount(0, $wrappedHandler->getRecords());
	}

	public function testProcessors(): void
	{
		$wrappedHandler = new SimpleTestHandler();
		$uidProcessor = new UidProcessor();
		$processors = [$uidProcessor];
		$handler = new HandlerAdapter($wrappedHandler, Logger::DEBUG, true, $processors);
		$logger = new Logger('main', [$handler]);

		$uid = $uidProcessor->getUid();

		self::assertSame($processors, $handler->getProcessors());

		$logger->debug('debug');
		self::assertSame(
			[
				[
					'message' => 'debug',
					'context' => [],
					'level' => Logger::DEBUG,
					'extra' => [
						'uid' => $uid,
					],
				],
			],
			$this->filterRecords($wrappedHandler->getRecords()),
		);

		$logger->reset();
		self::assertNotSame($uid, $uidProcessor->getUid());
	}

	/**
	 * @param array<mixed> $records
	 * @return array<mixed>
	 */
	private function filterRecords(array $records): array
	{
		foreach ($records as $key => $record) {
			unset($record['channel'], $record['datetime'], $record['level_name']);
			$records[$key] = $record;
		}

		return $records;
	}

}
