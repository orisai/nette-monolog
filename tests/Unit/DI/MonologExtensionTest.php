<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Unit\DI;

use Generator;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;
use Monolog\Processor\TagProcessor;
use Monolog\Test\TestCase;
use Nette\DI\InvalidConfigurationException;
use OriNette\DI\Boot\ManualConfigurator;
use OriNette\Monolog\HandlerAdapter;
use OriNette\Monolog\LogFlusher;
use OriNette\Monolog\LoggerGetter;
use OriNette\Monolog\Tracy\LazyTracyToPsrLogger;
use OriNette\Monolog\Tracy\TracyPanelHandler;
use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Logic\InvalidState;
use Psr\Log\LoggerInterface;
use Tests\OriNette\Monolog\Doubles\BazLogger;
use Tests\OriNette\Monolog\Doubles\ExtendedTestHandler;
use Tests\OriNette\Monolog\Doubles\FlushTrackingLogger;
use Tests\OriNette\Monolog\Doubles\SimpleTestHandler;
use Tests\OriNette\Monolog\Doubles\TracyTestLogger;
use Tracy\Bar;
use Tracy\Debugger;
use Tracy\ILogger;
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

	public function testHandlerProcessorWiring(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/handlerWiring.processors.neon');

		$container = $configurator->createContainer();

		$channel = $container->getService('monolog.channel.ch_foo');
		self::assertInstanceOf(Logger::class, $channel);

		$handlers = $channel->getHandlers();
		self::assertCount(2, $handlers);

		$handlerA = $container->getService('monolog.handler.h_a');
		self::assertInstanceOf(ExtendedTestHandler::class, $handlerA);

		$handlerB = $container->getService('monolog.handler.h_b');
		self::assertInstanceOf(SimpleTestHandler::class, $handlerB);

		$handlerB_adapter = $container->getService('monolog.handler.h_b.adapter');
		self::assertInstanceOf(HandlerAdapter::class, $handlerB_adapter);
		self::assertSame($handlerB, $handlerB_adapter->getHandler());

		self::assertSame([$handlerA, $handlerB_adapter], $handlers);

		self::assertSame([
			$container->getService('monolog.handler.h_a.processor.p_1'),
			$container->getService('monolog.handler.h_a.processor.p_2'),
		], $handlerA->getProcessors());

		self::assertSame([
			$container->getService('monolog.handler.h_b.processor.p_1'),
			$container->getService('monolog.handler.h_b.processor.p_2'),
		], $handlerB_adapter->getProcessors());
	}

	public function testHandlerWiringInvalid1(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/handlerWiring.invalid.1.neon');

		$this->expectException(InvalidConfigurationException::class);
		$this->expectExceptionMessage(
			"Failed assertion 'Use only 'allowed' or 'forbidden', these options are incompatible.'"
			. " for item 'monolog › channels › ch_foo › handlers' with value object stdClass.",
		);

		$configurator->createContainer();
	}

	public function testHandlerWiringInvalid2(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/handlerWiring.invalid.2.neon');

		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Trying to configure 'monolog > channels > ch_foo > handlers > allowed'.
Problem: Some of the given handlers do not exist - 'unknown'.
Solution: Register these handlers or remove them from configured option.
MSG);

		$configurator->createContainer();
	}

	public function testHandlerWiringFilterAllowed(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/handlerWiring.allowed.neon');

		$container = $configurator->createContainer();

		$channel = $container->getService('monolog.channel.ch_foo');
		self::assertInstanceOf(Logger::class, $channel);

		$handlers = $channel->getHandlers();
		self::assertCount(1, $handlers);

		$handlerA = $container->getService('monolog.handler.h_a');
		self::assertInstanceOf(TestHandler::class, $handlerA);

		$handlerB = $container->getService('monolog.handler.h_b');
		self::assertInstanceOf(TestHandler::class, $handlerB);

		self::assertSame([$handlerA], $handlers);
	}

	public function testHandlerWiringFilterForbidden(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/handlerWiring.forbidden.neon');

		$container = $configurator->createContainer();

		$channel = $container->getService('monolog.channel.ch_foo');
		self::assertInstanceOf(Logger::class, $channel);

		$handlers = $channel->getHandlers();
		self::assertCount(1, $handlers);

		$handlerA = $container->getService('monolog.handler.h_a');
		self::assertInstanceOf(TestHandler::class, $handlerA);

		$handlerB = $container->getService('monolog.handler.h_b');
		self::assertInstanceOf(TestHandler::class, $handlerB);

		self::assertSame([$handlerB], $handlers);
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

	public function testProcessorWiringInvalid1(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/processorWiring.invalid.1.neon');

		$this->expectException(InvalidConfigurationException::class);
		$this->expectExceptionMessage(
			"Failed assertion 'Use only 'allowed' or 'forbidden', these options are incompatible.'"
			. " for item 'monolog › channels › ch_foo › processors' with value object stdClass.",
		);

		$configurator->createContainer();
	}

	public function testProcessorWiringInvalid2(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/processorWiring.invalid.2.neon');

		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Trying to configure 'monolog > channels > ch_foo > processors >
         allowed'.
Problem: Some of the given processors do not exist - 'unknown'.
Solution: Register these processors or remove them from configured option.
MSG);

		$configurator->createContainer();
	}

	public function testProcessorWiringInvalid3(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/processorWiring.invalid.3.neon');

		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Trying to configure 'monolog > channels > ch_foo > processors >
         forbidden'.
Problem: Some of the given processors do not exist - 'unknown'.
Solution: Register these processors or remove them from configured option.
MSG);

		$configurator->createContainer();
	}

	public function testProcessorWiringFilterAllowed(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/processorWiring.allowed.neon');

		$container = $configurator->createContainer();

		$channel = $container->getService('monolog.channel.ch_foo');
		self::assertInstanceOf(Logger::class, $channel);

		$processors = $channel->getProcessors();
		self::assertCount(1, $processors);

		$processor1 = $container->getService('monolog.processor.p_1');
		self::assertInstanceOf(TagProcessor::class, $processor1);

		$processor2 = $container->getService('monolog.processor.p_2');
		self::assertInstanceOf(TagProcessor::class, $processor2);

		self::assertSame([$processor1], $processors);
	}

	public function testProcessorWiringFilterForbidden(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/processorWiring.forbidden.neon');

		$container = $configurator->createContainer();

		$channel = $container->getService('monolog.channel.ch_foo');
		self::assertInstanceOf(Logger::class, $channel);

		$processors = $channel->getProcessors();
		self::assertCount(1, $processors);

		$processor1 = $container->getService('monolog.processor.p_1');
		self::assertInstanceOf(TagProcessor::class, $processor1);

		$processor2 = $container->getService('monolog.processor.p_2');
		self::assertInstanceOf(TagProcessor::class, $processor2);

		self::assertSame([$processor2], $processors);
	}

	public function testTracyHandlerServiceSet(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/tracy.handler.service.neon');

		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Trying to configure 'monolog > handlers > tracyLogger > service'.
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
Context: Trying to configure 'monolog > handlers > tracyLogger'.
Problem: This option is reserved for Tracy handler and can be configured only
         when 'monolog > bridge > toTracy' is enabled.
Solution: Set 'toTracy' option to `true` or remove Tracy handler configuration.
MSG);

		$configurator->createContainer();
	}

	/**
	 * @param array<mixed> $handlerRecords
	 *
	 * @dataProvider provideLogLevels
	 */
	public function testLogLevels(
		bool $levelDebug,
		string $configFile,
		array $handlerRecords,
		?string $handlerServiceName = null
	): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig($configFile);
		$configurator->addStaticParameters([
			'levelDebug' => $levelDebug,
		]);

		$container = $configurator->createContainer();

		$logger = $container->getService('monolog.channel.ch_1');
		self::assertInstanceOf(LoggerInterface::class, $logger);

		$logger->debug('debug');
		$logger->info('info');
		$logger->notice('notice');
		$logger->warning('warning');
		$logger->error('error');
		$logger->critical('critical');
		$logger->alert('alert');
		$logger->emergency('emergency');

		$handler = $container->getService($handlerServiceName ?? 'monolog.handler.h_a');
		self::assertInstanceOf(TestHandler::class, $handler);

		self::assertSame(
			$handlerRecords,
			$this->filterRecords($handler->getRecords()),
		);
	}

	/**
	 * @return Generator<array<mixed>>
	 */
	public function provideLogLevels(): Generator
	{
		$debugMessages = [
			['DEBUG', 'debug'],
			['INFO', 'info'],
			['NOTICE', 'notice'],
			['WARNING', 'warning'],
			['ERROR', 'error'],
			['CRITICAL', 'critical'],
			['ALERT', 'alert'],
			['EMERGENCY', 'emergency'],
		];

		yield [
			true,
			__DIR__ . '/logLevels.default.neon',
			$debugMessages,
		];

		$warningMessages = [
			['WARNING', 'warning'],
			['ERROR', 'error'],
			['CRITICAL', 'critical'],
			['ALERT', 'alert'],
			['EMERGENCY', 'emergency'],
		];

		yield [
			false,
			__DIR__ . '/logLevels.default.neon',
			$warningMessages,
		];

		$alertMessages = [
			['ALERT', 'alert'],
			['EMERGENCY', 'emergency'],
		];

		$emergencyMessages = [
			['EMERGENCY', 'emergency'],
		];

		yield [
			true,
			__DIR__ . '/logLevels.global.neon',
			$alertMessages,
		];

		yield [
			false,
			__DIR__ . '/logLevels.global.neon',
			$emergencyMessages,
		];

		yield [
			true,
			__DIR__ . '/logLevels.local.neon',
			$emergencyMessages,
		];

		yield [
			false,
			__DIR__ . '/logLevels.local.neon',
			$alertMessages,
		];

		yield [
			false,
			__DIR__ . '/logLevels.local.withReference.neon',
			$alertMessages,
		];

		yield [
			false,
			__DIR__ . '/logLevels.local.withReferenceType.neon',
			$alertMessages,
			'h_a',
		];
	}

	public function testLogLevelsHandlerAdapter(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/logLevels.handlerAdapter.neon');

		$container = $configurator->createContainer();

		$logger = $container->getService('monolog.channel.ch_1');
		self::assertInstanceOf(Logger::class, $logger);

		$handlerAdapter_a = $container->getService('monolog.handler.h_a.adapter');
		self::assertInstanceOf(HandlerAdapter::class, $handlerAdapter_a);

		$handler_a = $container->getService('monolog.handler.h_a');
		self::assertInstanceOf(SimpleTestHandler::class, $handler_a);
		self::assertSame($handler_a, $handlerAdapter_a->getHandler());

		$handlerAdapter_b = $container->getService('monolog.handler.h_b.adapter');
		self::assertInstanceOf(HandlerAdapter::class, $handlerAdapter_b);

		$handler_b = $container->getService('h_b');
		self::assertInstanceOf(TestHandler::class, $handler_b);
		self::assertSame($handler_b, $handlerAdapter_b->getHandler());

		self::assertSame(
			[$handlerAdapter_a, $handlerAdapter_b],
			$logger->getHandlers(),
		);

		$logger->debug('debug');
		$logger->warning('warning');
		$logger->alert('alert');
		$logger->emergency('emergency');

		self::assertSame(
			[
				['ALERT', 'alert'],
				['EMERGENCY', 'emergency'],
			],
			$this->filterRecords($handler_a->getRecords()),
		);

		self::assertSame(
			[
				['ALERT', 'alert'],
				['EMERGENCY', 'emergency'],
			],
			$this->filterRecords($handler_b->getRecords()),
		);
	}

	public function testBubblingA(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/bubbling.a.neon');

		$container = $configurator->createContainer();

		$logger = $container->getService('monolog.channel.ch_1');
		self::assertInstanceOf(Logger::class, $logger);

		$handlerAdapter_a = $container->getService('monolog.handler.h_a.adapter');
		self::assertInstanceOf(HandlerAdapter::class, $handlerAdapter_a);

		$handler_a = $container->getService('monolog.handler.h_a');
		self::assertInstanceOf(SimpleTestHandler::class, $handler_a);
		self::assertSame($handler_a, $handlerAdapter_a->getHandler());

		$handler_b = $container->getService('monolog.handler.h_b');
		self::assertInstanceOf(TestHandler::class, $handler_b);

		self::assertSame(
			[$handlerAdapter_a, $handler_b, $container->getService('monolog.handler.h_c.adapter')],
			$logger->getHandlers(),
		);

		$logger->debug('debug');

		self::assertSame(
			[
				['DEBUG', 'debug'],
			],
			$this->filterRecords($handler_a->getRecords()),
		);

		self::assertSame(
			[
				['DEBUG', 'debug'],
			],
			$this->filterRecords($handler_b->getRecords()),
		);
	}

	public function testBubblingB(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/bubbling.b.neon');

		$container = $configurator->createContainer();

		$logger = $container->getService('monolog.channel.ch_1');
		self::assertInstanceOf(Logger::class, $logger);

		$handler_a = $container->getService('monolog.handler.h_a');
		self::assertInstanceOf(TestHandler::class, $handler_a);

		$handlerAdapter_b = $container->getService('monolog.handler.h_b.adapter');
		self::assertInstanceOf(HandlerAdapter::class, $handlerAdapter_b);

		$handler_b = $container->getService('monolog.handler.h_b');
		self::assertInstanceOf(SimpleTestHandler::class, $handler_b);
		self::assertSame($handler_b, $handlerAdapter_b->getHandler());

		self::assertSame(
			[$handler_a, $handlerAdapter_b, $container->getService('monolog.handler.h_c.adapter')],
			$logger->getHandlers(),
		);

		$logger->debug('debug');

		self::assertSame(
			[
				['DEBUG', 'debug'],
			],
			$this->filterRecords($handler_a->getRecords()),
		);

		self::assertSame(
			[],
			$this->filterRecords($handler_b->getRecords()),
		);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testToTracyBridge(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/tracy.toTracy.neon');

		$container = $configurator->createContainer();

		self::assertFalse($container->isCreated('monolog.handler.tracyLogger'));
		self::assertFalse($container->isCreated('monolog.bridge.psrToTracy'));
		self::assertFalse($container->isCreated('tracy.logger'));

		$logger = $container->getService('monolog.channel.ch1');
		self::assertInstanceOf(LoggerInterface::class, $logger);

		self::assertTrue($container->isCreated('monolog.handler.tracyLogger'));
		self::assertTrue($container->isCreated('monolog.bridge.psrToTracy'));
		self::assertTrue($container->isCreated('tracy.logger'));

		$logger->debug('debug');
		$logger->info('info');
		$logger->notice('notice');
		$logger->warning('warning');
		$logger->error('error');
		$logger->critical('critical');
		$logger->alert('alert');
		$logger->emergency('emergency');

		$tracyLogger = $container->getService('tracy.logger');
		self::assertInstanceOf(TracyTestLogger::class, $tracyLogger);
		self::assertInstanceOf(TracyTestLogger::class, $container->getByType(ILogger::class));

		self::assertSame(
			[
				['debug', 'debug'],
				['info', 'info'],
				['notice', 'warning'],
				['warning', 'warning'],
				['error', 'error'],
				['critical', 'critical'],
				['alert', 'critical'],
				['emergency', 'critical'],
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

		Debugger::log('debug', ILogger::DEBUG);
		Debugger::log('info', ILogger::INFO);
		Debugger::log('warning', ILogger::WARNING);
		Debugger::log('error', ILogger::ERROR);
		Debugger::log('exception', ILogger::EXCEPTION);
		Debugger::log('critical', ILogger::CRITICAL);
		Debugger::log('unknown', 'unknown');

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
				['DEBUG', 'debug'],
				['DEBUG', 'debug'],
				['INFO', 'info'],
				['INFO', 'info'],
				['WARNING', 'warning'],
				['WARNING', 'warning'],
				['ERROR', 'error'],
				['ERROR', 'error'],
				['ERROR', 'exception'],
				['ERROR', 'exception'],
				['CRITICAL', 'critical'],
				['CRITICAL', 'critical'],
				['ERROR', 'unknown'],
				['ERROR', 'unknown'],
			],
			$this->filterRecords($handler->getRecords()),
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

	/**
	 * @runInSeparateProcess
	 */
	public function testTracyBothDirections(): void
	{
		$tracyLogger = new TracyTestLogger();
		Debugger::setLogger($tracyLogger);

		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/tracy.bothDirections.neon');

		$container = $configurator->createContainer();

		self::assertFalse($container->isCreated('monolog.handler.tracyLogger'));
		self::assertFalse($container->isCreated('monolog.bridge.psrToTracy'));
		self::assertTrue($container->isCreated('tracy.logger'));

		$logger = $container->getService('monolog.channel.ch1');
		self::assertInstanceOf(LoggerInterface::class, $logger);

		self::assertTrue($container->isCreated('monolog.handler.tracyLogger'));
		self::assertTrue($container->isCreated('monolog.bridge.psrToTracy'));

		$logger->notice('monolog');
		Debugger::log('tracy', ILogger::ERROR);

		$tracyLogger = $container->getService('tracy.logger');
		self::assertInstanceOf(TracyTestLogger::class, $tracyLogger);
		self::assertInstanceOf(LazyTracyToPsrLogger::class, $container->getByType(ILogger::class));

		$handler = $container->getService('monolog.handler.h_a');
		self::assertInstanceOf(TestHandler::class, $handler);

		self::assertSame(
			[
				[
					'monolog',
					'warning',
				],
				[
					'tracy',
					'error',
				],
			],
			$tracyLogger->getRecords(),
		);

		self::assertSame(
			[
				['NOTICE', 'monolog'],
				['ERROR', 'tracy'],
			],
			$this->filterRecords($handler->getRecords()),
		);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testTracyPanelBridge(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/tracy.tracyPanel.neon');

		$container = $configurator->createContainer();

		self::assertFalse($container->isCreated('tracy.bar'));

		$logger = $container->getService('monolog.channel.main');
		self::assertInstanceOf(Logger::class, $logger);
		self::assertTrue($container->isCreated('tracy.bar'));

		$handler = $container->getService('monolog.handler.tracyPanel');
		self::assertInstanceOf(TracyPanelHandler::class, $handler);

		self::assertSame([$handler], $logger->getHandlers());

		$bar = $container->getByType(Bar::class);
		self::assertSame($handler, $bar->getPanel('monolog.panel'));
	}

	public function testTracyPanelBridgeMissingService(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/tracy.tracyPanel.missingService.neon');

		$this->expectException(InvalidState::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Trying to configure 'monolog > bridge > tracyPanel'.
Problem: Option requires package 'tracy/tracy' to be installed and
         'Tracy\ILogger' registered as a service.
Solution: Ensure Tracy is installed and register as a service or remove the
          configuration.
MSG);

		$configurator->createContainer();
	}

	/**
	 * @param array<mixed> $records
	 * @return array<mixed>
	 */
	private function filterRecords(array $records): array
	{
		$filtered = [];
		foreach ($records as $record) {
			$filtered[] = [
				$record['level_name'],
				$record['message'],
			];
		}

		return $filtered;
	}

	public function testLogFlusher(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/logFlusher.neon');

		$container = $configurator->createContainer();

		$logFlusher = $container->getService('monolog.logFlusher');
		self::assertInstanceOf(LogFlusher::class, $logFlusher);

		$logFlusher->reset();
		$logFlusher->close();

		self::assertFalse($container->isCreated('monolog.channel.foo'));
		self::assertFalse($container->isCreated('monolog.channel.bar'));

		$fooChannel = $container->getService('monolog.channel.foo');
		self::assertInstanceOf(FlushTrackingLogger::class, $fooChannel);
		self::assertSame(0, $fooChannel->resetCount);
		self::assertSame(0, $fooChannel->closeCount);

		$logFlusher->reset();
		$logFlusher->close();

		self::assertSame(1, $fooChannel->resetCount);
		self::assertSame(1, $fooChannel->closeCount);

		$barChannel = $container->getService('monolog.channel.bar');
		self::assertInstanceOf(FlushTrackingLogger::class, $barChannel);
		self::assertSame(0, $barChannel->resetCount);
		self::assertSame(0, $barChannel->closeCount);

		$logFlusher->reset();
		$logFlusher->close();

		self::assertSame(2, $fooChannel->resetCount);
		self::assertSame(2, $fooChannel->closeCount);
		self::assertSame(1, $barChannel->resetCount);
		self::assertSame(1, $barChannel->closeCount);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testStaticGetter(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/staticGetter.neon');

		$container = $configurator->createContainer();

		$mainChannel = $container->getService('monolog.channel.main');
		self::assertInstanceOf(Logger::class, $mainChannel);

		self::assertSame($mainChannel, LoggerGetter::get());
	}

	public function testStaticGetterUnknown(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/staticGetter.unknown.neon');

		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Trying to configure 'monolog > staticGetter'.
Problem: Given channel name 'main' is unknown.
Solution: Use only name of channel listed in 'monolog > channels' or remove the
          option.
MSG);

		$configurator->createContainer();
	}

}
