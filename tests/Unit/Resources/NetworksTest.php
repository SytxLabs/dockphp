<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\DTO\NetworkInfo;
use Sytxlabs\Dockphp\Http\DockerResponse;
use Sytxlabs\Dockphp\Resources\Networks;
use Sytxlabs\Dockphp\Tests\Support\FakeDockerTransport;

final class NetworksTest extends TestCase
{
    private FakeDockerTransport $transport;
    private Networks $networks;

    protected function setUp(): void
    {
        $this->transport = new FakeDockerTransport();
        $this->networks = new Networks($this->transport);
    }

    public function testListSendsGetRequestAndHydratesInfos(): void
    {
        $this->transport->setResponse(new DockerResponse(200, '[{"Id":"net123","Name":"my-network"}]'));

        $result = $this->networks->list();

        $call = $this->transport->lastCall();

        self::assertSame('GET', $call['method']);
        self::assertSame('/networks', $call['path']);
        self::assertSame(['filters' => null], $call['query']);

        self::assertCount(1, $result);
        self::assertInstanceOf(NetworkInfo::class, $result[0]);
        self::assertSame('my-network', $result[0]->getName());
    }

    public function testInspectBuildsIdInPathAndHydratesInfo(): void
    {
        $this->transport->setResponse(new DockerResponse(200, '{"Id":"net123","Name":"my-network"}'));

        $result = $this->networks->inspect('net123');

        self::assertInstanceOf(NetworkInfo::class, $result);
        self::assertSame('my-network', $result->getName());
        self::assertSame('/networks/net123', $this->transport->lastCall()['path']);
    }

    public function testCreateSendsNameInBody(): void
    {
        $this->networks->create('my-network');

        $call = $this->transport->lastCall();

        self::assertSame('POST', $call['method']);
        self::assertSame('/networks/create', $call['path']);
        self::assertSame(['Name' => 'my-network'], $call['body']);
    }

    public function testCreateMergesAdditionalOptions(): void
    {
        $this->networks->create('my-network', ['Driver' => 'bridge']);

        $call = $this->transport->lastCall();

        self::assertSame(['Name' => 'my-network', 'Driver' => 'bridge'], $call['body']);
    }

    public function testConnectSendsContainerInBody(): void
    {
        $this->networks->connect('net123', 'container123');

        $call = $this->transport->lastCall();

        self::assertSame('/networks/net123/connect', $call['path']);
        self::assertSame(['Container' => 'container123'], $call['body']);
    }

    public function testDisconnectSendsContainerAndForce(): void
    {
        $this->networks->disconnect('net123', 'container123', true);

        $call = $this->transport->lastCall();

        self::assertSame('/networks/net123/disconnect', $call['path']);
        self::assertSame(['Container' => 'container123', 'Force' => true], $call['body']);
    }

    public function testRemoveSendsDelete(): void
    {
        $this->networks->remove('net123');

        $call = $this->transport->lastCall();

        self::assertSame('DELETE', $call['method']);
        self::assertSame('/networks/net123', $call['path']);
    }

    public function testPruneForwardsFilters(): void
    {
        $this->networks->prune(['label' => ['foo=bar']]);

        self::assertSame(['filters' => ['label' => ['foo=bar']]], $this->transport->lastCall()['query']);
    }
}
