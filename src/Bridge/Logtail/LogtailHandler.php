<?php declare(strict_types = 1);

namespace OriNette\Monolog\Bridge\Logtail;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\BufferHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Psr\Log\LogLevel;
use function register_shutdown_function;

final class LogtailHandler extends AbstractProcessingHandler
{

	private LogtailClient $client;

	private bool $initialized = false;

	/** @var array<array<mixed>> */
	private array $records = [];

	/**
	 * @param int|string|Level|LogLevel::* $level
	 * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
	 */
	public function __construct(LogtailClient $client, $level = Logger::DEBUG, bool $bubble = true)
	{
		parent::__construct($level, $bubble);
		$this->client = $client;
	}

	/**
	 * @param array<mixed>|LogRecord $record
	 */
	protected function write($record): void
	{
		if (!$this->initialized) {
			// __destruct() is not called on fatal errors
			register_shutdown_function(fn () => $this->close());
			$this->initialized = true;
		}

		if ($record instanceof LogRecord) {
			$record = $record->toArray();
		}

		$record = $record['formatted'];

		$record['dt'] = $record['datetime'];
		unset($record['datetime']);

		$this->records[] = $record;
	}

	private function flush(): void
	{
		if ($this->records !== []) {
			$this->client->log($this->records);
			$this->records = [];
		}
	}

	public function close(): void
	{
		$this->flush();

		parent::close();
	}

	public function reset(): void
	{
		$this->flush();

		parent::reset();
	}

	protected function getDefaultFormatter(): FormatterInterface
	{
		return new NormalizerFormatter();
	}

	/**
	 * @see BufferHandler::__destruct()
	 */
	public function __destruct()
	{
		// Parent is not called intentionally
	}

}
