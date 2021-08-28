<?php declare(strict_types = 1);

namespace OriNette\Monolog\Bridge\Logtail;

use Monolog\Handler\AbstractProcessingHandler;
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

		$record['dt'] = $record['datetime'];
		unset($record['datetime']);

		$this->records[] = $record;
	}

	public function flush(): void
	{
		if ($this->records !== []) {
			$this->client->logBatch($this->records);
			$this->records = [];
		}
	}

	public function close(): void
	{
		$this->flush();
	}

	public function reset(): void
	{
		$this->flush();

		parent::reset();
	}

}
