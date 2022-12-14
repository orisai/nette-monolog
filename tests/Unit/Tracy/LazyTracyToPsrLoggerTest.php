<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Unit\Tracy;

use Nette\DI\Container;
use OriNette\DI\Boot\ManualConfigurator;
use OriNette\Monolog\Tracy\LazyTracyToPsrLogger;
use Orisai\Exceptions\Logic\MemberInaccessible;
use PHPUnit\Framework\TestCase;
use Tests\OriNette\Monolog\Doubles\TestLogger;
use Tests\OriNette\Monolog\Doubles\TracyTestLogger;
use function dirname;

final class LazyTracyToPsrLoggerTest extends TestCase
{

	public function testExisting(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/LazyTracyToPsrLogger.neon');

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

	public function testMagicWithoutParentLogger(): void
	{
		$logger = new LazyTracyToPsrLogger([], new Container());

		self::assertFalse(isset($logger->fromEmail));

		$e = null;
		try {
			$logger->fromEmail;
		} catch (MemberInaccessible $e) {
			// Handled bellow
		}

		self::assertNotNull($e);
		self::assertSame(
			'Cannot read an undeclared property OriNette\Monolog\Tracy\LazyTracyToPsrLogger::$fromEmail',
			$e->getMessage(),
		);

		$e = null;
		try {
			$logger->fromEmail = 'foo@bar.baz';
		} catch (MemberInaccessible $e) {
			// Handled bellow
		}

		self::assertNotNull($e);
		self::assertSame(
			'Cannot write to an undeclared property OriNette\Monolog\Tracy\LazyTracyToPsrLogger::$fromEmail',
			$e->getMessage(),
		);

		$e = null;
		try {
			$logger->setFromEmail('foo@bar.baz');
		} catch (MemberInaccessible $e) {
			// Handled bellow
		}

		self::assertNotNull($e);
		self::assertSame(
			'Call to undefined or non-public method OriNette\Monolog\Tracy\LazyTracyToPsrLogger::setFromEmail()',
			$e->getMessage(),
		);
	}

	public function testMagicWithParentLogger(): void
	{
		$parentLogger = new TracyTestLogger();
		$logger = new LazyTracyToPsrLogger([], new Container(), $parentLogger);

		self::assertFalse(isset($logger->fromEmail));
		self::assertNull($logger->fromEmail);

		$logger->fromEmail = 'foo@bar.baz';
		self::assertTrue(isset($logger->fromEmail));
		self::assertSame('foo@bar.baz', $logger->fromEmail);

		$logger->setFromEmail(null);
		self::assertFalse(isset($logger->fromEmail));
		self::assertNull($logger->fromEmail);
	}

}
