<?php declare(strict_types = 1);

namespace OriNette\Monolog\Tracy;

use OriNette\DI\Services\ServiceManager;
use Psr\Log\LoggerInterface;
use Tracy\Bridges\Psr\PsrToTracyLoggerAdapter;
use Tracy\ILogger;
use function assert;

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
			$service = $this->getService($key);

			// Can't happen when iterating over getKeys()
			assert($service !== null);

			if (!$service instanceof LoggerInterface) {
				$this->throwInvalidServiceType($key, LoggerInterface::class, $service);
			}

			$loggers[] = new PsrToTracyLoggerAdapter($service);
		}

		return $this->loggers = $loggers;
	}

}
