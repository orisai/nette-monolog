<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Unit\DI;

use Monolog\Logger;
use Monolog\Test\TestCase;
use OriNette\DI\Boot\ManualConfigurator;
use Orisai\Exceptions\Logic\InvalidArgument;
use Psr\Log\LoggerInterface;
use Tests\OriNette\Monolog\Doubles\BazLogger;
use function dirname;

final class MonologExtensionTest extends TestCase
{

	public function testEmpty(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.empty.neon');

		$container = $configurator->createContainer();

		self::assertSame([], $container->findByType(LoggerInterface::class));
	}

	public function testChannelWiring(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.channelWiring.neon');

		$container = $configurator->createContainer();

		self::assertCount(3, $container->findByType(LoggerInterface::class));

		$fooChannel = $container->getService('monolog.channel.ch_foo');
		self::assertInstanceOf(Logger::class, $fooChannel);

		$barChannel = $container->getService('monolog.channel.ch_bar');
		self::assertInstanceOf(Logger::class, $barChannel);
		self::assertSame($barChannel, $container->getByType(Logger::class));

		$bazChannel = $container->getService('monolog.channel.ch_baz');
		self::assertInstanceOf(BazLogger::class, $bazChannel);
		self::assertSame($bazChannel, $container->getByType(BazLogger::class));
	}

	public function testChannelWiringInvalidClass(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.channelWiring.invalid.neon');

		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Trying to configure 'monolog > channels > ch_baz > autowired'.
Problem: 'autowired' expects bool or name of class which extends
         'Monolog\Logger', 'stdClass' given.
Solution: Use bool or class which extends expected class instead.
MSG);

		$configurator->createContainer();
	}

}
