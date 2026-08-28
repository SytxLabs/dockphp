<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\Resources\Exec;
use Sytxlabs\Dockphp\Tests\Support\FakeDockerTransport;

final class ExecTest extends TestCase
{
    private FakeDockerTransport $transport;
    private Exec $exec;

    protected function setUp(): void
    {
        $this->transport = new FakeDockerTransport();
        $this->exec = new Exec($this->transport);
    }

    public function testCreatePostsConfigAsBody(): void
    {
        $this->exec->create('container123', ['Cmd' => ['ls', '-la'], 'AttachStdout' => true]);

        $call = $this->transport->lastCall();

        self::assertSame('request', $call['kind']);
        self::assertSame('POST', $call['method']);
        self::assertSame('/containers/container123/exec', $call['path']);
        self::assertSame(['Cmd' => ['ls', '-la'], 'AttachStdout' => true], $call['body']);
    }

    public function testStartPostsOptionsAsBody(): void
    {
        $this->exec->start('exec123', ['Detach' => true]);

        $call = $this->transport->lastCall();

        self::assertSame('POST', $call['method']);
        self::assertSame('/exec/exec123/start', $call['path']);
        self::assertSame(['Detach' => true], $call['body']);
    }

    public function testStartStreamForcesDetachFalseAndStreamsChunks(): void
    {
        $this->transport->setStreamChunks(['hello ', 'world']);

        $received = '';
        $status = $this->exec->startStream('exec123', function (string $chunk) use (&$received): void {
            $received .= $chunk;
        }, ['Tty' => true]);

        $call = $this->transport->lastCall();

        self::assertSame('stream', $call['kind']);
        self::assertSame(['Tty' => true, 'Detach' => false], $call['body']);
        self::assertSame(200, $status);
        self::assertSame('hello world', $received);
    }

    public function testResizePassesHeightAndWidthAsQuery(): void
    {
        $this->exec->resize('exec123', 40, 120);

        $call = $this->transport->lastCall();

        self::assertSame('POST', $call['method']);
        self::assertSame('/exec/exec123/resize', $call['path']);
        self::assertSame(['h' => 40, 'w' => 120], $call['query']);
    }

    public function testInspectSendsGetRequest(): void
    {
        $this->exec->inspect('exec123');

        $call = $this->transport->lastCall();

        self::assertSame('GET', $call['method']);
        self::assertSame('/exec/exec123/json', $call['path']);
    }
}
