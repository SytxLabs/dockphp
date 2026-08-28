<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\DTO;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\DTO\NetworkInfo;

final class NetworkInfoTest extends TestCase
{
    public function testFromArrayHydratesKnownFields(): void
    {
        $network = NetworkInfo::fromArray([
            'Id' => 'net123',
            'Name' => 'my-network',
            'Driver' => 'bridge',
            'Scope' => 'local',
            'Internal' => false,
            'Attachable' => true,
            'Created' => '2024-01-01T00:00:00Z',
            'Labels' => ['env' => 'prod'],
            'Options' => ['com.docker.network.bridge.enable_icc' => 'true'],
        ]);

        self::assertSame('net123', $network->id);
        self::assertSame('my-network', $network->name);
        self::assertSame('bridge', $network->driver);
        self::assertSame('local', $network->scope);
        self::assertFalse($network->internal);
        self::assertTrue($network->attachable);
        self::assertSame(['env' => 'prod'], $network->labels);
    }

    public function testGetNameReturnsName(): void
    {
        $network = NetworkInfo::fromArray(['Name' => 'my-network']);

        self::assertSame('my-network', $network->getName());
    }

    public function testRawReturnsOriginalArrayUntouched(): void
    {
        $data = ['Id' => 'net123', 'SomeUnmodeledField' => 'kept'];
        $network = NetworkInfo::fromArray($data);

        self::assertSame($data, $network->raw());
    }
}
