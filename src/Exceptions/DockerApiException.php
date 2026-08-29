<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Exceptions;

/**
 * Thrown when the Docker Engine responded with a non-2xx HTTP status. Carries the HTTP status code and, when present, Docker's own JSON error message (the `"message"` field of the error body).
 *
 * @phpstan-consistent-constructor
 */
class DockerApiException extends DockerException
{
    public function __construct(string $message, private readonly int $statusCode, private readonly ?string $dockerMessage = null, private readonly ?string $responseBody = null)
    {
        parent::__construct($message, $statusCode);
    }

    public static function fromResponse(string $method, string $path, int $statusCode, string $responseBody): static
    {
        $dockerMessage = null;

        $decoded = json_decode($responseBody, true);
        if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
            $dockerMessage = $decoded['message'];
        }
        return new static(sprintf('Docker API error on %s %s: HTTP %d%s', $method, $path, $statusCode, $dockerMessage !== null ? sprintf(' - %s', $dockerMessage) : ''), $statusCode, $dockerMessage, $responseBody);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getDockerMessage(): ?string
    {
        return $this->dockerMessage;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}
