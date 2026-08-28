<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\Tests\Support\TestableDockerTransport;

final class DockerTransportTcpTest extends TestCase
{
    public function testBuildUrlUsesHttpForPlainTcp(): void
    {
        $transport = TestableDockerTransport::forTcp('docker.example.com', 2375, false, apiVersion: '1.43');

        self::assertSame(
            'http://docker.example.com:2375/containers/json',
            $transport->publicBuildUrl('/containers/json', []),
        );
    }

    public function testBuildUrlUsesHttpsWhenTlsEnabled(): void
    {
        $transport = TestableDockerTransport::forTcp('docker.example.com', 2376, true, apiVersion: '1.43');

        self::assertSame(
            'https://docker.example.com:2376/containers/json',
            $transport->publicBuildUrl('/containers/json', []),
        );
    }

    public function testBuildUrlUsesCustomPort(): void
    {
        $transport = TestableDockerTransport::forTcp('10.0.0.5', 9999, apiVersion: '1.43');

        self::assertSame(
            'http://10.0.0.5:9999/info',
            $transport->publicBuildUrl('/info', []),
        );
    }

    public function testForSocketStillUsesLocalhostBase(): void
    {
        $transport = TestableDockerTransport::forSocket('/var/run/docker.sock', '1.43');

        self::assertSame(
            'http://localhost/info',
            $transport->publicBuildUrl('/info', []),
        );
    }
}
