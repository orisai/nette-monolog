<?php declare(strict_types = 1);

namespace OriNette\Monolog\Tracy;

use Tracy\Bridges\Psr\TracyToPsrLoggerAdapter;

/**
 * @internal
 */
final class ToggleableTracyToPsrLoggerAdapter extends TracyToPsrLoggerAdapter
{

	public bool $enabled = true;

	/**
	 * @param mixed $message
	 * @param array<mixed> $context
	 */
	public function log($level, $message, array $context = []): void
	{
		if (!$this->enabled) {
			return;
		}

		parent::log($level, $message, $context);
	}

}
