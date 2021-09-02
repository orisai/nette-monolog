<?php declare(strict_types = 1);

namespace OriNette\Monolog;

use OriNette\Monolog\DI\MonologExtension;
use Orisai\Exceptions\Logic\InvalidState;
use Orisai\Exceptions\Message;
use Psr\Log\LoggerInterface;

final class LoggerGetter
{

	private static ?LoggerInterface $logger = null;

	private function __construct()
	{
		// Static class
	}

	public static function set(LoggerInterface $logger): void
	{
		self::$logger = $logger;
	}

	public static function get(): LoggerInterface
	{
		if (self::$logger === null) {
			$selfClass = self::class;
			$extClass = MonologExtension::class;
			$message = Message::create()
				->withContext("Trying to get logger from $selfClass.")
				->withProblem('Logger is not set.')
				->withSolution("Enable getter via 'staticLogger' option of $extClass.");

			throw InvalidState::create()
				->withMessage($message);
		}

		return self::$logger;
	}

}
