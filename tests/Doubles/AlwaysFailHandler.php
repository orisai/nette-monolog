<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Doubles;

use Monolog\Handler\HandlerInterface;
use Monolog\LogRecord;
use Orisai\Exceptions\Logic\NotImplemented;

final class AlwaysFailHandler implements HandlerInterface
{

	/**
	 * @param array<mixed>|LogRecord $record
	 */
	public function isHandling($record): bool
	{
		throw NotImplemented::create();
	}

	/**
	 * @param array<mixed>|LogRecord $record
	 */
	public function handle($record): bool
	{
		throw NotImplemented::create();
	}

	public function handleBatch(array $records): void
	{
		throw NotImplemented::create();
	}

	public function close(): void
	{
		throw NotImplemented::create();
	}

}
