<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Unit;

use OriNette\DI\Boot\ManualConfigurator;
use OriNette\Monolog\StaticLoggerGetter;
use Orisai\Exceptions\Logic\InvalidState;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use function dirname;

/**
 * @runTestsInSeparateProcesses
 */
final class StaticLoggerGetterTest extends TestCase
{

	public function testFailure(): void
	{
		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Trying to get logger from OriNette\Monolog\StaticLoggerGetter.
Problem: Logger is not set.
Solution: Enable getter via 'staticGetter' option of
          OriNette\Monolog\DI\MonologExtension.
MSG);

		StaticLoggerGetter::get();
	}

	public function testOk(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 2));
		$configurator->setDebugMode(true);

		$configurator->addConfig(__DIR__ . '/staticLoggerGetter.neon');

		$container = $configurator->createContainer();
		$logger = $container->getByType(LoggerInterface::class);

		StaticLoggerGetter::set('logger', $container);

		self::assertSame($logger, StaticLoggerGetter::get());
		self::assertSame($logger, StaticLoggerGetter::get());
	}

}
