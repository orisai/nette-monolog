<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Unit\Tracy;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use OriNette\Monolog\Tracy\TracyPanelHandler;
use PHPUnit\Framework\TestCase;
use function class_exists;

/**
 * @runTestsInSeparateProcesses Because other tests started failing for some reason
 */
final class TracyPanelHandlerTest extends TestCase
{

	public function test(): void
	{
		$handler = new TracyPanelHandler();

		$record = class_exists(LogRecord::class)
			? new LogRecord(
				new DateTimeImmutable(),
				'main',
				Level::Info,
				'message',
				[],
				[],
			)
			: [
				'datetime' => new DateTimeImmutable(),
				'channel' => 'main',
				'level' => 200,
				'level_name' => 'INFO',
				'message' => 'message',
				'context' => [],
				'extra' => [],
			];

		self::assertEmpty($handler->getTab());
		self::assertEmpty($handler->getPanel());

		$handler->handle($record);
		self::assertNotEmpty($handler->getTab());
		self::assertNotEmpty($handler->getPanel());

		$handler->handle($record);
		self::assertNotEmpty($handler->getTab());
		self::assertNotEmpty($handler->getPanel());
	}

}
