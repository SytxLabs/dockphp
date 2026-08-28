<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\DTO;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\DTO\VolumeInfo;

final class VolumeInfoTest extends TestCase
{
    public function testFromArrayHydratesKnownFields(): void
    {
        $volume = VolumeInfo::fromArray([
            'Name' => 'my-volume',
            'Driver' => 'local',
            'Mountpoint' => '/var/lib/docker/volumes/my-volume/_data',
            'CreatedAt' => '2024-01-01T00:00:00Z',
            'Scope' => 'local',
            'Labels' => ['env' => 'prod'],
            'Options' => [],
        ]);

        self::assertSame('my-volume', $volume->name);
        self::assertSame('local', $volume->driver);
        self::assertSame('/var/lib/docker/volumes/my-volume/_data', $volume->mountpoint);
        self::assertSame('2024-01-01T00:00:00Z', $volume->createdAt);
        self::assertSame('local', $volume->scope);
        self::assertSame(['env' => 'prod'], $volume->labels);
    }

    public function testGetNameReturnsName(): void
    {
        $volume = VolumeInfo::fromArray(['Name' => 'my-volume']);

        self::assertSame('my-volume', $volume->getName());
    }

    public function testRawReturnsOriginalArrayUntouched(): void
    {
        $data = ['Name' => 'my-volume', 'SomeUnmodeledField' => 'kept'];
        $volume = VolumeInfo::fromArray($data);

        self::assertSame($data, $volume->raw());
    }
}
