<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\DockerClient;
use Sytxlabs\Dockphp\DTO\ContainerSummary;
use Sytxlabs\Dockphp\DTO\ImageSummary;

/** Runs only against a real Docker Engine. Automatically skipped when /var/run/docker.sock does not exist (e.g. on a machine without Docker, or on Windows) - no environment variable required. */
final class DockerIntegrationTest extends TestCase
{
    private const SOCKET_PATH = '/var/run/docker.sock';

    protected function setUp(): void
    {
        if (!file_exists(self::SOCKET_PATH)) {
            self::markTestSkipped('Docker socket not found at ' . self::SOCKET_PATH . '; skipping integration test.');
        }
    }

    public function testPingSucceedsAgainstRealDaemon(): void
    {
        self::assertTrue((new DockerClient(self::SOCKET_PATH))->system()->ping());
    }

    public function testListContainersAndImagesAgainstRealDaemon(): void
    {
        $client = new DockerClient(self::SOCKET_PATH);
        $containers = $client->containers()->list(['all' => true]);
        self::assertIsArray($containers);
        if ($containers !== []) {
            self::assertInstanceOf(ContainerSummary::class, $containers[0]);
        }

        $images = $client->images()->list();
        self::assertIsArray($images);
        if ($images !== []) {
            self::assertInstanceOf(ImageSummary::class, $images[0]);
        }
    }

    public function testApiVersionIsAutoDetected(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+$/', (new DockerClient(self::SOCKET_PATH))->getApiVersion());
    }
}
