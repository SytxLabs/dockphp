<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\Http\DockerResponse;
use Sytxlabs\Dockphp\Resources\System;
use Sytxlabs\Dockphp\Tests\Support\FakeDockerTransport;

final class SystemTest extends TestCase
{
    private FakeDockerTransport $transport;
    private System $system;

    protected function setUp(): void
    {
        $this->transport = new FakeDockerTransport();
        $this->system = new System($this->transport);
    }

    public function testInfoSendsGetRequest(): void
    {
        $this->system->info();

        self::assertSame('/info', $this->transport->lastCall()['path']);
    }

    public function testVersionSendsGetRequest(): void
    {
        $this->system->version();

        self::assertSame('/version', $this->transport->lastCall()['path']);
    }

    public function testPingReturnsTrueOnSuccessfulResponse(): void
    {
        $this->transport->setResponse(new DockerResponse(200, 'OK'));

        self::assertTrue($this->system->ping());
        self::assertSame('/_ping', $this->transport->lastCall()['path']);
    }

    public function testPingReturnsFalseOnUnsuccessfulResponse(): void
    {
        $this->transport->setResponse(new DockerResponse(500, ''));

        self::assertFalse($this->system->ping());
    }

    public function testDfSendsGetRequest(): void
    {
        $this->system->df();

        self::assertSame('/system/df', $this->transport->lastCall()['path']);
    }

    public function testEventsDecodesNdjsonAndForwardsFilters(): void
    {
        $this->transport->setStreamChunks(["{\"Type\":\"container\",\"Action\":\"start\"}\n"]);

        $events = [];
        $this->system->events(function (array $event) use (&$events): void {
            $events[] = $event;
        }, ['type' => ['container']], '1000', '2000');

        $call = $this->transport->lastCall();

        self::assertSame('stream', $call['kind']);
        self::assertSame('/events', $call['path']);
        self::assertSame(['filters' => ['type' => ['container']], 'since' => '1000', 'until' => '2000'], $call['query']);
        self::assertSame([['Type' => 'container', 'Action' => 'start']], $events);
    }

    public function testPruneForwardsFilters(): void
    {
        $this->system->prune(['until' => ['24h']]);

        $call = $this->transport->lastCall();

        self::assertSame('POST', $call['method']);
        self::assertSame('/system/prune', $call['path']);
        self::assertSame(['filters' => ['until' => ['24h']]], $call['query']);
    }
}
