<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Http;

use JsonException;
use Sytxlabs\Dockphp\Exceptions\DockerApiException;
use Sytxlabs\Dockphp\Exceptions\DockerConnectionException;
use Sytxlabs\Dockphp\Exceptions\DockerException;
use Sytxlabs\Dockphp\Exceptions\DockerNotFoundException;

/**
 * Sends requests to the Docker Engine API using cURL, either over a
 * Unix domain socket (default) or a TCP host (optionally with TLS).
 * No shell commands, no Docker CLI.
 *
 * For the Unix socket case, the internal base URL "http://localhost"
 * is never actually resolved over the network — CURLOPT_UNIX_SOCKET_PATH
 * routes the connection through the socket instead, the host part only
 * satisfies HTTP syntax.
 *
 * @phpstan-consistent-constructor
 */
class DockerTransport implements DockerTransportInterface
{
    private ?string $resolvedApiVersion;

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
     * @param array<int, string> $headers
     * @param array<string, mixed> $query
     */
    private function bufferedRequest(string $method, string $path, ?string $payload, array $headers, array $query): DockerResponse
    {
        ['status' => $statusCode, 'body' => $body] = $this->execute($method, $path, $payload, $headers, $query, null);

        if ($statusCode >= 400) {
            $this->throwForError($method, $path, $statusCode, $body);
        }

        return new DockerResponse($statusCode, $body);
    }

    /**
     * @param array<int, string> $headers
     * @param array<string, mixed> $query
     * @param callable(string): (bool|void) $onChunk
     */
    private function streamedRequest(string $method, string $path, ?string $payload, array $headers, array $query, callable $onChunk): int
    {
        ['status' => $statusCode, 'body' => $errorBody] = $this->execute($method, $path, $payload, $headers, $query, $onChunk);

        if ($statusCode >= 400) {
            $this->throwForError($method, $path, $statusCode, $errorBody);
        }

        return $statusCode;
    }

    private function throwForError(string $method, string $path, int $statusCode, string $body): never
    {
        $exceptionClass = $statusCode === 404 ? DockerNotFoundException::class : DockerApiException::class;

        throw $exceptionClass::fromResponse($method, $path, $statusCode, $body);
    }

    /**
     * Shared cURL core for all four public request variants.
     *
     * When $onChunk is null the full response body is buffered and
     * returned as 'body'. When $onChunk is given, it is invoked with
     * each chunk as it arrives (return false from it to abort the
     * transfer early — this is not treated as an error); 'body' then
     * only contains bytes received while the response looked like an
     * error (status >= 400), for exception reporting.
     *
     * @param array<int, string> $headers
     * @param array<string, mixed> $query
     * @param (callable(string): (bool|void))|null $onChunk
     *
     * @return array{status: int, body: string}
     */
    private function execute(string $method, string $path, ?string $payload, array $headers, array $query, ?callable $onChunk): array
    {
        $url = $this->buildUrl($path, $query);
        $handle = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_CONNECTTIMEOUT_MS => (int) round($this->connectTimeout * 1000),
            CURLOPT_TIMEOUT_MS => (int) round($this->timeout * 1000),
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($this->socketPath !== null) {
            $options[CURLOPT_UNIX_SOCKET_PATH] = $this->socketPath;
        } elseif ($this->tls) {
            $options[CURLOPT_SSL_VERIFYPEER] = true;
            $options[CURLOPT_SSL_VERIFYHOST] = 2;
            if ($this->caFile !== null) {
                $options[CURLOPT_CAINFO] = $this->caFile;
            }
            if ($this->certFile !== null) {
                $options[CURLOPT_SSLCERT] = $this->certFile;
            }
            if ($this->keyFile !== null) {
                $options[CURLOPT_SSLKEY] = $this->keyFile;
            }
        }

        $statusCode = 0;
        $body = '';
        $aborted = false;

        if ($onChunk === null) {
            $options[CURLOPT_RETURNTRANSFER] = true;
        } else {
            $options[CURLOPT_RETURNTRANSFER] = false;
            $options[CURLOPT_HEADERFUNCTION] = static function ($ch, string $headerLine) use (&$statusCode): int {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $matches) === 1) {
                    $statusCode = (int) $matches[1];
                }

                return strlen($headerLine);
            };
            $options[CURLOPT_WRITEFUNCTION] = static function ($ch, string $chunk) use (&$statusCode, &$body, $onChunk, &$aborted): int {
                $length = strlen($chunk);

                if ($statusCode >= 400) {
                    $body .= $chunk;

                    return $length;
                }

                if ($onChunk($chunk) === false) {
                    $aborted = true;

                    return 0;
                }

                return $length;
            };
        }

        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = $payload;
        }

        curl_setopt_array($handle, $options);

        $result = curl_exec($handle);

        if ($result === false) {
            $errno = curl_errno($handle);

            if ($aborted && $errno === CURLE_WRITE_ERROR) {
                curl_close($handle);

                return ['status' => $statusCode, 'body' => $body];
            }

            $error = curl_error($handle);
            curl_close($handle);

            throw DockerConnectionException::fromCurlError($this->describeTarget(), $error, $errno);
        }

        if ($onChunk === null) {
            $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $body = (string) $result;
        }

        curl_close($handle);

        return ['status' => $statusCode, 'body' => $body];
    }

    private function describeTarget(): string
    {
        if ($this->socketPath !== null) {
            return $this->socketPath;
        }

        return ($this->tls ? 'tcps://' : 'tcp://') . $this->host . ':' . $this->port;
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
     * @param array<string, mixed> $data
     */
    private function encodeJson(array $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DockerException('Failed to JSON-encode Docker API payload: ' . $e->getMessage(), 0, $e);
        }
    }
}
