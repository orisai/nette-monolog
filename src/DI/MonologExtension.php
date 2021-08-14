<?php declare(strict_types = 1);

namespace OriNette\Monolog\DI;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
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
use OriNette\Monolog\Tracy\LazyTracyToPsrLogger;
use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\Exceptions\Message;
use Psr\Log\LogLevel;
use stdClass;
use Tracy\Bridges\Psr\TracyToPsrLoggerAdapter;
use Tracy\Debugger;
use Tracy\ILogger;
use function array_reverse;
use function array_unique;
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

	private const LOG_LEVELS = [
		LogLevel::DEBUG,
		LogLevel::INFO,
		LogLevel::NOTICE,
		LogLevel::WARNING,
		LogLevel::ERROR,
		LogLevel::CRITICAL,
		LogLevel::ALERT,
		LogLevel::EMERGENCY,
	];

	/** @var array<ServiceDefinition> */
	private array $channelDefinitions;

	/** @var array<Definition|Reference> */
	private array $handlerDefinitions;

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'debug' => Expect::bool()->required(),
			'level' => Expect::structure([
				'debug' => Expect::anyOf(...self::LOG_LEVELS)->default(LogLevel::DEBUG),
				'production' => Expect::anyOf(...self::LOG_LEVELS)->default(LogLevel::WARNING),
			]),
			'channels' => Expect::arrayOf(
				Expect::structure([
					'autowired' => Expect::anyOf(
						Expect::bool(),
						Expect::string(),
					)->default(false),
					'allowedHandlers' => Expect::arrayOf(Expect::string()),
					'forbiddenHandlers' => Expect::arrayOf(Expect::string()),
				])->assert(
					static fn (stdClass $value): bool => !($value->allowedHandlers !== [] && $value->forbiddenHandlers !== []),
					'Use only allowedHandlers or forbiddenHandlers, these options are incompatible.',
				),
				Expect::string(),
			),
			'handlers' => Expect::arrayOf(
				Expect::structure([
					'enabled' => Expect::bool(true),
					'service' => Expect::anyOf(Expect::string(), Expect::array(), Expect::type(Statement::class)),
					'level' => Expect::structure([
						'debug' => Expect::anyOf(null, ...self::LOG_LEVELS),
						'production' => Expect::anyOf(null, ...self::LOG_LEVELS),
					]),
				]),
				Expect::string(),
			),
			'processors' => Expect::arrayOf(
				Expect::anyOf(Expect::string(), Expect::array(), Expect::type(Statement::class)),
				Expect::string(),
			),
			'bridge' => Expect::structure([
				'fromTracy' => Expect::arrayOf(Expect::string()),
				'toTracy' => Expect::bool(false),
			]),
		])->before(fn ($value) => $this->configureTracyHandler($value));
	}

	public function loadConfiguration(): void
	{
		parent::loadConfiguration();

		$builder = $this->getContainerBuilder();
		$config = $this->config;
		$loader = new DefinitionsLoader($this->compiler);

		$channelDefinitions = $this->registerChannels($config, $builder);

		$config = $this->processTracyHandlerConfig($config);
		$handlerDefinitions = $this->registerHandlers($config, $loader);
		$this->addHandlersToChannels($channelDefinitions, $handlerDefinitions, $config);

		$this->registerProcessors($channelDefinitions, $config, $loader);
	}

	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();
		$config = $this->config;

		$this->configureHandlers($config);

		// Tracy may not be available in loadConfiguration(), depending on extension order
		$this->registerToTracyBridge($config, $builder);
		$this->registerFromTracyBridge($this->channelDefinitions, $config, $builder);
	}

	/**
	 * @return array<ServiceDefinition>
	 */
	private function registerChannels(stdClass $config, ContainerBuilder $builder): array
	{
		$channelDefinitions = [];
		foreach ($config->channels as $channelName => $channelConfig) {
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
					],
				)
				->setAutowired($autowired);
		}

		return $this->channelDefinitions = $channelDefinitions;
	}

	/**
	 * @return array<Definition|Reference>
	 */
	private function registerHandlers(stdClass $config, DefinitionsLoader $loader): array
	{
		$handlerDefinitions = [];
		foreach ($config->handlers as $handlerName => $handlerConfig) {
			if ($handlerConfig->enabled !== true) {
				continue;
			}

			$handlerDefinitions[$handlerName] = $loader->loadDefinitionFromConfig(
				$handlerConfig->service,
				$this->prefix("handler.$handlerName"),
			);
		}

		return $this->handlerDefinitions = $handlerDefinitions;
	}

	/**
	 * @param array<ServiceDefinition> $channelDefinitions
	 * @param array<Definition|Reference> $handlerDefinitions
	 */
	private function addHandlersToChannels(array $channelDefinitions, array $handlerDefinitions, stdClass $config): void
	{
		foreach ($channelDefinitions as $channelName => $channelDefinition) {
			$filteredHandlerDefinitions = [];

			$allowedHandlers = $config->channels[$channelName]->allowedHandlers;
			$forbiddenHandlers = $config->channels[$channelName]->forbiddenHandlers;

			if ($allowedHandlers === [] && $forbiddenHandlers === []) {
				$filteredHandlerDefinitions = $handlerDefinitions;
			} elseif ($allowedHandlers !== []) {
				foreach ($handlerDefinitions as $handlerName => $handlerDefinition) {
					if (in_array($handlerName, $allowedHandlers, true)) {
						$filteredHandlerDefinitions[$handlerName] = $handlerDefinition;
					}
				}
			} else {
				foreach ($handlerDefinitions as $handlerName => $handlerDefinition) {
					if (!in_array($handlerName, $forbiddenHandlers, true)) {
						$filteredHandlerDefinitions[$handlerName] = $handlerDefinition;
					}
				}
			}

			$channelDefinition->addSetup('setHandlers', [$filteredHandlerDefinitions]);
		}
	}

	private function configureHandlers(stdClass $config): void
	{
		$defaultLevel = $config->debug === false
			? $config->level->production
			: $config->level->debug;

		foreach ($this->handlerDefinitions as $name => $definition) {
			$handlerConfig = $config->handlers[$name];

			$handlerDebugLevel = $handlerConfig->level->debug;
			$handlerProductionLevel = $handlerConfig->level->production;

			if ($definition instanceof Reference) {
				$definition = $this->tryResolveReference($definition);
			}

			if (
				!$definition instanceof ServiceDefinition
				|| ($type = $definition->getType()) === null
				|| !is_a($type, AbstractHandler::class, true)
			) {
				if ($handlerDebugLevel === null && $handlerProductionLevel === null) {
					continue;
				}

				throw InvalidState::create()
					->withMessage(
						"'$this->name > handlers > $name > service' either does not implement AbstractHandler "
						. 'or is not ServiceDefinition or definition does not contain type or cannot be resolved.',
					);
			}

			$handlerLevel = $config->debug === false
				? $handlerProductionLevel
				: $handlerDebugLevel;

			$definition->addSetup('setLevel', [$handlerLevel ?? $defaultLevel]);
		}
	}

	/**
	 * @param array<ServiceDefinition> $channelDefinitions
	 */
	private function registerProcessors(array $channelDefinitions, stdClass $config, DefinitionsLoader $loader): void
	{
		$processorDefinitions = [];
		foreach ($config->processors as $processorName => $processorConfig) {
			$processorDefinitions[$processorName] = $loader->loadDefinitionFromConfig(
				$processorConfig,
				$this->prefix("processor.$processorName"),
			);
		}

		$processorDefinitions = array_reverse($processorDefinitions);
		foreach ($channelDefinitions as $channelDefinition) {
			foreach ($processorDefinitions as $processorDefinition) {
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
	private function configureTracyHandler($value)
	{
		if (isset($value['handlers']['tracy']['service'])) {
			$message = Message::create()
				->withContext("Trying to configure '$this->name > handlers > tracy > service'.")
				->withProblem('This options is reserved and cannot be changed.')
				->withSolution('Remove the option or choose different name for your handler.');

			throw InvalidArgument::create()
				->withMessage($message);
		}

		if (isset($value['handlers']['tracy']) && is_array($value['handlers']['tracy'])) {
			$value['handlers']['tracy']['service'] = '_validation_bypass_';
		}

		return $value;
	}

	private function processTracyHandlerConfig(stdClass $config): stdClass
	{
		if ($config->bridge->toTracy === false) {
			if (isset($config->handlers['tracy']) && count((array) $config->handlers['tracy']) !== 1) {
				$message = Message::create()
					->withContext("Trying to configure '$this->name > handlers > tracy'.")
					->withProblem(
						"This option is reserved for Tracy handler and can be configured only when '$this->name > bridge > toTracy' is enabled.",
					)
					->withSolution("Set 'toTracy' option to `true` or remove Tracy handler configuration.");

				throw InvalidState::create()
					->withMessage($message);
			}

			unset($config->handlers['tracy']);

			return $config;
		}

		if (!isset($config->handlers['tracy'])) {
			$config->handlers['tracy'] = (object) [
				'enabled' => true,
				'level' => (object) [
					'debug' => null,
					'production' => null,
				],
			];
		}

		$config->handlers['tracy']->service = new Statement(PsrHandler::class, [
			new Reference($this->prefix('bridge.psrToTracy')),
		]);

		return $config;
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

		$builder->getDefinition($tracyLoggerDefinitionName)
			->setAutowired(false);

		$tracyToPsrChannelKeys = $this->filterDefinitionsToServiceKeys(
			$channelDefinitions,
			$fromTracyConfig,
			'bridge > fromTracy',
			'channels',
		);

		$tracyToPsrDefinition = $builder->addDefinition($this->prefix('bridge.tracyToPsr'))
			->setFactory(LazyTracyToPsrLogger::class, [
				$tracyToPsrChannelKeys,
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
			$namesInline = implode(', ', $missingNames);

			$message = Message::create()
				->withContext("Trying to configure '$this->name > $configOption'.")
				->withProblem("Some of the given $filtered do not exist - '$namesInline'.")
				->withSolution("Register these $filtered or remove them from configured option.");

			throw InvalidArgument::create()
				->withMessage($message);
		}

		return $serviceNamesAndKeys;
	}

}
