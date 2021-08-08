<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Unit\DI;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;
use Monolog\Processor\TagProcessor;
use Monolog\Test\TestCase;
use OriNette\DI\Boot\ManualConfigurator;
use OriNette\Monolog\Tracy\LazyTracyToPsrLogger;
use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Logic\InvalidState;
use Psr\Log\LoggerInterface;
use Tests\OriNette\Monolog\Doubles\BazLogger;
use Tests\OriNette\Monolog\Doubles\TracyTestLogger;
use Tracy\Debugger;
use function array_column;
use function dirname;

final class MonologExtensionTest extends TestCase
{

	public function testEmpty(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/empty.neon');

		$container = $configurator->createContainer();

		self::assertSame([], $container->findByType(LoggerInterface::class));
		self::assertSame([], $container->findByType(Logger::class));
		self::assertSame([], $container->findByType(HandlerInterface::class));
		self::assertSame([], $container->findByType(ProcessorInterface::class));
	}

	public function testChannelWiring(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/channelWiring.neon');

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
		$configurator->addConfig(__DIR__ . '/channelWiring.invalid.neon');

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
		$configurator->addConfig(__DIR__ . '/handlerWiring.neon');

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
		$configurator->addConfig(__DIR__ . '/processorWiring.neon');

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

	public function testTracyHandlerServiceSet(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/tracy.handler.service.neon');

		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Trying to configure 'monolog > handlers > tracy > service'.
Problem: This options is reserved and cannot be changed.
Solution: Remove the option or choose different name for your handler.
MSG);

		$configurator->createContainer();
	}

	public function testTracyHandlerWithoutActivation(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/tracy.handler.withoutActivation.neon');

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Trying to configure 'monolog > handlers > tracy'.
Problem: This option is reserved for Tracy handler and can be configured only
         when 'monolog > bridge > toTracy' is enabled.
Solution: Set 'toTracy' option to `true` or remove Tracy handler configuration.
MSG);

		$configurator->createContainer();
	}

	public function testToTracyBridge(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/tracy.toTracy.neon');

		$container = $configurator->createContainer();

		self::assertFalse($container->isCreated('monolog.handler.tracy'));
		self::assertFalse($container->isCreated('tracy.logger'));

		$logger = $container->getService('monolog.channel.ch1');
		self::assertInstanceOf(LoggerInterface::class, $logger);

		self::assertTrue($container->isCreated('monolog.handler.tracy'));
		self::assertTrue($container->isCreated('tracy.logger'));

		$logger->notice('test');

		$tracyLogger = $container->getService('tracy.logger');
		self::assertInstanceOf(TracyTestLogger::class, $tracyLogger);

		self::assertSame(
			[
				[
					'value' => 'test',
					'level' => 'warning',
				],
			],
			$tracyLogger->getRecords(),
		);
	}

	public function testToTracyBridgeMissingService(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/tracy.toTracy.missingService.neon');

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Trying to configure 'monolog > bridge > toTracy'.
Problem: Option requires package 'tracy/tracy' to be installed and
         'Tracy\ILogger' registered as a service.
Solution: Ensure Tracy is installed and register as a service or remove the
          configuration.
MSG);

		$configurator->createContainer();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testFromTracyBridge(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/tracy.fromTracy.neon');

		$container = $configurator->createContainer();

		self::assertTrue($container->isCreated('tracy.logger'));
		self::assertInstanceOf(LazyTracyToPsrLogger::class, Debugger::getLogger());

		self::assertFalse($container->isCreated('monolog.channel.ch1'));
		self::assertFalse($container->isCreated('monolog.channel.ch2'));
		self::assertFalse($container->isCreated('monolog.channel.ch3'));
		self::assertFalse($container->isCreated('monolog.handler.test'));

		Debugger::log('test');

		self::assertTrue($container->isCreated('monolog.channel.ch1'));
		self::assertFalse($container->isCreated('monolog.channel.ch2'));
		self::assertTrue($container->isCreated('monolog.channel.ch3'));
		self::assertTrue($container->isCreated('monolog.handler.test'));

		self::assertSame(
			$container->getService('monolog.channel.ch1'),
			$container->getByType(LoggerInterface::class),
		);

		$handler = $container->getService('monolog.handler.test');
		self::assertInstanceOf(TestHandler::class, $handler);

		// Message is written to ch1 and ch3 which both use same handler, so message is duplicated
		// ch2 is not registered to bridge and so it does not write message
		self::assertSame(
			[
				'test',
				'test',
			],
			array_column($handler->getRecords(), 'message'),
		);
	}

	public function testFromTracyBridgeUnknownChannels(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/tracy.fromTracy.unknownChannels.neon');

		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Trying to configure 'monolog > bridge > fromTracy'.
Problem: Some of the given channels do not exist - 'unknown1, unknown2'.
Solution: Register these channels or remove them from configured option.
MSG);

		$configurator->createContainer();
	}

	public function testFromTracyBridgeMissingService(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/tracy.fromTracy.missingService.neon');

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Trying to configure 'monolog > bridge > fromTracy'.
Problem: Option requires package 'tracy/tracy' to be installed and
         'Tracy\ILogger' registered as a service.
Solution: Ensure Tracy is installed and register as a service or remove the
          configuration.
MSG);

		$configurator->createContainer();
	}

}