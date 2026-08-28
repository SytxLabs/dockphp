<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\DTO;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\DTO\ContainerSummary;

final class ContainerSummaryTest extends TestCase
{
    public function testFromArrayHydratesKnownFields(): void
    {
        $summary = ContainerSummary::fromArray([
            'Id' => 'abc123',
            'Names' => ['/web'],
            'Image' => 'nginx:latest',
            'ImageID' => 'sha256:deadbeef',
            'Command' => 'nginx -g daemon off;',
            'Created' => 1700000000,
            'State' => 'running',
            'Status' => 'Up 3 hours',
            'Labels' => ['env' => 'prod'],
        ]);

        self::assertSame('abc123', $summary->id);
        self::assertSame(['/web'], $summary->names);
        self::assertSame('nginx:latest', $summary->image);
        self::assertSame('sha256:deadbeef', $summary->imageId);
        self::assertSame(1700000000, $summary->created);
        self::assertSame('running', $summary->state);
        self::assertSame('Up 3 hours', $summary->status);
        self::assertSame(['env' => 'prod'], $summary->labels);
    }

    public function testGetNameStripsLeadingSlash(): void
    {
        $summary = ContainerSummary::fromArray(['Names' => ['/web']]);

        self::assertSame('web', $summary->getName());
    }

    public function testGetNameReturnsEmptyStringWhenNoNames(): void
    {
        $summary = ContainerSummary::fromArray([]);

        self::assertSame('', $summary->getName());
    }

    public function testRawReturnsOriginalArrayUntouched(): void
    {
        $data = ['Id' => 'abc123', 'SomeUnmodeledField' => 'kept'];
        $summary = ContainerSummary::fromArray($data);

        self::assertSame($data, $summary->raw());
    }
}
