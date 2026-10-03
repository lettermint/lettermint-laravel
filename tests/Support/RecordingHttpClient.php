<?php

namespace Lettermint\Laravel\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * HTTP-level test double for the Lettermint PHP SDK.
 *
 * A real Lettermint client is constructed with the Guzzle client from
 * client(), so tests exercise the exact JSON body and request headers the SDK
 * puts on the wire, without performing a network request.
 */
class RecordingHttpClient
{
    /**
     * @var list<RequestInterface>
     */
    public array $sent = [];

    /**
     * @var list<array{path: string, data: array<array-key, mixed>, headers: array<string, string>}>
     */
    public array $requests = [];

    /**
     * @var list<ResponseInterface|Throwable>
     */
    private array $responses = [];

    /**
     * Queue the response for the next request: an array is sent as a JSON body
     * with HTTP 200, a Throwable is thrown by the handler (e.g. a Guzzle
     * ConnectException).
     *
     * @param  array<array-key, mixed>|ResponseInterface|Throwable  $response
     */
    public function respondWith(array|ResponseInterface|Throwable $response): self
    {
        $this->responses[] = is_array($response)
            ? new Response(200, ['Content-Type' => 'application/json'], json_encode($response, JSON_THROW_ON_ERROR))
            : $response;

        return $this;
    }

    /**
     * Queue a JSON error response.
     *
     * @param  array<array-key, mixed>  $body
     * @param  array<string, string>  $headers
     */
    public function respondWithError(int $status, array $body, array $headers = []): self
    {
        return $this->respondWith(new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode($body, JSON_THROW_ON_ERROR)));
    }

    public function client(): ClientInterface
    {
        return new Client(['handler' => HandlerStack::create(function (RequestInterface $request): PromiseInterface {
            $this->record($request);

            $response = array_shift($this->responses)
                ?? new Response(200, ['Content-Type' => 'application/json'], '{"message_id":"123","status":"pending"}');

            return $response instanceof Throwable ? Create::rejectionFor($response) : Create::promiseFor($response);
        })]);
    }

    /**
     * The last request in the shape the transport tests compare: the URL path,
     * the decoded JSON body and the Idempotency-Key header when one was sent.
     *
     * @return array{path: string, data: array<array-key, mixed>, headers: array<string, string>}
     */
    public function lastRequest(): array
    {
        $request = end($this->requests);

        if ($request === false) {
            throw new \RuntimeException('No request was recorded.');
        }

        return $request;
    }

    public function lastRawRequest(): RequestInterface
    {
        $request = end($this->sent);

        if ($request === false) {
            throw new \RuntimeException('No request was recorded.');
        }

        return $request;
    }

    private function record(RequestInterface $request): void
    {
        $this->sent[] = $request;

        $body = (string) $request->getBody();
        $headers = $request->hasHeader('Idempotency-Key')
            ? ['Idempotency-Key' => $request->getHeaderLine('Idempotency-Key')]
            : [];

        $this->requests[] = [
            'path' => $request->getUri()->getPath(),
            'data' => $body === '' ? [] : json_decode($body, true, 512, JSON_THROW_ON_ERROR),
            'headers' => $headers,
        ];
    }
}
