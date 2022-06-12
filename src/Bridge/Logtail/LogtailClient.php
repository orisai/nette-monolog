<?php declare(strict_types = 1);

namespace OriNette\Monolog\Bridge\Logtail;

use Nette\Utils\Json;
use Orisai\Exceptions\Logic\InvalidArgument;
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
			->withHeader('Authorization', "Bearer $this->token")
			->withHeader('Content-Type', 'application/json')
			->withBody($this->streamFactory->createStream(Json::encode($data)));

		$response = $this->client->sendRequest($request);

		$code = $response->getStatusCode();
		if ($code >= 400) {
			throw InvalidArgument::create()
				->withMessage("Logtail returned an error ($code): {$response->getBody()->getContents()}");
		}
	}

}
