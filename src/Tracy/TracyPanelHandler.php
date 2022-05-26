<?php declare(strict_types = 1);

namespace OriNette\Monolog\Tracy;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
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
	 * @param array<mixed>|LogRecord $record
	 */
	protected function write($record): void
	{
		if ($record instanceof LogRecord) {
			$record = $record->toArray();
		}

		$this->records[] = $record;
	}

}
