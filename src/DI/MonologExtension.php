<?php declare(strict_types = 1);

namespace OriNette\Monolog\DI;

use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use OriNette\DI\Definitions\DefinitionsLoader;
use OriNette\Monolog\Tracy\LazyTracyToPsrLogger;
use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\Exceptions\Message;
use stdClass;
use Tracy\Bridges\Psr\TracyToPsrLoggerAdapter;
use Tracy\Debugger;
use Tracy\ILogger;
use function array_reverse;
use function array_unique;
use function assert;
use function count;
use function implode;
use function is_a;
use function is_array;
use function is_string;

/**
 * @property-read stdClass $config
 */
final class MonologExtension extends CompilerExtension
{

	/** @var array<ServiceDefinition> */
	private array $channelDefinitions;

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'channels' => Expect::arrayOf(
				Expect::structure([
					'autowired' => Expect::anyOf(
						Expect::bool(),
						Expect::string(),
					)->default(false),
				]),
				Expect::string(),
			),
			'handlers' => Expect::arrayOf(
				Expect::structure([
					'service' => Expect::anyOf(Expect::string(), Expect::array(), Expect::type(Statement::class)),
				])->otherItems(), // TODO - temporary workaround
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

	/**
	 * Preconfigure Tracy handler with placeholder service so user don't have to configure it when bridge is enabled
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function configureTracyHandler($value)
	{
		if (is_array($value['handlers']['tracy'] ?? [])) {
			if (isset($value['handlers']['tracy']['service'])) {
				$message = Message::create()
					->withContext("Trying to configure '$this->name > handlers > tracy > service'.")
					->withProblem('This options is reserved and cannot be changed.')
					->withSolution('Remove the option or choose different name for your handler.');

				throw InvalidArgument::create()
					->withMessage($message);
			}

			$value['handlers']['tracy']['service'] = '_validation_bypass_';
		}

		return $value;
	}

	public function loadConfiguration(): void
	{
		parent::loadConfiguration();

		$builder = $this->getContainerBuilder();
		$config = $this->config;
		$loader = new DefinitionsLoader($this->compiler);

		// Setup channels
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

		$this->channelDefinitions = $channelDefinitions;

		// Setup handlers
		// - process Tracy config
		$config = $this->processTracyHandlerConfig($config);

		// - register handlers as services
		$handlerDefinitions = [];
		foreach ($config->handlers as $handlerName => $handlerConfig) {
			$handlerDefinitions[] = $loader->loadDefinitionFromConfig(
				$handlerConfig->service,
				$this->prefix("handler.$handlerName"),
			);
		}

		// Add handlers to channels
		foreach ($channelDefinitions as $channelDefinition) {
			$channelDefinition->addSetup('setHandlers', [$handlerDefinitions]);
		}

		// Setup channel processors
		$processorDefinitions = [];
		$processorsConfig = $config->processors;
		foreach ($processorsConfig as $processorName => $processorConfig) {
			$processorDefinitions[] = $loader->loadDefinitionFromConfig(
				$processorConfig,
				$this->prefix("processor.$processorName"),
			);
		}

		// Add processors to channels
		$processorDefinitions = array_reverse($processorDefinitions);
		foreach ($channelDefinitions as $channelDefinition) {
			foreach ($processorDefinitions as $processorDefinition) {
				$channelDefinition->addSetup('pushProcessor', [$processorDefinition]);
			}
		}
	}

	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();
		$config = $this->config;

		// Tracy may not be available in loadConfiguration(), depending on extension order
		$this->registerFromTracyBridge($this->channelDefinitions, $config, $builder);
		$this->checkTracyHandlerRequiredService($config, $builder);
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

		$tracyAdapter = new Statement(TracyToPsrLoggerAdapter::class, [
			Reference::fromType(ILogger::class),
		]);

		$config->handlers['tracy']->service = new Statement(PsrHandler::class, [
			$tracyAdapter,
		]);

		return $config;
	}

	private function checkTracyHandlerRequiredService(stdClass $config, ContainerBuilder $builder): void
	{
		if ($config->bridge->toTracy === false) {
			return;
		}

		$tracyLoggerDefinitionName = $builder->getByType(ILogger::class);
		if ($tracyLoggerDefinitionName === null) {
			$this->throwTracyBridgeRequiresTracyInstalled('toTracy');
		}
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
