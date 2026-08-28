<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\Exceptions\DockerApiException;
use Sytxlabs\Dockphp\Exceptions\DockerNotFoundException;

final class DockerApiExceptionTest extends TestCase
{
    public function testFromResponseExtractsDockerMessage(): void
    {
        $exception = DockerApiException::fromResponse(
            'GET',
            '/containers/abc/json',
            500,
            '{"message":"something went wrong"}',
        );

        self::assertSame(500, $exception->getStatusCode());
        self::assertSame('something went wrong', $exception->getDockerMessage());
        self::assertStringContainsString('something went wrong', $exception->getMessage());
        self::assertStringContainsString('HTTP 500', $exception->getMessage());
    }

    public function testFromResponseHandlesNonJsonBody(): void
    {
        $exception = DockerApiException::fromResponse('GET', '/info', 502, 'Bad Gateway');

        self::assertNull($exception->getDockerMessage());
        self::assertSame('Bad Gateway', $exception->getResponseBody());
    }

    public function testFromResponseOnNotFoundSubclassPreservesType(): void
    {
        $exception = DockerNotFoundException::fromResponse(
            'GET',
            '/containers/missing/json',
            404,
            '{"message":"No such container: missing"}',
        );

        self::assertInstanceOf(DockerNotFoundException::class, $exception);
        self::assertSame(404, $exception->getStatusCode());
        self::assertSame('No such container: missing', $exception->getDockerMessage());
    }
}
