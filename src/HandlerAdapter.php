<?php declare(strict_types = 1);

namespace OriNette\Monolog;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Handler\ProcessableHandlerTrait;
use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;
use Monolog\ResettableInterface;

/**
 * @phpstan-import-type Record from Logger
 * @phpstan-import-type Level from Logger
 *
 * @internal
 */
final class HandlerAdapter implements HandlerInterface, ProcessableHandlerInterface, ResettableInterface
{

	use ProcessableHandlerTrait;

	private HandlerInterface $handler;

	/** @phpstan-var Level */
	private int $level;

	private bool $bubble;

	/**
	 * @param array<ProcessorInterface> $processors
	 * @phpstan-param Level             $level
	 */
	public function __construct(HandlerInterface $handler, int $level, bool $bubble, array $processors)
	{
		$this->handler = $handler;
		$this->level = $level;
		$this->bubble = $bubble;
		$this->processors = $processors;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isHandling(array $record): bool
	{
		return $record['level'] >= $this->level;
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle(array $record): bool
	{
		if (!$this->isHandling($record)) {
			return false;
		}

		$record = $this->processRecord($record);

		$handled = $this->handler->handle($record);
		if (!$handled) {
			return false;
		}

		return $this->bubble === false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function handleBatch(array $records): void
	{
		foreach ($records as $record) {
			$this->handle($record);
		}
	}

	public function close(): void
	{
		$this->handler->close();
	}

	public function reset(): void
	{
		if ($this->handler instanceof ResettableInterface) {
			$this->handler->reset();
		}

		$this->resetProcessors();
	}

	public function getHandler(): HandlerInterface
	{
		return $this->handler;
	}

	/**
	 * @return array<callable>
	 * @phpstan-return array<ProcessorInterface|callable(Record): Record>
	 */
	public function getProcessors(): array
	{
		return $this->processors;
	}

}
