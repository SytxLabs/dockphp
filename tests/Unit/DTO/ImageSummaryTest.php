<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\DTO;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\DTO\ImageSummary;

final class ImageSummaryTest extends TestCase
{
    public function testFromArrayHydratesKnownFields(): void
    {
        $summary = ImageSummary::fromArray([
            'Id' => 'sha256:abc123',
            'ParentId' => 'sha256:parent',
            'RepoTags' => ['nginx:latest'],
            'RepoDigests' => ['nginx@sha256:def456'],
            'Created' => 1700000000,
            'Size' => 12345,
            'Labels' => ['maintainer' => 'nginx'],
        ]);

        self::assertSame('sha256:abc123', $summary->id);
        self::assertSame('sha256:parent', $summary->parentId);
        self::assertSame(['nginx:latest'], $summary->repoTags);
        self::assertSame(['nginx@sha256:def456'], $summary->repoDigests);
        self::assertSame(1700000000, $summary->created);
        self::assertSame(12345, $summary->size);
        self::assertSame(['maintainer' => 'nginx'], $summary->labels);
    }

    public function testGetNameReturnsFirstRepoTag(): void
    {
        self::assertSame('nginx:latest', ImageSummary::fromArray(['RepoTags' => ['nginx:latest', 'nginx:1.25']])->getName());
    }

    public function testGetNameReturnsNullForUntaggedImage(): void
    {
        self::assertNull(ImageSummary::fromArray(['RepoTags' => []])->getName());
    }

    public function testRawReturnsOriginalArrayUntouched(): void
    {
        $data = ['Id' => 'sha256:abc123', 'SomeUnmodeledField' => 'kept'];
        self::assertSame($data, ImageSummary::fromArray($data)->raw());
    }
}
