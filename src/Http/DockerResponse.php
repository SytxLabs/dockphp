<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Http;

use JsonException;

/**
 * Immutable wrapper around a Docker Engine API HTTP response.
 */
final class DockerResponse
{
    /** @var array<mixed>|null */
    private ?array $decoded = null;
    private bool $decodeAttempted = false;

    public function __construct(
        private readonly int $statusCode,
        private readonly string $body,
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Lazily JSON-decodes the response body as an associative array.
     * Returns null when the body is empty or not valid JSON.
     *
     * @return array<mixed>|null
     */
    public function json(): ?array
    {
        if ($this->decodeAttempted) {
            return $this->decoded;
        }

        $this->decodeAttempted = true;

        if (trim($this->body) === '') {
            return $this->decoded = null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->decoded = null;
        }

        return $this->decoded = is_array($decoded) ? $decoded : null;
    }
}
