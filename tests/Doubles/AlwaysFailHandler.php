<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Doubles;

use Monolog\Handler\HandlerInterface;
use Orisai\Exceptions\Logic\NotImplemented;

final class AlwaysFailHandler implements HandlerInterface
{

	/**
	 * {@inheritDoc}
	 */
	public function isHandling(array $record): bool
	{
		throw NotImplemented::create();
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle(array $record): bool
	{
		throw NotImplemented::create();
	}

	/**
	 * {@inheritDoc}
	 */
	public function handleBatch(array $records): void
	{
		throw NotImplemented::create();
	}

	public function close(): void
	{
		throw NotImplemented::create();
	}

}
