<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Support;

/**
 * Buffers streamed chunks and decodes newline-delimited JSON lines as
 * they become complete, since chunk boundaries from cURL never align
 * with line boundaries. Used for `docker pull` / `docker build`
 * progress events and `/events`.
 */
final class NdjsonLineBuffer
{
    private string $buffer = '';

    /**
     * Feeds a raw chunk into the buffer and decodes any complete lines.
     * Calls `$onEvent(array $event)` for each decoded JSON object line;
     * non-JSON or non-object lines are skipped. Stops early and returns
     * false as soon as `$onEvent` itself returns false.
     *
     * @param callable(array<mixed>): (bool|void) $onEvent
     */
    public function push(string $chunk, callable $onEvent): bool
    {
        $this->buffer .= $chunk;

        while (($pos = strpos($this->buffer, "\n")) !== false) {
            $line = trim(substr($this->buffer, 0, $pos));
            $this->buffer = substr($this->buffer, $pos + 1);

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (!is_array($decoded)) {
                continue;
            }

            if ($onEvent($decoded) === false) {
                return false;
            }
        }

        return true;
    }
}
