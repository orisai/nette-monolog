<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Unit\Tracy;

use OriNette\DI\Boot\ManualConfigurator;
use OriNette\DI\Services\MissingService;
use OriNette\Monolog\Tracy\LazyTracyToPsrLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use function dirname;

final class LazyTracyToPsrLoggerTest extends TestCase
{

	public function testExisting(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/logger.neon');

		$container = $configurator->createContainer();

		$logger = $container->getByType(LazyTracyToPsrLogger::class);

		self::assertFalse($container->isCreated('logger.one'));
		self::assertFalse($container->isCreated('logger.two'));

		$logger->log('test');
		$logger->log('test2');

		self::assertTrue($container->isCreated('logger.one'));
		$logger1 = $container->getService('logger.one');
		self::assertInstanceOf(TestLogger::class, $logger1);

		self::assertTrue($container->isCreated('logger.two'));
		$logger2 = $container->getService('logger.two');
		self::assertInstanceOf(TestLogger::class, $logger2);

		self::assertSame(
			[
				[
					'level' => 'info',
					'message' => 'test',
					'context' => [],
				],
				[
					'level' => 'info',
					'message' => 'test2',
					'context' => [],
				],
			],
			$logger1->records,
		);
		self::assertSame($logger1->records, $logger2->records);
	}

	public function testInvalidType(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/logger.invalid.neon');

		$container = $configurator->createContainer();

		$logger = $container->getByType(LazyTracyToPsrLogger::class);

		$this->expectException(MissingService::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Service logger.one returns instance of stdClass.
Problem: OriNette\Monolog\Tracy\LazyTracyToPsrLogger supports only instances of
         Psr\Log\LoggerInterface.
Solution: Remove service from LazyTracyToPsrLogger or make the service return
          supported object type.
MSG);

		$logger->log('test');
	}

}
