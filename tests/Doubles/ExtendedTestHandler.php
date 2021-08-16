<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Doubles;

use Monolog\Handler\TestHandler;
use Monolog\Processor\ProcessorInterface;

final class ExtendedTestHandler extends TestHandler
{

	/**
	 * @return array<ProcessorInterface|callable(array): array>
	 */
	public function getProcessors(): array
	{
		return $this->processors;
	}

}
