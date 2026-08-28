<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\DockerClient;
use Sytxlabs\Dockphp\Resources\Containers;
use Sytxlabs\Dockphp\Resources\Images;
use Sytxlabs\Dockphp\Resources\Networks;
use Sytxlabs\Dockphp\Resources\System;
use Sytxlabs\Dockphp\Resources\Volumes;

final class DockerClientTest extends TestCase
{
    public function testConstructionDoesNotPerformIo(): void
    {
        // Building the client (and resolving resources) must not touch
        // the socket until an actual request is made.
        $client = new DockerClient('/var/run/docker.sock', '1.43');

        self::assertInstanceOf(Containers::class, $client->containers());
        self::assertInstanceOf(Images::class, $client->images());
        self::assertInstanceOf(Networks::class, $client->networks());
        self::assertInstanceOf(Volumes::class, $client->volumes());
        self::assertInstanceOf(System::class, $client->system());
    }

    public function testResourcesAreMemoized(): void
    {
        $client = new DockerClient('/var/run/docker.sock', '1.43');

        self::assertSame($client->containers(), $client->containers());
        self::assertSame($client->images(), $client->images());
        self::assertSame($client->networks(), $client->networks());
        self::assertSame($client->volumes(), $client->volumes());
        self::assertSame($client->system(), $client->system());
    }

    public function testGetApiVersionReturnsManualOverrideWithoutIo(): void
    {
        $client = new DockerClient('/var/run/docker.sock', '1.43');

        self::assertSame('1.43', $client->getApiVersion());
    }
}
