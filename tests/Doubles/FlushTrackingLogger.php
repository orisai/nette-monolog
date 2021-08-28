<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Doubles;

use Monolog\Logger;

final class FlushTrackingLogger extends Logger
{

	public int $resetCount = 0;

	public int $closeCount = 0;

	public function reset(): void
	{
		parent::reset();
		$this->resetCount++;
	}

	public function close(): void
	{
		parent::close();
		$this->closeCount++;
	}

}
