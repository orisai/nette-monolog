<?php declare(strict_types = 1);

namespace OriNette\Monolog\DI;

use Monolog\Logger;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use OriNette\DI\Definitions\DefinitionsLoader;
use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Message;
use stdClass;
use function array_reverse;
use function is_a;
use function is_string;

/**
 * @property-read stdClass $config
 */
final class MonologExtension extends CompilerExtension
{

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
				]),
				Expect::string(),
			),
			'processors' => Expect::arrayOf(
				Expect::anyOf(Expect::string(), Expect::array(), Expect::type(Statement::class)),
				Expect::string(),
			),
		]);
	}

	public function loadConfiguration(): void
	{
		parent::loadConfiguration();

		$builder = $this->getContainerBuilder();
		$config = $this->config;
		$loader = new DefinitionsLoader($this->compiler);

		// Setup channels
		$channelDefinitions = [];
		$channelsConfig = $config->channels;
		foreach ($channelsConfig as $channelName => $channelConfig) {
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

			$channelDefinitions[] = $builder->addDefinition($this->prefix("channel.$channelName"))
				->setFactory(
					is_string($autowired) ? $autowired : $loggerClass,
					[
						'name' => $channelName,
					],
				)
				->setAutowired($autowired);
		}

		// Setup handlers
		$handlerDefinitions = [];
		$handlersConfig = $config->handlers;
		foreach ($handlersConfig as $handlerName => $handlerConfig) {
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

}
