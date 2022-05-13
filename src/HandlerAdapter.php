<?php declare(strict_types = 1);

namespace OriNette\Monolog;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Handler\ProcessableHandlerTrait;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Monolog\ResettableInterface;

/**
 * @internal
 */
final class HandlerAdapter implements HandlerInterface, ProcessableHandlerInterface, ResettableInterface
{

	use ProcessableHandlerTrait;

	private HandlerInterface $handler;

	private int $level;

	private bool $bubble;

	/**
	 * @param array<ProcessorInterface> $processors
	 * @phpstan-param int|Level         $level
	 */
	public function __construct(HandlerInterface $handler, $level, bool $bubble, array $processors)
	{
		$this->handler = $handler;
		$this->level = $level instanceof Level ? $level->value : $level;
		$this->bubble = $bubble;
		$this->processors = $processors;
	}

	/**
	 * @param array<mixed>|LogRecord $record
	 */
	public function isHandling($record): bool
	{
		return $record instanceof LogRecord
			? $record->level->value >= $this->level
			: $record['level'] >= $this->level;
	}

	/**
	 * @param array<mixed>|LogRecord $record
	 */
	public function handle($record): bool
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
	 * @phpstan-return array<(callable(LogRecord): LogRecord)|ProcessorInterface>
	 */
	public function getProcessors(): array
	{
		return $this->processors;
	}

}
