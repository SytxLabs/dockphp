<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Http;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use stdClass;
use Sytxlabs\Dockphp\Exceptions\DockerApiException;
use Sytxlabs\Dockphp\Exceptions\DockerConnectionException;
use Sytxlabs\Dockphp\Exceptions\DockerException;
use Sytxlabs\Dockphp\Exceptions\DockerNotFoundException;

/**
 * Sends requests to the Docker Engine API using Guzzle, either over a
 * Unix domain socket (default) or a TCP host (optionally with TLS).
 * No shell commands, no Docker CLI.
 *
 * For the Unix socket case, the internal base URL "http://localhost"
 * is never actually resolved over the network — the CURLOPT_UNIX_SOCKET_PATH
 * option (passed through Guzzle's "curl" client config) routes the
 * connection through the socket instead, the host part only satisfies
 * HTTP syntax.
 *
 * @phpstan-consistent-constructor
 */
class DockerTransport implements DockerTransportInterface
{
    private ?string $resolvedApiVersion;
    private readonly Client $client;

    public function __construct(
        private readonly ?string $socketPath,
        ?string $apiVersion = null,
        private readonly float $connectTimeout = 5.0,
        private readonly float $timeout = 30.0,
        private readonly ?string $host = null,
        private readonly int $port = 2375,
        private readonly bool $tls = false,
        private readonly ?string $caFile = null,
        private readonly ?string $certFile = null,
        private readonly ?string $keyFile = null,
    ) {
        $this->resolvedApiVersion = $apiVersion;
        $this->client = $this->buildClient();
    }

    public static function forSocket(
        string $socketPath,
        ?string $apiVersion = null,
        float $connectTimeout = 5.0,
        float $timeout = 30.0,
    ): static {
        return new static($socketPath, $apiVersion, $connectTimeout, $timeout);
    }

    /**
     * @param string $host Hostname or IP only, without scheme (e.g. "docker.example.com").
     * @param string|null $caFile Path to a CA bundle used to verify the server certificate.
     * @param string|null $certFile Path to the client certificate (mutual TLS).
     * @param string|null $keyFile Path to the client private key (mutual TLS).
     */
    public static function forTcp(
        string $host,
        int $port = 2375,
        bool $tls = false,
        ?string $caFile = null,
        ?string $certFile = null,
        ?string $keyFile = null,
        ?string $apiVersion = null,
        float $connectTimeout = 5.0,
        float $timeout = 30.0,
    ): static {
        return new static(null, $apiVersion, $connectTimeout, $timeout, $host, $port, $tls, $caFile, $certFile, $keyFile);
    }

    public function request(string $method, string $path, ?array $body = null, array $query = [], array $extraHeaders = []): DockerResponse
    {
        [$payload, $headers] = $this->encodeJsonPayload($body);

        return $this->bufferedRequest($method, $this->prefixPath($path), $payload, [...$headers, ...$extraHeaders], $query);
    }

    public function requestRaw(string $method, string $path, ?string $rawBody, array $query = [], array $headers = []): DockerResponse
    {
        return $this->bufferedRequest($method, $this->prefixPath($path), $rawBody, $headers, $query);
    }

    public function stream(string $method, string $path, ?array $body, array $query, callable $onChunk, array $extraHeaders = []): int
    {
        [$payload, $headers] = $this->encodeJsonPayload($body);

        return $this->streamedRequest($method, $this->prefixPath($path), $payload, [...$headers, ...$extraHeaders], $query, $onChunk);
    }

    public function streamRaw(string $method, string $path, ?string $rawBody, array $query, callable $onChunk, array $headers = []): int
    {
        return $this->streamedRequest($method, $this->prefixPath($path), $rawBody, $headers, $query, $onChunk);
    }

    public function getApiVersion(): string
    {
        return $this->resolvedApiVersion ??= $this->fetchApiVersion();
    }

    private function buildClient(): Client
    {
        $config = [
            'http_errors' => false,
            'connect_timeout' => $this->connectTimeout,
            'timeout' => $this->timeout,
        ];

        if ($this->socketPath !== null) {
            $config['curl'] = [CURLOPT_UNIX_SOCKET_PATH => $this->socketPath];
        } elseif ($this->tls) {
            $config['verify'] = $this->caFile ?? true;

            if ($this->certFile !== null) {
                $config['cert'] = $this->certFile;
            }

            if ($this->keyFile !== null) {
                $config['ssl_key'] = $this->keyFile;
            }
        }

        return new Client($config);
    }

    private function prefixPath(string $path): string
    {
        return '/v' . $this->getApiVersion() . $path;
    }

    private function fetchApiVersion(): string
    {
        $response = $this->bufferedRequest('GET', '/version', null, [], []);
        $data = $response->json();

        if (!is_array($data) || !isset($data['ApiVersion']) || !is_string($data['ApiVersion'])) {
            throw new DockerException('Could not determine Docker Engine API version from /version response.');
        }

        return $data['ApiVersion'];
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array{0: string|null, 1: array<int, string>}
     */
    private function encodeJsonPayload(?array $body): array
    {
        if ($body === null) {
            return [null, []];
        }

        return [$this->encodeJson($body), ['Content-Type: application/json']];
    }

    /**
     * @param array<int, string> $headerLines
     * @param array<string, mixed> $query
     */
    private function bufferedRequest(string $method, string $path, ?string $payload, array $headerLines, array $query): DockerResponse
    {
        $options = ['headers' => $this->toAssocHeaders($headerLines)];

        if ($payload !== null) {
            $options['body'] = $payload;
        }

        try {
            $response = $this->client->request($method, $this->buildUrl($path, $query), $options);
        } catch (RequestException $e) {
            throw $this->connectionExceptionFrom($e);
        }

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode >= 400) {
            $this->throwForError($method, $path, $statusCode, $body);
        }

        return new DockerResponse($statusCode, $body);
    }

    /**
     * @param array<int, string> $headerLines
     * @param array<string, mixed> $query
     * @param callable(string): (bool|void) $onChunk
     */
    private function streamedRequest(string $method, string $path, ?string $payload, array $headerLines, array $query, callable $onChunk): int
    {
        $sink = new StreamingSink($onChunk instanceof Closure ? $onChunk : Closure::fromCallable($onChunk));

        $options = [
            'headers' => $this->toAssocHeaders($headerLines),
            'sink' => $sink,
            'on_headers' => static function (ResponseInterface $response) use ($sink): void {
                if ($response->getStatusCode() >= 400) {
                    $sink->markAsError();
                }
            },
        ];

        if ($payload !== null) {
            $options['body'] = $payload;
        }

        try {
            $response = $this->client->request($method, $this->buildUrl($path, $query), $options);
        } catch (RequestException $e) {
            $context = $e->getHandlerContext();
            $errno = (int) ($context['errno'] ?? 0);

            if ($sink->isAborted() && $errno === CURLE_WRITE_ERROR) {
                $statusCode = (int) ($context['http_code'] ?? 0);

                if ($statusCode >= 400) {
                    $this->throwForError($method, $path, $statusCode, $sink->getErrorBody());
                }

                return $statusCode;
            }

            throw $this->connectionExceptionFrom($e);
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            $this->throwForError($method, $path, $statusCode, $sink->getErrorBody());
        }

        return $statusCode;
    }

    private function connectionExceptionFrom(RequestException $e): DockerConnectionException
    {
        $context = $e->getHandlerContext();
        $errno = (int) ($context['errno'] ?? 0);
        $error = (string) ($context['error'] ?? $e->getMessage());

        return DockerConnectionException::fromCurlError($this->describeTarget(), $error, $errno);
    }

    private function throwForError(string $method, string $path, int $statusCode, string $body): never
    {
        $exceptionClass = $statusCode === 404 ? DockerNotFoundException::class : DockerApiException::class;

        throw $exceptionClass::fromResponse($method, $path, $statusCode, $body);
    }

    private function describeTarget(): string
    {
        if ($this->socketPath !== null) {
            return $this->socketPath;
        }

        return ($this->tls ? 'tcps://' : 'tcp://') . $this->host . ':' . $this->port;
    }

    /**
     * Converts this package's raw "Name: value" header line format
     * (used throughout its public interface) into the associative
     * array Guzzle's "headers" request option expects.
     *
     * @param array<int, string> $headerLines
     *
     * @return array<string, string>
     */
    private function toAssocHeaders(array $headerLines): array
    {
        $headers = [];

        foreach ($headerLines as $line) {
            $pos = strpos($line, ':');

            if ($pos === false) {
                continue;
            }

            $headers[trim(substr($line, 0, $pos))] = trim(substr($line, $pos + 1));
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $query
     */
    protected function buildUrl(string $path, array $query): string
    {
        $queryString = $this->buildQueryString($query);
        $base = $this->socketPath !== null
            ? 'http://localhost'
            : ($this->tls ? 'https' : 'http') . '://' . $this->host . ':' . $this->port;

        return $base . $path . ($queryString !== '' ? '?' . $queryString : '');
    }

    /**
     * Docker's query parameters use JSON encoding for array values
     * (e.g. `filters`) and lowercase "true"/"false" for booleans.
     *
     * @param array<string, mixed> $query
     */
    protected function buildQueryString(array $query): string
    {
        $prepared = [];

        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                $prepared[$key] = $value ? 'true' : 'false';
                continue;
            }

            if (is_array($value)) {
                $prepared[$key] = $this->encodeJson($value);
                continue;
            }

            $prepared[$key] = (string) $value;
        }

        if ($prepared === []) {
            return '';
        }

        return http_build_query($prepared, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * An empty PHP array is indistinguishable from an empty list, so it
     * json_encode()s to `[]` by default. Docker's Go structs expect a
     * JSON object (`{}`) wherever a body or query value is a map (e.g.
     * exec start options, `filters`) — encode empty arrays as `{}`
     * instead of `[]` to match. Non-empty arrays are unaffected: their
     * PHP key types already determine object-vs-list encoding.
     *
     * @param array<array-key, mixed> $data
     */
    protected function encodeJson(array $data): string
    {
        try {
            return json_encode($data === [] ? new stdClass() : $data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DockerException('Failed to JSON-encode Docker API payload: ' . $e->getMessage(), 0, $e);
        }
    }
}
