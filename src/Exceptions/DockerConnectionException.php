<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Exceptions;

use Throwable;

/** Thrown when the transport could not reach the Docker Engine at all (missing socket, connection refused, timeout, ...). This is a transport-level failure, not an API error response. */
class DockerConnectionException extends DockerException
{
    public static function fromCurlError(string $socketPath, string $curlError, int $curlErrno, ?Throwable $previous = null): self
    {
        return new self(sprintf('Could not connect to Docker Engine via socket "%s": %s (curl errno %d)', $socketPath, $curlError, $curlErrno), $curlErrno, $previous);
    }

    public static function fromGuzzleError(string $socketPath, string $guzzleError, ?Throwable $previous = null): self
    {
        return new self(sprintf('Could not connect to Docker Engine via socket "%s": %s', $socketPath, $guzzleError), 0, $previous);
    }
}
