<h1 align="center">
	<img src="https://github.com/orisai/.github/blob/main/images/repo_title.png?raw=true" alt="Orisai"/>
	<br/>
	Nette Monolog
</h1>

<p align="center">
	<a href="https://github.com/Seldaek/monolog">Monolog</a> logger integration for Nette
</p>

<p align="center">
	📄 Check out our <a href="docs/README.md">documentation</a>.
</p>

<p align="center">
	💸 If you like Orisai, please <a href="https://orisai.dev/sponsor">make a donation</a>. Thank you!
</p>

<p align="center">
	<a href="https://github.com/orisai/nette-monolog/actions?query=workflow%3ACI">
		<img src="https://github.com/orisai/nette-monolog/workflows/CI/badge.svg">
	</a>
	<a href="https://coveralls.io/r/orisai/nette-monolog">
		<img src="https://badgen.net/coveralls/c/github/orisai/nette-monolog/v1.x?cache=300">
	</a>
	<a href="https://dashboard.stryker-mutator.io/reports/github.com/orisai/nette-monolog/v1.x">
		<img src="https://badge.stryker-mutator.io/github.com/orisai/nette-monolog/v1.x">
	</a>
	<a href="https://packagist.org/packages/orisai/nette-monolog">
		<img src="https://badgen.net/packagist/dt/orisai/nette-monolog?cache=3600">
	</a>
	<a href="https://packagist.org/packages/orisai/nette-monolog">
		<img src="https://badgen.net/packagist/v/orisai/nette-monolog?cache=3600">
	</a>
	<a href="https://choosealicense.com/licenses/mpl-2.0/">
		<img src="https://badgen.net/badge/license/MPL-2.0/blue?cache=3600">
	</a>
<p>

##

```php
use Psr\Log\LoggerInterface;

final class ImportantCode
{

	private LoggerInterface $logger;

	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	public function doSomethingImportant(): void
	{
		try {
			// Dark magic

			$this->logger->info('Everything is fine');
		} catch (WeHaveAProblemHouston $e) {
			$this->logger->critical('We are all gonna die.', [
				'exception' => $e,
			]);
		}
	}

}
```
