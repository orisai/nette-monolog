<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Unit;

use Monolog\Logger;
use OriNette\Monolog\LoggerGetter;
use Orisai\Exceptions\Logic\InvalidState;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
final class LoggerGetterTest extends TestCase
{

	public function testOk(): void
	{
		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Trying to get logger from OriNette\Monolog\LoggerGetter.
Problem: Logger is not set.
Solution: Enable getter via 'staticLogger' option of
          OriNette\Monolog\DI\MonologExtension.
MSG);

		LoggerGetter::get();
	}

	public function testFailure(): void
	{
		$logger = new Logger('test');
		LoggerGetter::set($logger);

		self::assertSame($logger, LoggerGetter::get());
	}

}
