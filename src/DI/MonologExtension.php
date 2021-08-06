<?php declare(strict_types = 1);

namespace OriNette\Monolog\DI;

use Monolog\Logger;
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Orisai\Exceptions\Logic\InvalidArgument;
use Orisai\Exceptions\Message;
use stdClass;
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
		]);
	}

	public function loadConfiguration(): void
	{
		parent::loadConfiguration();

		$builder = $this->getContainerBuilder();
		$config = $this->config;

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

			$builder->addDefinition($this->prefix("channel.$channelName"))
				->setFactory(
					is_string($autowired) ? $autowired : $loggerClass,
					[
						'name' => $channelName,
					],
				)
				->setAutowired($autowired);
		}
	}

}
