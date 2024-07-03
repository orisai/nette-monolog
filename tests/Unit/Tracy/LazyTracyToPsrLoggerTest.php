<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Unit\Tracy;

use Exception;
use Nette\DI\Container;
use OriNette\DI\Boot\ManualConfigurator;
use OriNette\Monolog\Tracy\LazyTracyToPsrLogger;
use Orisai\Exceptions\Logic\MemberInaccessible;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\OriNette\Monolog\Doubles\TestLogger;
use Tests\OriNette\Monolog\Doubles\TracyTestLogger;
use function dirname;

final class LazyTracyToPsrLoggerTest extends TestCase
{

	public function testExisting(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setForceReloadContainer();
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

	public function testNonStringValues(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setForceReloadContainer();
		$configurator->addConfig(__DIR__ . '/LazyTracyToPsrLogger.neon');

		$container = $configurator->createContainer();

		$logger = $container->getByType(LazyTracyToPsrLogger::class);

		$logger->log($e1 = new Exception());
		$logger->log($e2 = new RuntimeException('message', 123));
		$logger->log(123);

		$logger1 = $container->getService('logger.one');
		self::assertInstanceOf(TestLogger::class, $logger1);

		self::assertCount(3, $logger1->records);

		$file = __FILE__;

		$record = $logger1->records[0];
		self::assertSame('info', $record['level']);
		self::assertIsString($record['message']);
		self::assertStringMatchesFormat("Exception: in $file:%s", $record['message']);
		self::assertSame(
			['exception' => $e1],
			$record['context'],
		);

		$record = $logger1->records[1];
		self::assertSame('info', $record['level']);
		self::assertIsString($record['message']);
		self::assertStringMatchesFormat("RuntimeException: message #123 in $file:%s", $record['message']);
		self::assertSame(
			['exception' => $e2],
			$record['context'],
		);

		$record = $logger1->records[2];
		self::assertSame('info', $record['level']);
		self::assertSame('123', $record['message']);
		self::assertSame([], $record['context']);
	}

	public function testCustomLogLevels(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setForceReloadContainer();
		$configurator->addConfig(__DIR__ . '/LazyTracyToPsrLogger.neon');

		$container = $configurator->createContainer();

		$logger = $container->getByType(LazyTracyToPsrLogger::class);

		$logger->log(new Exception(), 'custom');
		$logger->log('message', 'custom');

		$logger1 = $container->getService('logger.one');
		self::assertInstanceOf(TestLogger::class, $logger1);

		self::assertCount(2, $logger1->records);

		$record = $logger1->records[0];
		self::assertSame('error', $record['level']);

		$record = $logger1->records[1];
		self::assertSame('info', $record['level']);
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
