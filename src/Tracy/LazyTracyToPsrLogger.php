<?php declare(strict_types = 1);

namespace OriNette\Monolog\Tracy;

use Nette\DI\Container;
use OriNette\DI\Services\ServiceManager;
use Orisai\Exceptions\Logic\MemberInaccessible;
use Psr\Log\LoggerInterface;
use Tracy\Bridges\Psr\PsrToTracyLoggerAdapter;
use Tracy\ILogger;

final class LazyTracyToPsrLogger extends ServiceManager implements ILogger
{

	/** @var array<PsrToTracyLoggerAdapter>|null */
	private ?array $loggers = null;

	private ?ILogger $tracyOriginalLogger;

	public function __construct(array $serviceMap, Container $container, ?ILogger $tracyOriginalLogger = null)
	{
		parent::__construct($serviceMap, $container);
		$this->tracyOriginalLogger = $tracyOriginalLogger;
	}

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

	/**
	 * @param mixed $value
	 */
	public function __set(string $name, $value): void
	{
		if ($this->tracyOriginalLogger === null) {
			$class = self::class;

			throw MemberInaccessible::create()
				->withMessage("Cannot write to an undeclared property $class::\$$name");
		}

		$this->tracyOriginalLogger->$name = $value;
	}

	/**
	 * @return mixed
	 */
	public function __get(string $name)
	{
		if ($this->tracyOriginalLogger === null) {
			$class = self::class;

			throw MemberInaccessible::create()
				->withMessage("Cannot read an undeclared property $class::\$$name");
		}

		return $this->tracyOriginalLogger->$name;
	}

	public function __isset(string $name): bool
	{
		if ($this->tracyOriginalLogger === null) {
			return false;
		}

		return isset($this->tracyOriginalLogger->$name);
	}

	/**
	 * @param array<mixed> $arguments
	 * @return mixed
	 */
	public function __call(string $name, array $arguments)
	{
		if ($this->tracyOriginalLogger === null) {
			$class = self::class;

			throw MemberInaccessible::create()
				->withMessage("Call to undefined or non-public method $class::$name()");
		}

		return $this->tracyOriginalLogger->$name(...$arguments);
	}

}
