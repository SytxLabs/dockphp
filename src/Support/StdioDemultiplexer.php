<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Support;

/**
 * Splits Docker's multiplexed stdout/stderr stream format into
 * individual frames. Applies only to containers created without a
 * TTY (`Tty: false`) — with a TTY, Docker sends raw bytes and no
 * demuxing is needed or possible.
 *
 * Frame format: 1 byte stream type (0 = stdin, 1 = stdout, 2 = stderr),
 * 3 reserved bytes, 4-byte big-endian payload length, then the payload.
 *
 * @see https://docs.docker.com/engine/api/v1.43/#tag/Container/operation/ContainerAttach
 */
final class StdioDemultiplexer
{
    private const STREAM_NAMES = [0 => 'stdin', 1 => 'stdout', 2 => 'stderr'];
    private const HEADER_LENGTH = 8;

    private string $buffer = '';

    /**
     * Decodes a complete, non-streamed body in one call.
     *
     * @return list<array{stream: string, payload: string}>
     */
    public static function demuxAll(string $rawBody): array
    {
        $demultiplexer = new self();
        $frames = [];

        $demultiplexer->push($rawBody, static function (string $stream, string $payload) use (&$frames): void {
            $frames[] = ['stream' => $stream, 'payload' => $payload];
        });

        return $frames;
    }

    /**
     * Feeds a streamed chunk in; emits every complete frame found so
     * far via `$onFrame(string $stream, string $payload)`. Incomplete
     * trailing frames are kept buffered until the next call.
     *
     * @param callable(string, string): void $onFrame
     */
    public function push(string $chunk, callable $onFrame): void
    {
        $this->buffer .= $chunk;

        while (strlen($this->buffer) >= self::HEADER_LENGTH) {
            $streamType = ord($this->buffer[0]);
            $lengthUnpacked = unpack('N', substr($this->buffer, 4, 4));

            if ($lengthUnpacked === false) {
                break;
            }

            $length = $lengthUnpacked[1];

            if (strlen($this->buffer) < self::HEADER_LENGTH + $length) {
                break;
            }

            $payload = substr($this->buffer, self::HEADER_LENGTH, $length);
            $this->buffer = substr($this->buffer, self::HEADER_LENGTH + $length);

            $onFrame(self::STREAM_NAMES[$streamType] ?? 'unknown', $payload);
        }
    }
}
