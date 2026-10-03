<?php

namespace Lettermint\Laravel\Tests\Support;

use Lettermint\Client\HttpClient;
use Throwable;

/**
 * HTTP-level test double for the Lettermint PHP SDK.
 *
 * A real EmailEndpoint is constructed with this client, so tests exercise the
 * exact JSON body and request headers the SDK would hand to Guzzle, without
 * performing a network request.
 */
class RecordingHttpClient extends HttpClient
{
    /**
     * @var list<array{path: string, data: array<array-key, mixed>, headers: array<string, string>}>
     */
    public array $requests = [];

    /**
     * @var list<array<array-key, mixed>|Throwable>
     */
    private array $responses = [];

    public function __construct()
    {
        parent::__construct('test-token', 'https://api.lettermint.test/v1');
    }

    /**
     * Queue the response (or exception) for the next POST request.
     *
     * @param  array<array-key, mixed>|Throwable  $response
     */
    public function respondWith(array|Throwable $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    public function post(string $path, array $data, array $headers = []): mixed
    {
        $this->requests[] = ['path' => $path, 'data' => $data, 'headers' => $headers];

        $response = array_shift($this->responses) ?? ['message_id' => '123', 'status' => 'pending'];

        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }

    /**
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
}
