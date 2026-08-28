<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Http;

use Sytxlabs\Dockphp\Exceptions\DockerApiException;
use Sytxlabs\Dockphp\Exceptions\DockerConnectionException;

/**
 * Contract used by Resources to talk to the Docker Engine API.
 * Allows tests to substitute a fake transport with no real socket.
 */
interface DockerTransportInterface
{
    /**
     * @param array<string, mixed>|null $body JSON-encoded as the request body when present.
     * @param array<string, mixed> $query Query string parameters.
     * @param array<int, string> $extraHeaders Additional request headers, e.g. 'X-Registry-Auth: ...'.
     *
     * @throws DockerConnectionException When the socket cannot be reached.
     * @throws DockerApiException When the Engine responds with a non-2xx status.
     */
    public function request(
        string $method,
        string $path,
        ?array $body = null,
        array $query = [],
        array $extraHeaders = [],
    ): DockerResponse;

    /**
     * Like request(), but sends $rawBody verbatim (no JSON encoding).
     * Used for endpoints that take a tar stream (e.g. copying files
     * into a container).
     *
     * @param array<string, mixed> $query
     * @param array<int, string> $headers Extra request headers, e.g. 'Content-Type: application/x-tar'.
     */
    public function requestRaw(
        string $method,
        string $path,
        ?string $rawBody,
        array $query = [],
        array $headers = [],
    ): DockerResponse;

    /**
     * Streams the response body to $onChunk as it arrives instead of
     * buffering it. Used for `follow`ed logs, streamed stats, and
     * `/events`. Returning false from $onChunk stops the transfer
     * early without throwing.
     *
     * @param array<string, mixed>|null $body
     * @param array<string, mixed> $query
     * @param callable(string): (bool|void) $onChunk
     * @param array<int, string> $extraHeaders
     *
     * @return int The final HTTP status code.
     */
    public function stream(
        string $method,
        string $path,
        ?array $body,
        array $query,
        callable $onChunk,
        array $extraHeaders = [],
    ): int;

    /**
     * Combination of requestRaw() and stream(): raw request body,
     * streamed response. Used only for `docker build`.
     *
     * @param array<string, mixed> $query
     * @param callable(string): (bool|void) $onChunk
     * @param array<int, string> $headers
     */
    public function streamRaw(
        string $method,
        string $path,
        ?string $rawBody,
        array $query,
        callable $onChunk,
        array $headers = [],
    ): int;

    /**
     * Resolves the API version prefix used for requests (e.g. "1.43"),
     * either the manually configured override or the version detected
     * lazily via a one-time `/version` call.
     */
    public function getApiVersion(): string;
}
