<?php declare(strict_types = 1);

namespace OriNette\Monolog;

use Nette\DI\Container;
use OriNette\Monolog\DI\MonologExtension;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\Exceptions\Message;
use Psr\Log\LoggerInterface;
use function assert;

final class StaticLoggerGetter
{

	private static ?string $serviceName = null;

	private static Container $container;

	private static ?LoggerInterface $logger = null;

	private function __construct()
	{
		// Static class
	}

	public static function set(string $serviceName, Container $container): void
	{
		self::$serviceName = $serviceName;
		self::$container = $container;
	}

	public static function get(): LoggerInterface
	{
		if (self::$logger !== null) {
			return self::$logger;
		}

		if (self::$serviceName === null) {
			$selfClass = self::class;
			$extClass = MonologExtension::class;
			$message = Message::create()
				->withContext("Trying to get logger from $selfClass.")
				->withProblem('Logger is not set.')
				->withSolution("Enable getter via 'staticGetter' option of $extClass.");

			throw InvalidState::create()
				->withMessage($message);
		}

		$logger = self::$container->getService(self::$serviceName);
		assert($logger instanceof LoggerInterface);

		return self::$logger = $logger;
	}

}
