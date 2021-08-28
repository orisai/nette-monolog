<?php declare(strict_types = 1);

namespace OriNette\Monolog\Bridge\Logtail;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\BufferHandler;
use Monolog\Logger;
use function register_shutdown_function;

final class LogtailHandler extends AbstractProcessingHandler
{

	private LogtailClient $client;

	private bool $initialized = false;

	/** @var array<array<mixed>> */
	private array $records = [];

	/**
	 * {@inheritDoc}
	 */
	public function __construct(LogtailClient $client, $level = Logger::DEBUG, bool $bubble = true)
	{
		parent::__construct($level, $bubble);
		$this->client = $client;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function write(array $record): void
	{
		if (!$this->initialized) {
			// __destruct() is not called on fatal errors
			register_shutdown_function(fn () => $this->close());
			$this->initialized = true;
		}

		$record = $record['formatted'];

		$record['dt'] = $record['datetime'];
		unset($record['datetime']);

		$this->records[] = $record;
	}

	private function flush(): void
	{
		if ($this->records !== []) {
			$this->client->logBatch($this->records);
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
