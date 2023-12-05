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

	/**
	 * @phpstan-impure
	 */
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

	/**
	 * @phpstan-impure
	 */
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
		/** @infection-ignore-all */
		if ($record instanceof LogRecord) {
			$record = $record->toArray();
		}

		$this->records[] = $record;
	}

}
