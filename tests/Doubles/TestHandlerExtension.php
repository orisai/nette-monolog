<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Doubles;

use Monolog\Handler\TestHandler;
use Nette\DI\CompilerExtension;

final class TestHandlerExtension extends CompilerExtension
{

	private string $definitionName;

	public function __construct(string $definitionName)
	{
		$this->definitionName = $definitionName;
	}

	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->definitionName)
			->setFactory(TestHandler::class);
	}

}
