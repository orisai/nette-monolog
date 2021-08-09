<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Doubles;

use Monolog\Handler\TestHandler;
use Nette\DI\CompilerExtension;

final class TestHandlerExtension extends CompilerExtension
{

	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();

		$builder->addDefinition('h_a')
			->setFactory(TestHandler::class);
	}

}
