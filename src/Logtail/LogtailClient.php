<?php declare(strict_types = 1);

namespace OriNette\Monolog\Logtail;

use Nette\Utils\Json;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class LogtailClient
{

	private HttpClientInterface $httpClient;

	public function __construct(HttpClientInterface $httpClient)
	{
		$this->httpClient = $httpClient;
	}

	public static function create(string $token, ?string $uri = null): self
	{
		$httpClient = HttpClient::createForBaseUri(
			$uri ?? 'https://in.logtail.com/',
			[
				'auth_bearer' => $token,
			],
		);

		return new self($httpClient);
	}

	/**
	 * @param array<mixed> $record
	 * @throws TransportExceptionInterface
	 */
	public function log(array $record): void
	{
		$this->doRequest($record);
	}

	/**
	 * @param array<array<mixed>> $records
	 * @throws TransportExceptionInterface
	 */
	public function logBatch(array $records): void
	{
		$this->doRequest($records);
	}

	/**
	 * @param mixed $data
	 * @throws TransportExceptionInterface
	 */
	private function doRequest($data): void
	{
		$this->httpClient->request(
			'POST',
			'',
			[
				'headers' => [
					'content-type' => 'application/json',
				],
				'body' => Json::encode($data),
			],
		);
	}

}
