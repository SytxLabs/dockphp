<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\Http\DockerResponse;

final class DockerResponseTest extends TestCase
{
    public function testIsSuccessfulForTwoXxStatus(): void
    {
        self::assertTrue((new DockerResponse(200, ''))->isSuccessful());
        self::assertTrue((new DockerResponse(204, ''))->isSuccessful());
        self::assertFalse((new DockerResponse(404, ''))->isSuccessful());
        self::assertFalse((new DockerResponse(500, ''))->isSuccessful());
    }

    public function testJsonDecodesValidBody(): void
    {
        self::assertSame(['Id' => 'abc123', 'Warnings' => []], (new DockerResponse(200, '{"Id":"abc123","Warnings":[]}'))->json());
    }

    public function testJsonReturnsNullForEmptyBody(): void
    {
        self::assertNull((new DockerResponse(204, ''))->json());
        self::assertNull((new DockerResponse(204, '   '))->json());
    }

    public function testJsonReturnsNullForInvalidJson(): void
    {
        self::assertNull((new DockerResponse(200, 'not json'))->json());
    }

    public function testJsonIsMemoized(): void
    {
        $response = new DockerResponse(200, '{"a":1}');
        self::assertSame($response->json(), $response->json());
    }

    public function testGetBodyReturnsRawString(): void
    {
        $response = new DockerResponse(200, '{"a":1}');
        self::assertSame('{"a":1}', $response->getBody());
    }
}
