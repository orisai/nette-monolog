<?php declare(strict_types = 1);

namespace OriNette\Monolog\Bridge\Logtail;

use Nette\Utils\Json;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class LogtailClient
{

	private string $token;

	private string $uri = 'https://in.logtail.com/';

	private ClientInterface $client;

	private RequestFactoryInterface $requestFactory;

	private StreamFactoryInterface $streamFactory;

	public function __construct(
		string $token,
		ClientInterface $client,
		RequestFactoryInterface $requestFactory,
		StreamFactoryInterface $streamFactory
	)
	{
		$this->token = $token;
		$this->client = $client;
		$this->requestFactory = $requestFactory;
		$this->streamFactory = $streamFactory;
	}

	public function setUri(string $uri): void
	{
		$this->uri = $uri;
	}

	/**
	 * @param array<mixed>|array<array<mixed>> $data
	 * @throws ClientExceptionInterface
	 */
	public function log(array $data): void
	{
		$request = $this->requestFactory->createRequest('POST', $this->uri);
		$request = $request
			->withHeader('auth_bearer', $this->token)
			->withHeader('content-type', 'application/json')
			->withBody($this->streamFactory->createStream(Json::encode($data)));

		$this->client->sendRequest($request);
	}

}
