<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Http;

use Closure;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class StreamingSink implements StreamInterface
{
    private bool $isError = false;
    private string $errorBuffer = '';
    private bool $aborted = false;

    /**
     * @param Closure(string): (bool|void) $onChunk
     */
    public function __construct(private readonly Closure $onChunk) {}

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

    public function close(): void {}

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
