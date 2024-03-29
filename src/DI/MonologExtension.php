<?php declare(strict_types = 1);

namespace OriNette\Monolog\DI;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Nette\Application\Application;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\DI\MissingServiceException;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use OriNette\DI\Definitions\DefinitionsLoader;
use OriNette\Monolog\HandlerAdapter;
use OriNette\Monolog\LogFlusher;
use OriNette\Monolog\StaticLoggerGetter;
use OriNette\Monolog\Tracy\LazyTracyToPsrLogger;
use OriNette\Monolog\Tracy\TracyPanelHandler;
use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\Exceptions\Message;
use Psr\Log\LogLevel;
use stdClass;
use Tracy\Bar;
use Tracy\Bridges\Psr\TracyToPsrLoggerAdapter;
use Tracy\Debugger;
use Tracy\ILogger;
use function array_diff;
use function array_keys;
use function array_reverse;
use function array_unique;
use function array_values;
use function assert;
use function count;
use function implode;
use function in_array;
use function is_a;
use function is_array;
use function is_string;

/**
 * @property-read stdClass $config
 */
final class MonologExtension extends CompilerExtension
{

	private const LogLevels = [
		LogLevel::DEBUG,
		LogLevel::INFO,
		LogLevel::NOTICE,
		LogLevel::WARNING,
		LogLevel::ERROR,
		LogLevel::CRITICAL,
		LogLevel::ALERT,
		LogLevel::EMERGENCY,
	];

	/** @var array<string, ServiceDefinition> */
	private array $channelDefinitions;

	/** @var array<string, Definition|Reference> */
	private array $handlerDefinitions;

	private ServiceDefinition $logFlusherDefinition;

	private function getFilteredDefinitionsSchema(): Schema
	{
		return Expect::structure([
			'allowed' => Expect::listOf(Expect::string()),
			'forbidden' => Expect::listOf(Expect::string()),
		])->assert(
			static fn (stdClass $value): bool => !($value->allowed !== [] && $value->forbidden !== []),
			'Use only \'allowed\' or \'forbidden\', these options are incompatible.',
		);
	}

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'debug' => Expect::bool()->required(),
			'level' => Expect::structure([
				'debug' => Expect::anyOf(...self::LogLevels)->default(LogLevel::DEBUG),
				'production' => Expect::anyOf(...self::LogLevels)->default(LogLevel::WARNING),
			]),
			'channels' => Expect::arrayOf(
				Expect::structure([
					'autowired' => Expect::anyOf(
						Expect::bool(),
						Expect::string(),
					)->default(false),
					'handlers' => $this->getFilteredDefinitionsSchema(),
					'processors' => $this->getFilteredDefinitionsSchema(),
				]),
				Expect::string(),
			),
			'handlers' => Expect::arrayOf(
				Expect::structure([
					'enabled' => Expect::bool(true),
					'service' => DefinitionsLoader::schema(),
					'level' => Expect::structure([
						'debug' => Expect::anyOf(null, ...self::LogLevels),
						'production' => Expect::anyOf(null, ...self::LogLevels),
					]),
					'bubble' => Expect::bool(true),
					'processors' => Expect::arrayOf(
						DefinitionsLoader::schema(),
					),
				]),
				Expect::string(),
			),
			'processors' => Expect::arrayOf(
				DefinitionsLoader::schema(),
				Expect::string(),
			),
			'bridge' => Expect::structure([
				'fromTracy' => Expect::listOf(Expect::string()),
				'toTracy' => Expect::bool(false),
				'tracyPanel' => Expect::bool(false),
			]),
			'staticGetter' => Expect::anyOf(Expect::string(), Expect::null()),
		])->before(fn ($value) => $this->configureTracyHandlers($value));
	}

	public function loadConfiguration(): void
	{
		parent::loadConfiguration();

		$builder = $this->getContainerBuilder();
		$config = $this->config;
		$loader = new DefinitionsLoader($this->compiler);

		$channelDefinitions = $this->registerChannels($config, $builder);

		$this->logFlusherDefinition = $this->registerLogFlusher($channelDefinitions, $builder);
		$this->registerStaticGetter($channelDefinitions, $config);

		$config = $this->processTracyPanelHandlerConfig($config);
		$config = $this->processTracyLoggerHandlerConfig($config);

		$this->registerHandlers($config, $loader);

		$processorDefinitions = $this->registerProcessors($config, $loader);
		$this->addProcessorsToChannels($channelDefinitions, $processorDefinitions, $config);
	}

	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();
		$config = $this->config;
		$loader = new DefinitionsLoader($this->compiler);

		$this->configureHandlers($config, $builder, $loader);
		$this->addHandlersToChannels($this->channelDefinitions, $this->handlerDefinitions, $config);

		// Tracy may not be available in loadConfiguration(), depending on extension order
		$this->registerToTracyBridge($config, $builder);
		$this->registerFromTracyBridge($this->channelDefinitions, $config, $builder);
		$this->registerTracyPanel($this->handlerDefinitions, $builder);

		$this->addFlusherToApplicationShutdown($this->logFlusherDefinition, $builder);
	}

	/**
	 * @return array<string, ServiceDefinition>
	 */
	private function registerChannels(stdClass $config, ContainerBuilder $builder): array
	{
		$channelDefinitions = [];
		foreach ($config->channels as $channelName => $channelConfig) {
			assert(is_string($channelName));

			$loggerClass = Logger::class;
			$autowired = $channelConfig->autowired;

			if (is_string($autowired) && !is_a($autowired, $loggerClass, true)) {
				$message = Message::create()
					->withContext("Trying to configure '$this->name > channels > $channelName > autowired'.")
					->withProblem(
						"'autowired' expects bool or name of class which extends '$loggerClass', '$autowired' given.",
					)
					->withSolution('Use bool or class which extends expected class instead.');

				throw InvalidArgument::create()
					->withMessage($message);
			}

			$channelDefinitions[$channelName] = $builder->addDefinition($this->prefix("channel.$channelName"))
				->setFactory(
					is_string($autowired) ? $autowired : $loggerClass,
					[
						'name' => $channelName,
						'handlers' => [],
						'processors' => [],
					],
				)
				->setAutowired($autowired);
		}

		return $this->channelDefinitions = $channelDefinitions;
	}

	/**
	 * @param array<string, ServiceDefinition> $channelDefinitions
	 */
	private function registerLogFlusher(array $channelDefinitions, ContainerBuilder $builder): ServiceDefinition
	{
		$channelDefinitionMap = [];
		foreach ($channelDefinitions as $channelName => $channelDefinition) {
			$channelDefinitionMap[$channelName] = $channelDefinition->getName();
		}

		return $builder->addDefinition($this->prefix('logFlusher'))
			->setFactory(LogFlusher::class, [
				$channelDefinitionMap,
			]);
	}

	/**
	 * @param array<string, ServiceDefinition> $channelDefinitions
	 */
	private function registerStaticGetter(array $channelDefinitions, stdClass $config): void
	{
		$channelName = $config->staticGetter;

		if ($channelName === null) {
			return;
		}

		$channelDefinition = $channelDefinitions[$channelName] ?? null;

		if ($channelDefinition === null) {
			$message = Message::create()
				->withContext("Trying to configure '$this->name > staticGetter'.")
				->withProblem("Given channel name '$channelName' is unknown.")
				->withSolution("Use only name of channel listed in '$this->name > channels' or remove the option.");

			throw InvalidArgument::create()
				->withMessage($message);
		}

		$init = $this->getInitialization();
		$init->addBody(StaticLoggerGetter::class . '::set(?, $this);', [
			$channelDefinition->getName(),
		]);
	}

	private function registerHandlers(stdClass $config, DefinitionsLoader $loader): void
	{
		$handlerDefinitions = [];
		foreach ($config->handlers as $handlerName => $handlerConfig) {
			assert(is_string($handlerName));

			if ($handlerConfig->enabled !== true) {
				continue;
			}

			$handlerDefinitions[$handlerName] = $loader->loadDefinitionFromConfig(
				$handlerConfig->service,
				$this->prefix("handler.$handlerName"),
			);
		}

		$this->handlerDefinitions = $handlerDefinitions;
	}

	/**
	 * @param array<string, ServiceDefinition>    $channelDefinitions
	 * @param array<string, Definition|Reference> $handlerDefinitions
	 */
	private function addHandlersToChannels(array $channelDefinitions, array $handlerDefinitions, stdClass $config): void
	{
		foreach ($channelDefinitions as $channelName => $channelDefinition) {
			$handlersConfig = $config->channels[$channelName]->handlers;

			$filteredHandlerDefinitions = $this->filterAllowedDefinitions(
				$handlerDefinitions,
				$handlersConfig->allowed,
				$handlersConfig->forbidden,
				"channels > $channelName > handlers > allowed",
				"channels > $channelName > handlers > forbidden",
				'handlers',
			);

			$channelDefinition->addSetup('setHandlers', [$filteredHandlerDefinitions]);
		}
	}

	private function configureHandlers(stdClass $config, ContainerBuilder $builder, DefinitionsLoader $loader): void
	{
		$defaultLevel = $config->debug === false
			? $config->level->production
			: $config->level->debug;

		foreach ($this->handlerDefinitions as $name => $definition) {
			$handlerConfig = $config->handlers[$name];

			if ($definition instanceof Reference) {
				$this->handlerDefinitions[$name] = $definition = $this->tryResolveReference($definition);
			}

			$handlerLevel = $config->debug === false
				? $handlerConfig->level->production
				: $handlerConfig->level->debug;

			$handlerLevel = Logger::toMonologLevel(
				$handlerLevel ?? $defaultLevel,
			);

			$bubble = $handlerConfig->bubble;

			$processorDefinitions = $this->registerHandlerProcessors($name, $handlerConfig, $loader);

			if (
				!$definition instanceof ServiceDefinition
				|| ($type = $definition->getType()) === null
				|| !is_a($type, AbstractHandler::class, true)
				|| (!is_a($type, ProcessableHandlerInterface::class, true) && $processorDefinitions !== [])
			) {
				$this->handlerDefinitions[$name] = $builder->addDefinition($this->prefix("handler.$name.adapter"))
					->setFactory(HandlerAdapter::class, [
						$definition,
						$handlerLevel,
						$bubble,
						array_values($processorDefinitions),
					]);
			} else {
				$definition->addSetup('setLevel', [$handlerLevel]);
				$definition->addSetup('setBubble', [$bubble]);

				foreach (array_reverse($processorDefinitions) as $processorDefinition) {
					$definition->addSetup('pushProcessor', [$processorDefinition]);
				}
			}
		}
	}

	/**
	 * @return array<Definition|Reference>
	 */
	private function registerHandlerProcessors(
		string $handlerName,
		stdClass $handlerConfig,
		DefinitionsLoader $loader
	): array
	{
		$processorDefinitions = [];
		foreach ($handlerConfig->processors as $processorName => $processorConfig) {
			$processorDefinitions[$processorName] = $loader->loadDefinitionFromConfig(
				$processorConfig,
				$this->prefix("handler.$handlerName.processor.$processorName"),
			);
		}

		return $processorDefinitions;
	}

	/**
	 * @return array<Definition|Reference>
	 */
	private function registerProcessors(stdClass $config, DefinitionsLoader $loader): array
	{
		$processorDefinitions = [];
		foreach ($config->processors as $processorName => $processorConfig) {
			$processorDefinitions[$processorName] = $loader->loadDefinitionFromConfig(
				$processorConfig,
				$this->prefix("processor.$processorName"),
			);
		}

		return $processorDefinitions;
	}

	/**
	 * @param array<ServiceDefinition>    $channelDefinitions
	 * @param array<Definition|Reference> $processorDefinitions
	 */
	private function addProcessorsToChannels(
		array $channelDefinitions,
		array $processorDefinitions,
		stdClass $config
	): void
	{
		foreach ($channelDefinitions as $channelName => $channelDefinition) {
			$processorsConfig = $config->channels[$channelName]->processors;

			$filteredProcessorDefinitions = $this->filterAllowedDefinitions(
				$processorDefinitions,
				$processorsConfig->allowed,
				$processorsConfig->forbidden,
				"channels > $channelName > processors > allowed",
				"channels > $channelName > processors > forbidden",
				'processors',
			);

			foreach (array_reverse($filteredProcessorDefinitions) as $processorDefinition) {
				$channelDefinition->addSetup('pushProcessor', [$processorDefinition]);
			}
		}
	}

	/**
	 * Preconfigure Tracy handler with placeholder service so user don't have to configure it when bridge is enabled
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function configureTracyHandlers($value)
	{
		$handlers = [
			'tracyLogger',
			'tracyPanel',
		];

		foreach ($handlers as $handlerName) {
			if (isset($value['handlers'][$handlerName]['service'])) {
				$message = Message::create()
					->withContext("Trying to configure '$this->name > handlers > $handlerName > service'.")
					->withProblem('This options is reserved and cannot be changed.')
					->withSolution('Remove the option or choose different name for your handler.');

				throw InvalidArgument::create()
					->withMessage($message);
			}

			if (isset($value['handlers'][$handlerName]) && is_array($value['handlers'][$handlerName])) {
				$value['handlers'][$handlerName]['service'] = '_validation_bypass_';
			}
		}

		return $value;
	}

	private function processTracyLoggerHandlerConfig(stdClass $config): stdClass
	{
		if ($config->bridge->toTracy === false) {
			if (isset($config->handlers['tracyLogger']) && count((array) $config->handlers['tracyLogger']) !== 1) {
				$message = Message::create()
					->withContext("Trying to configure '$this->name > handlers > tracyLogger'.")
					->withProblem(
						"This option is reserved for Tracy handler and can be configured only when '$this->name > bridge > toTracy' is enabled.",
					)
					->withSolution("Set 'toTracy' option to `true` or remove Tracy handler configuration.");

				throw InvalidState::create()
					->withMessage($message);
			}

			unset($config->handlers['tracyLogger']);

			return $config;
		}

		if (!isset($config->handlers['tracyLogger'])) {
			$config->handlers['tracyLogger'] = $this->createDynamicHandlerPlaceholderConfig();
		}

		$config->handlers['tracyLogger']->service = new Statement(PsrHandler::class, [
			new Reference($this->prefix('bridge.psrToTracy')),
		]);

		$panel = $config->handlers['tracyLogger'];
		unset($config->handlers['tracyLogger']);
		$config->handlers = ['tracyLogger' => $panel] + $config->handlers;

		return $config;
	}

	private function processTracyPanelHandlerConfig(stdClass $config): stdClass
	{
		if ($config->bridge->tracyPanel === false) {
			return $config;
		}

		if (!isset($config->handlers['tracyPanel'])) {
			$config->handlers['tracyPanel'] = $this->createDynamicHandlerPlaceholderConfig();
		}

		$config->handlers['tracyPanel']->service = new Statement(TracyPanelHandler::class);

		$panel = $config->handlers['tracyPanel'];
		unset($config->handlers['tracyPanel']);
		$config->handlers = ['tracyPanel' => $panel] + $config->handlers;

		return $config;
	}

	private function createDynamicHandlerPlaceholderConfig(): stdClass
	{
		return (object) [
			'enabled' => true,
			'level' => (object) [
				'debug' => null,
				'production' => null,
			],
			'bubble' => true,
			'processors' => [],
		];
	}

	private function registerToTracyBridge(stdClass $config, ContainerBuilder $builder): void
	{
		if ($config->bridge->toTracy === false) {
			return;
		}

		$tracyLoggerDefinitionName = $builder->getByType(ILogger::class);
		if ($tracyLoggerDefinitionName === null) {
			$this->throwTracyBridgeRequiresTracyInstalled('toTracy');
		}

		$builder->addDefinition($this->prefix('bridge.psrToTracy'))
			->setFactory(
				TracyToPsrLoggerAdapter::class,
				[
					$builder->getDefinition($tracyLoggerDefinitionName),
				],
			)->setAutowired(false);
	}

	/**
	 * @param array<Definition> $channelDefinitions
	 */
	private function registerFromTracyBridge(
		array $channelDefinitions,
		stdClass $config,
		ContainerBuilder $builder
	): void
	{
		$fromTracyConfig = $config->bridge->fromTracy;

		if ($fromTracyConfig === []) {
			return;
		}

		$tracyLoggerDefinitionName = $builder->getByType(ILogger::class);
		if ($tracyLoggerDefinitionName === null) {
			$this->throwTracyBridgeRequiresTracyInstalled('fromTracy');
		}

		$tracyLoggerDefinition = $builder->getDefinition($tracyLoggerDefinitionName)
			->setAutowired(false);

		$tracyToPsrChannelKeys = $this->filterDefinitionsToServiceKeys(
			$channelDefinitions,
			$fromTracyConfig,
			'bridge > fromTracy',
			'channels',
		);

		$tracyToPsrDefinition = $builder->addDefinition($this->prefix('bridge.tracyToPsr'))
			->setFactory(LazyTracyToPsrLogger::class, [
				'serviceMap' => $tracyToPsrChannelKeys,
				'tracyOriginalLogger' => $tracyLoggerDefinition,
			]);

		$init = $this->getInitialization();

		// Workaround for Tracy logger service static creation - create original service to prevent lazy wrapper cyclic reference
		$init->addBody("\$this->getService('$tracyLoggerDefinitionName');");

		// Set lazy wrapper as a logger
		$init->addBody(Debugger::class . '::setLogger($this->getService(?));', [
			$tracyToPsrDefinition->getName(),
		]);
	}

	/**
	 * @param array<string, Definition|Reference> $handlerDefinitions
	 */
	private function registerTracyPanel(array $handlerDefinitions, ContainerBuilder $builder): void
	{
		$handlerDefinition = $handlerDefinitions['tracyPanel'] ?? null;

		if ($handlerDefinition === null) {
			return;
		}

		$tracyBarDefinitionName = $builder->getByType(Bar::class);
		if ($tracyBarDefinitionName === null) {
			$this->throwTracyBridgeRequiresTracyInstalled('tracyPanel');
		}

		assert($handlerDefinition instanceof ServiceDefinition);
		$handlerDefinition->addSetup(
			[self::class, 'setupTracyHandlerPanel'],
			[
				"$this->name.panel",
				$builder->getDefinition($tracyBarDefinitionName),
				$handlerDefinition,
			],
		);
	}

	public static function setupTracyHandlerPanel(
		string $name,
		Bar $bar,
		TracyPanelHandler $handler
	): void
	{
		$bar->addPanel($handler, $name);
	}

	/**
	 * @return never
	 */
	private function throwTracyBridgeRequiresTracyInstalled(string $option): void
	{
		$tracyLoggerClass = ILogger::class;
		$message = Message::create()
			->withContext("Trying to configure '$this->name > bridge > $option'.")
			->withProblem(
				"Option requires package 'tracy/tracy' to be installed and '$tracyLoggerClass' registered as a service.",
			)
			->withSolution('Ensure Tracy is installed and register as a service or remove the configuration.');

		throw InvalidState::create()
			->withMessage($message);
	}

	private function addFlusherToApplicationShutdown(
		ServiceDefinition $logFlusherDefinition,
		ContainerBuilder $builder
	): void
	{
		foreach ($builder->findByType(Application::class) as $applicationDefinition) {
			assert($applicationDefinition instanceof ServiceDefinition);

			$applicationDefinition->addSetup(
				'?->onShutdown[] = fn() => ?->reset()',
				[
					$applicationDefinition,
					$logFlusherDefinition,
				],
			);
		}
	}

	/**
	 * @return Reference|Definition
	 */
	private function tryResolveReference(Reference $reference)
	{
		$builder = $this->getContainerBuilder();
		$value = $reference->getValue();

		// Self reference should be impossible
		assert(!$reference->isSelf());

		try {
			return $reference->isType()
				? $builder->getDefinitionByType($value)
				: $builder->getDefinition($value);
		} catch (MissingServiceException $exception) {
			return $reference;
		}
	}

	/**
	 * @param array<string>     $requiredNames
	 * @param array<Definition> $definitions
	 * @return array<string, string>
	 */
	private function filterDefinitionsToServiceKeys(
		array $definitions,
		array $requiredNames,
		string $configOption,
		string $filtered
	): array
	{
		$serviceNamesAndKeys = [];
		$missingNames = [];

		foreach (array_unique($requiredNames) as $name) {
			$definition = $definitions[$name] ?? null;

			if ($definition === null) {
				$missingNames[] = $name;

				continue;
			}

			$key = $definition->getName();
			assert($key !== null);

			$serviceNamesAndKeys[$name] = $key;
		}

		if ($missingNames !== []) {
			$this->throwUnknownFilteredNames($missingNames, $configOption, $filtered);
		}

		return $serviceNamesAndKeys;
	}

	/**
	 * @param array<Definition|Reference> $definitions
	 * @param array<string>               $allowedList
	 * @param array<string>               $forbiddenList
	 * @return array<Definition|Reference>
	 */
	private function filterAllowedDefinitions(
		array $definitions,
		array $allowedList,
		array $forbiddenList,
		string $configOptionAllowed,
		string $configOptionForbidden,
		string $filtered
	): array
	{
		$filteredDefinitions = [];

		if ($allowedList === [] && $forbiddenList === []) {
			$filteredDefinitions = $definitions;
		} elseif ($allowedList !== []) {
			$missingAllowed = array_diff($allowedList, array_keys($definitions));
			if ($missingAllowed !== []) {
				$this->throwUnknownFilteredNames(
					$allowedList,
					$configOptionAllowed,
					$filtered,
				);
			}

			foreach ($definitions as $definitionName => $definition) {
				if (in_array($definitionName, $allowedList, true)) {
					$filteredDefinitions[$definitionName] = $definition;
				}
			}
		} else {
			$missingForbidden = array_diff($forbiddenList, array_keys($definitions));
			if ($missingForbidden !== []) {
				$this->throwUnknownFilteredNames(
					$forbiddenList,
					$configOptionForbidden,
					$filtered,
				);
			}

			foreach ($definitions as $definitionName => $definition) {
				if (!in_array($definitionName, $forbiddenList, true)) {
					$filteredDefinitions[$definitionName] = $definition;
				}
			}
		}

		return $filteredDefinitions;
	}

	/**
	 * @param array<string> $missingNames
	 * @return never
	 */
	private function throwUnknownFilteredNames(array $missingNames, string $configOption, string $filtered): void
	{
		$namesInline = implode(', ', $missingNames);

		$message = Message::create()
			->withContext("Trying to configure '$this->name > $configOption'.")
			->withProblem("Some of the given $filtered do not exist - '$namesInline'.")
			->withSolution("Register these $filtered or remove them from configured option.");

		throw InvalidArgument::create()
			->withMessage($message);
	}

}
