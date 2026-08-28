<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\DTO\VolumeInfo;
use Sytxlabs\Dockphp\Http\DockerResponse;
use Sytxlabs\Dockphp\Resources\Volumes;
use Sytxlabs\Dockphp\Tests\Support\FakeDockerTransport;

final class VolumesTest extends TestCase
{
    private FakeDockerTransport $transport;
    private Volumes $volumes;

    protected function setUp(): void
    {
        $this->transport = new FakeDockerTransport();
        $this->volumes = new Volumes($this->transport);
    }

    public function testListSendsGetRequestAndHydratesInfos(): void
    {
        $this->transport->setResponse(new DockerResponse(200, '{"Volumes":[{"Name":"my-volume"}],"Warnings":[]}'));

        $result = $this->volumes->list();

        $call = $this->transport->lastCall();

        self::assertSame('GET', $call['method']);
        self::assertSame('/volumes', $call['path']);
        self::assertSame(['filters' => null], $call['query']);

        self::assertCount(1, $result);
        self::assertInstanceOf(VolumeInfo::class, $result[0]);
        self::assertSame('my-volume', $result[0]->getName());
    }

    public function testListReturnsEmptyArrayWhenNoVolumes(): void
    {
        $this->transport->setResponse(new DockerResponse(200, '{"Volumes":null,"Warnings":[]}'));

        self::assertSame([], $this->volumes->list());
    }

    public function testInspectBuildsNameInPathAndHydratesInfo(): void
    {
        $this->transport->setResponse(new DockerResponse(200, '{"Name":"my-volume"}'));

        $result = $this->volumes->inspect('my-volume');

        self::assertInstanceOf(VolumeInfo::class, $result);
        self::assertSame('my-volume', $result->getName());
        self::assertSame('/volumes/my-volume', $this->transport->lastCall()['path']);
    }

    public function testCreateSendsNameInBody(): void
    {
        $this->volumes->create('my-volume');

        $call = $this->transport->lastCall();

        self::assertSame('POST', $call['method']);
        self::assertSame('/volumes/create', $call['path']);
        self::assertSame(['Name' => 'my-volume'], $call['body']);
    }

    public function testCreateMergesAdditionalOptions(): void
    {
        $this->volumes->create('my-volume', ['Driver' => 'local']);

        self::assertSame(['Name' => 'my-volume', 'Driver' => 'local'], $this->transport->lastCall()['body']);
    }

    public function testRemoveSendsDeleteWithForceFlag(): void
    {
        $this->volumes->remove('my-volume', true);

        $call = $this->transport->lastCall();

        self::assertSame('DELETE', $call['method']);
        self::assertSame('/volumes/my-volume', $call['path']);
        self::assertSame(['force' => true], $call['query']);
    }

    public function testPruneForwardsFilters(): void
    {
        $this->volumes->prune(['label' => ['foo=bar']]);

        self::assertSame(['filters' => ['label' => ['foo=bar']]], $this->transport->lastCall()['query']);
    }
}
