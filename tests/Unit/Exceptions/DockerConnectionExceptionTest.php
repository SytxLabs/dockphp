<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\Exceptions\DockerConnectionException;

final class DockerConnectionExceptionTest extends TestCase
{
    public function testFromCurlErrorBuildsReadableMessage(): void
    {
        $exception = DockerConnectionException::fromCurlError('/var/run/docker.sock', 'Couldn\'t connect to server', 7);
        self::assertSame(7, $exception->getCode());
        self::assertStringContainsString('/var/run/docker.sock', $exception->getMessage());
        self::assertStringContainsString("Couldn't connect to server", $exception->getMessage());
    }
}
