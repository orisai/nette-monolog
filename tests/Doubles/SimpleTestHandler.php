<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Doubles;

use Monolog\ResettableInterface;

/**
 * Does not extend AbstractHandler intentionally
 */
class SimpleTestHandler extends UnresettableSimpleTestHandler implements ResettableInterface
{

	public function reset(): void
	{
		$this->records = [];
	}

}
