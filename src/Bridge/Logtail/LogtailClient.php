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

	private string $url = 'https://in.logtail.com/';

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

	/**
	 * @deprecated use setUrl() instead
	 */
	public function setUri(string $uri): void
	{
		$this->setUrl($uri);
	}

	public function setUrl(string $url): void
	{
		$this->url = $url;
	}

	/**
	 * @param array<mixed>|array<array<mixed>> $data
	 * @throws ClientExceptionInterface
	 */
	public function log(array $data): void
	{
		$request = $this->requestFactory->createRequest('POST', $this->url);
		$request = $request
			->withHeader('Authorization', "Bearer $this->token")
			->withHeader('Content-Type', 'application/json')
			->withBody($this->streamFactory->createStream(Json::encode($data, Json::PRETTY)));

		$response = $this->client->sendRequest($request);

		$code = $response->getStatusCode();
		if ($code >= 400) {
			throw InvalidArgument::create()
				->withMessage("Logtail returned an error ($code): {$response->getBody()->getContents()}");
		}
	}

}
