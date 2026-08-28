<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Http;

use Closure;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Write-only PSR-7 stream used as Guzzle's cURL "sink" for streamed
 * requests (follow logs, live stats, pull/build progress, events).
 *
 * Guzzle's default cURL handler is synchronous (curl_exec() blocks
 * until the transfer completes) but still writes each body chunk to
 * the sink as it arrives via CURLOPT_WRITEFUNCTION — so a sink that
 * forwards to a callback on write() gives genuine incremental
 * processing during that blocking call, exactly like a raw cURL
 * write-function would. Returning false from the callback makes
 * write() return a short byte count, which curl reports as
 * CURLE_WRITE_ERROR and aborts the transfer — the caller translates
 * that back into a clean early stop instead of an exception.
 *
 * When the response turns out to be an error (status >= 400, flagged
 * via markAsError() from an `on_headers` callback before any body
 * bytes arrive), bytes are buffered instead of forwarded, so the
 * caller can build a proper exception message from the full body.
 *
 * @internal
 */
final class StreamingSink implements StreamInterface
{
    private bool $isError = false;
    private string $errorBuffer = '';
    private bool $aborted = false;

    /**
     * @param Closure(string): (bool|void) $onChunk
     */
    public function __construct(private readonly Closure $onChunk)
    {
    }

    public function markAsError(): void
    {
        $this->isError = true;
    }

    public function isAborted(): bool
    {
        return $this->aborted;
    }

    public function getErrorBody(): string
    {
        return $this->errorBuffer;
    }

    public function write(string $string): int
    {
        if ($this->isError) {
            $this->errorBuffer .= $string;

            return strlen($string);
        }

        if (($this->onChunk)($string) === false) {
            $this->aborted = true;

            return 0;
        }

        return strlen($string);
    }

    public function __toString(): string
    {
        return '';
    }

    public function close(): void
    {
    }

    public function detach(): mixed
    {
        return null;
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function tell(): int
    {
        throw new RuntimeException('StreamingSink is write-only.');
    }

    public function eof(): bool
    {
        return true;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('StreamingSink is not seekable.');
    }

    public function rewind(): void
    {
        throw new RuntimeException('StreamingSink is not seekable.');
    }

    public function isWritable(): bool
    {
        return true;
    }

    public function isReadable(): bool
    {
        return false;
    }

    public function read(int $length): string
    {
        throw new RuntimeException('StreamingSink is not readable.');
    }

    public function getContents(): string
    {
        throw new RuntimeException('StreamingSink is not readable.');
    }

    public function getMetadata(?string $key = null): mixed
    {
        return $key === null ? [] : null;
    }
}
