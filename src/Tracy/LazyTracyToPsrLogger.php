<?php declare(strict_types = 1);

namespace OriNette\Monolog\Tracy;

use OriNette\DI\Services\ServiceManager;
use Psr\Log\LoggerInterface;
use Tracy\Bridges\Psr\PsrToTracyLoggerAdapter;
use Tracy\ILogger;

final class LazyTracyToPsrLogger extends ServiceManager implements ILogger
{

	/** @var array<PsrToTracyLoggerAdapter>|null */
	private ?array $loggers = null;

	/**
	 * @param mixed $value
	 * @param mixed $level
	 */
	public function log($value, $level = self::INFO): void
	{
		foreach ($this->getLoggers() as $logger) {
			$logger->log($value, $level);
		}
	}

	/**
	 * @return array<PsrToTracyLoggerAdapter>
	 */
	private function getLoggers(): array
	{
		if ($this->loggers !== null) {
			return $this->loggers;
		}

		$loggers = [];
		foreach ($this->getKeys() as $key) {
			$loggers[] = new PsrToTracyLoggerAdapter(
				$this->getTypedServiceOrThrow($key, LoggerInterface::class),
			);
		}

		return $this->loggers = $loggers;
	}

}
