<?php declare(strict_types = 1);

namespace OriNette\Monolog;

use Monolog\Logger;
use OriNette\DI\Services\ServiceManager;

final class LogFlusher extends ServiceManager
{

	/** @var array<int, int|string>|null */
	private ?array $uninitialized = null;

	/** @var array<int, Logger> */
	private array $loggers = [];

	/**
	 * @see Logger::close()
	 */
	public function close(): void
	{
		foreach ($this->getLoggers() as $logger) {
			$logger->close();
		}
	}

	/**
	 * @see Logger::reset()
	 */
	public function reset(): void
	{
		foreach ($this->getLoggers() as $logger) {
			$logger->reset();
		}
	}

	/**
	 * @return array<Logger>
	 */
	private function getLoggers(): array
	{
		if ($this->uninitialized === null) {
			$this->uninitialized = $this->getKeys();
		}

		foreach ($this->uninitialized as $i => $key) {
			if ($this->isCreated($key)) {
				$this->loggers[] = $this->getTypedServiceOrThrow($key, Logger::class);
				unset($this->uninitialized[$i]);
			}
		}

		return $this->loggers;
	}

	/**
	 * @param int|string $key
	 */
	private function isCreated($key): bool
	{
		return $this->container->isCreated(
			$this->getServiceName($key),
		);
	}

}
