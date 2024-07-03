<?php declare(strict_types = 1);

namespace OriNette\Monolog\Tracy;

use Nette\DI\Container;
use OriNette\DI\Services\ServiceManager;
use Orisai\Exceptions\Logic\MemberInaccessible;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;
use Tracy\Dumper;
use Tracy\ILogger;
use function get_class;
use function is_string;
use function trim;

final class LazyTracyToPsrLogger extends ServiceManager implements ILogger
{

	private const LevelMap = [
		ILogger::DEBUG => LogLevel::DEBUG,
		ILogger::INFO => LogLevel::INFO,
		ILogger::WARNING => LogLevel::WARNING,
		ILogger::ERROR => LogLevel::ERROR,
		ILogger::EXCEPTION => LogLevel::ERROR,
		ILogger::CRITICAL => LogLevel::CRITICAL,
	];

	/** @var array<LoggerInterface>|null */
	private ?array $loggers = null;

	private ?ILogger $tracyOriginalLogger;

	public function __construct(array $serviceMap, Container $container, ?ILogger $tracyOriginalLogger = null)
	{
		parent::__construct($serviceMap, $container);
		$this->tracyOriginalLogger = $tracyOriginalLogger;
	}

	/**
	 * @param mixed $value
	 * @param string $level
	 *
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
	 */
	public function log($value, $level = self::INFO): void
	{
		[$mappedLevel, $message, $context] = $this->transform($value, $level);

		foreach ($this->getLoggers() as $logger) {
			$logger->log($mappedLevel, $message, $context);
		}
	}

	/**
	 * @param mixed $value
	 * @return array{LogLevel::*, string, array<mixed>}
	 */
	private function transform($value, string $level): array
	{
		if (isset(self::LevelMap[$level])) {
			$mappedLevel = self::LevelMap[$level];
		} elseif ($value instanceof Throwable) {
			$mappedLevel = LogLevel::ERROR;
		} else {
			$mappedLevel = LogLevel::INFO;
		}

		if ($value instanceof Throwable) {
			$code = $value->getCode();
			$exceptionMessage = $value->getMessage();
			$message = get_class($value)
				. ':'
				. ($exceptionMessage !== '' ? " $exceptionMessage" : '')
				. ($code ? " #$code" : '')
				. " in {$value->getFile()}:{$value->getLine()}";
			$context = ['exception' => $value];
		} elseif (is_string($value)) {
			$message = $value;
			$context = [];
		} else {
			$message = trim(Dumper::toText($value));
			$context = [];
		}

		return [
			$mappedLevel,
			$message,
			$context,
		];
	}

	/**
	 * @return array<LoggerInterface>
	 */
	private function getLoggers(): array
	{
		if ($this->loggers !== null) {
			return $this->loggers;
		}

		$loggers = [];
		foreach ($this->getKeys() as $key) {
			$loggers[] = $this->getTypedServiceOrThrow($key, LoggerInterface::class);
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
