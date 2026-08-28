<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\Support\NdjsonLineBuffer;

final class NdjsonLineBufferTest extends TestCase
{
    public function testDecodesASingleCompleteLine(): void
    {
        $buffer = new NdjsonLineBuffer();
        $events = [];

        $buffer->push("{\"status\":\"Pulling\"}\n", function (array $event) use (&$events): void {
            $events[] = $event;
        });

        self::assertSame([['status' => 'Pulling']], $events);
    }

    public function testBuffersPartialLinesAcrossPushes(): void
    {
        $buffer = new NdjsonLineBuffer();
        $events = [];
        $onEvent = function (array $event) use (&$events): void {
            $events[] = $event;
        };

        $buffer->push('{"status":"Down', $onEvent);
        self::assertSame([], $events, 'no line completed yet');

        $buffer->push("loading\"}\n", $onEvent);
        self::assertSame([['status' => 'Downloading']], $events);
    }

    public function testDecodesMultipleLinesInOneChunk(): void
    {
        $buffer = new NdjsonLineBuffer();
        $events = [];

        $buffer->push("{\"a\":1}\n{\"a\":2}\n{\"a\":3}\n", function (array $event) use (&$events): void {
            $events[] = $event;
        });

        self::assertSame([['a' => 1], ['a' => 2], ['a' => 3]], $events);
    }

    public function testSkipsBlankAndNonJsonLines(): void
    {
        $buffer = new NdjsonLineBuffer();
        $events = [];

        $buffer->push("\n{\"a\":1}\nnot json\n\n", function (array $event) use (&$events): void {
            $events[] = $event;
        });

        self::assertSame([['a' => 1]], $events);
    }

    public function testStopsEarlyWhenCallbackReturnsFalse(): void
    {
        $buffer = new NdjsonLineBuffer();
        $seen = [];

        $result = $buffer->push("{\"a\":1}\n{\"a\":2}\n{\"a\":3}\n", function (array $event) use (&$seen) {
            $seen[] = $event;

            return $event['a'] !== 2;
        });

        self::assertFalse($result);
        self::assertSame([['a' => 1], ['a' => 2]], $seen);
    }
}
