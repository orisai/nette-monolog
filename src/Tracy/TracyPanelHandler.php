<?php declare(strict_types = 1);

namespace OriNette\Monolog\Tracy;

use Monolog\Handler\AbstractProcessingHandler;
use Tracy\Helpers;
use Tracy\IBarPanel;

final class TracyPanelHandler extends AbstractProcessingHandler implements IBarPanel
{

	/** @var array<array<mixed>> */
	private array $records = [];

	public function getTab(): string
	{
		return Helpers::capture(function (): void {
			$records = $this->records;

			if ($records === []) {
				return;
			}

			require __DIR__ . '/TracyPanelHandler.tab.phtml';
		});
	}

	public function getPanel(): string
	{
		return Helpers::capture(function (): void {
			$records = $this->records;

			if ($records === []) {
				return;
			}

			require __DIR__ . '/TracyPanelHandler.panel.phtml';
		});
	}

	/**
	 * {@inheritDoc}
	 */
	protected function write(array $record): void
	{
		$this->records[] = $record;
	}

}
