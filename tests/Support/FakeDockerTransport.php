<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Support;

use LogicException;
use Sytxlabs\Dockphp\Http\DockerResponse;
use Sytxlabs\Dockphp\Http\DockerTransportInterface;

/**
 * In-memory fake used to unit-test Resources without a real Docker socket.
 * Records every call and returns pre-configured responses; stream()/streamRaw() replay a canned list of chunks through the caller's $onChunk callback.
 */
final class FakeDockerTransport implements DockerTransportInterface
{
    /** @var list<array{kind: string, method: string, path: string, body: array<string, mixed>|string|null, query: array<string, mixed>, headers: array<int, string>}> */
    public array $calls = [];
    /** @var list<string> */
    private array $streamChunks = [];
    private int $streamStatus = 200;

    public function __construct(private DockerResponse $response = new DockerResponse(200, '{}'), private string $apiVersion = '1.43') {}

    public function request(string $method, string $path, ?array $body = null, array $query = [], array $extraHeaders = []): DockerResponse
    {
        $this->record('request', $method, $path, $body, $query, $extraHeaders);
        return $this->response;
    }

    public function requestRaw(string $method, string $path, ?string $rawBody, array $query = [], array $headers = []): DockerResponse
    {
        $this->record('requestRaw', $method, $path, $rawBody, $query, $headers);
        return $this->response;
    }

    public function stream(string $method, string $path, ?array $body, array $query, callable $onChunk, array $extraHeaders = []): int
    {
        $this->record('stream', $method, $path, $body, $query, $extraHeaders);
        $this->replayChunks($onChunk);

        return $this->streamStatus;
    }

    public function streamRaw(string $method, string $path, ?string $rawBody, array $query, callable $onChunk, array $headers = []): int
    {
        $this->record('streamRaw', $method, $path, $rawBody, $query, $headers);
        $this->replayChunks($onChunk);

        return $this->streamStatus;
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    public function setResponse(DockerResponse $response): void
    {
        $this->response = $response;
    }

    /** @param list<string> $chunks */
    public function setStreamChunks(array $chunks, int $status = 200): void
    {
        $this->streamChunks = $chunks;
        $this->streamStatus = $status;
    }

    /** @return array{kind: string, method: string, path: string, body: array<string, mixed>|string|null, query: array<string, mixed>, headers: array<int, string>} */
    public function lastCall(): array
    {
        $call = end($this->calls);
        if ($call === false) {
            throw new LogicException('No request was made.');
        }
        return $call;
    }

    /**
     * @param array<string, mixed>|string|null $body
     * @param array<string, mixed> $query
     * @param array<int, string> $headers
     */
    private function record(string $kind, string $method, string $path, array|string|null $body, array $query, array $headers = []): void
    {
        $this->calls[] = ['kind' => $kind, 'method' => $method, 'path' => $path, 'body' => $body, 'query' => $query, 'headers' => $headers];
    }

    private function replayChunks(callable $onChunk): void
    {
        foreach ($this->streamChunks as $chunk) {
            if ($onChunk($chunk) === false) {
                return;
            }
        }
    }
}
