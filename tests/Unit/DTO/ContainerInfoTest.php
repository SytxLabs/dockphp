<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\DTO;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\DTO\ContainerInfo;

final class ContainerInfoTest extends TestCase
{
    public function testFromArrayHydratesKnownFields(): void
    {
        $info = ContainerInfo::fromArray([
            'Id' => 'abc123',
            'Name' => '/web',
            'Image' => 'sha256:deadbeef',
            'Path' => 'nginx',
            'Args' => ['-g', 'daemon off;'],
            'State' => ['Running' => true, 'ExitCode' => 0],
            'Config' => ['Env' => ['FOO=bar']],
            'Created' => '2024-01-01T00:00:00Z',
        ]);

        self::assertSame('abc123', $info->id);
        self::assertSame('web', $info->name);
        self::assertSame('sha256:deadbeef', $info->image);
        self::assertSame('nginx', $info->path);
        self::assertSame(['-g', 'daemon off;'], $info->args);
        self::assertSame(['Running' => true, 'ExitCode' => 0], $info->state);
        self::assertSame(['Env' => ['FOO=bar']], $info->config);
        self::assertSame('2024-01-01T00:00:00Z', $info->created);
    }

    public function testGetNameStripsLeadingSlash(): void
    {
        self::assertSame('web', ContainerInfo::fromArray(['Name' => '/web'])->getName());
    }

    public function testIsRunningReflectsStateRunning(): void
    {
        self::assertTrue(ContainerInfo::fromArray(['State' => ['Running' => true]])->isRunning());
        self::assertFalse(ContainerInfo::fromArray(['State' => ['Running' => false]])->isRunning());
        self::assertFalse(ContainerInfo::fromArray([])->isRunning());
    }

    public function testRawReturnsOriginalArrayUntouched(): void
    {
        $data = ['Id' => 'abc123', 'SomeUnmodeledField' => 'kept'];
        self::assertSame($data, ContainerInfo::fromArray($data)->raw());
    }
}
