<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Unit\DI;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\Processor\TagProcessor;
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
		self::assertSame([], $container->findByType(Logger::class));
		self::assertSame([], $container->findByType(HandlerInterface::class));
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

	public function testHandlerWiring(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.handlerWiring.neon');

		$container = $configurator->createContainer();

		$fooChannel = $container->getService('monolog.channel.ch_foo');
		self::assertInstanceOf(Logger::class, $fooChannel);

		$barChannel = $container->getService('monolog.channel.ch_bar');
		self::assertInstanceOf(Logger::class, $barChannel);

		$fooHandlers = $fooChannel->getHandlers();
		self::assertCount(2, $fooHandlers);

		self::assertSame($fooHandlers, $barChannel->getHandlers());

		$handlerA = $container->getService('monolog.handler.h_a');
		self::assertInstanceOf(TestHandler::class, $handlerA);

		$handlerB = $container->getService('monolog.handler.h_b');
		self::assertInstanceOf(TestHandler::class, $handlerB);

		self::assertSame([$handlerA, $handlerB], $fooHandlers);
	}

	public function testProcessorWiring(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.processorWiring.neon');

		$container = $configurator->createContainer();

		$fooChannel = $container->getService('monolog.channel.ch_foo');
		self::assertInstanceOf(Logger::class, $fooChannel);

		$barChannel = $container->getService('monolog.channel.ch_bar');
		self::assertInstanceOf(Logger::class, $barChannel);

		$fooProcessors = $fooChannel->getProcessors();
		self::assertCount(2, $fooProcessors);

		self::assertSame($fooProcessors, $barChannel->getProcessors());

		$processor1 = $container->getService('monolog.processor.p_1');
		self::assertInstanceOf(TagProcessor::class, $processor1);

		$processor2 = $container->getService('monolog.processor.p_2');
		self::assertInstanceOf(TagProcessor::class, $processor2);

		self::assertSame([$processor1, $processor2], $fooProcessors);
	}

}
