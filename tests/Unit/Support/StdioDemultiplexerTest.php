<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\Support\StdioDemultiplexer;

final class StdioDemultiplexerTest extends TestCase
{
    private static function frame(int $streamType, string $payload): string
    {
        return chr($streamType) . "\x00\x00\x00" . pack('N', strlen($payload)) . $payload;
    }

    public function testDemuxAllDecodesASingleFrame(): void
    {
        $raw = self::frame(1, 'hello stdout');

        self::assertSame(
            [['stream' => 'stdout', 'payload' => 'hello stdout']],
            StdioDemultiplexer::demuxAll($raw),
        );
    }

    public function testDemuxAllDecodesMultipleFramesInOneBuffer(): void
    {
        $raw = self::frame(1, 'out') . self::frame(2, 'err');

        self::assertSame(
            [
                ['stream' => 'stdout', 'payload' => 'out'],
                ['stream' => 'stderr', 'payload' => 'err'],
            ],
            StdioDemultiplexer::demuxAll($raw),
        );
    }

    public function testDemuxAllReturnsEmptyListForEmptyBody(): void
    {
        self::assertSame([], StdioDemultiplexer::demuxAll(''));
    }

    public function testPushHandlesFrameSplitAcrossChunks(): void
    {
        $raw = self::frame(1, 'hello world');
        $firstChunk = substr($raw, 0, 5);
        $secondChunk = substr($raw, 5);

        $demultiplexer = new StdioDemultiplexer();
        $frames = [];
        $onFrame = function (string $stream, string $payload) use (&$frames): void {
            $frames[] = [$stream, $payload];
        };

        $demultiplexer->push($firstChunk, $onFrame);
        self::assertSame([], $frames, 'incomplete frame must not be emitted yet');

        $demultiplexer->push($secondChunk, $onFrame);
        self::assertSame([['stdout', 'hello world']], $frames);
    }

    public function testPushHandlesHeaderSplitAcrossChunks(): void
    {
        $raw = self::frame(2, 'stderr text');
        $firstChunk = substr($raw, 0, 3); // splits mid-header
        $secondChunk = substr($raw, 3);

        $demultiplexer = new StdioDemultiplexer();
        $frames = [];
        $onFrame = function (string $stream, string $payload) use (&$frames): void {
            $frames[] = [$stream, $payload];
        };

        $demultiplexer->push($firstChunk, $onFrame);
        self::assertSame([], $frames);

        $demultiplexer->push($secondChunk, $onFrame);
        self::assertSame([['stderr', 'stderr text']], $frames);
    }

    public function testPushEmitsMultipleFramesFromOneChunk(): void
    {
        $raw = self::frame(1, 'a') . self::frame(1, 'b') . self::frame(2, 'c');

        $demultiplexer = new StdioDemultiplexer();
        $frames = [];

        $demultiplexer->push($raw, function (string $stream, string $payload) use (&$frames): void {
            $frames[] = [$stream, $payload];
        });

        self::assertSame([['stdout', 'a'], ['stdout', 'b'], ['stderr', 'c']], $frames);
    }
}
