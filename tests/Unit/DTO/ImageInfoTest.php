<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\DTO;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\DTO\ImageInfo;

final class ImageInfoTest extends TestCase
{
    public function testFromArrayHydratesKnownFields(): void
    {
        $info = ImageInfo::fromArray([
            'Id' => 'sha256:abc123',
            'RepoTags' => ['nginx:latest'],
            'RepoDigests' => ['nginx@sha256:def456'],
            'Parent' => '',
            'Comment' => '',
            'Created' => '2024-01-01T00:00:00Z',
            'Author' => '',
            'Architecture' => 'amd64',
            'Os' => 'linux',
            'Size' => 12345,
            'Config' => ['Env' => ['FOO=bar']],
        ]);

        self::assertSame('sha256:abc123', $info->id);
        self::assertSame(['nginx:latest'], $info->repoTags);
        self::assertSame('amd64', $info->architecture);
        self::assertSame('linux', $info->os);
        self::assertSame(12345, $info->size);
        self::assertSame(['Env' => ['FOO=bar']], $info->config);
    }

    public function testGetNameReturnsFirstRepoTagOrNull(): void
    {
        self::assertSame('nginx:latest', ImageInfo::fromArray(['RepoTags' => ['nginx:latest']])->getName());
        self::assertNull(ImageInfo::fromArray(['RepoTags' => []])->getName());
    }

    public function testRawReturnsOriginalArrayUntouched(): void
    {
        $data = ['Id' => 'sha256:abc123', 'SomeUnmodeledField' => 'kept'];
        $info = ImageInfo::fromArray($data);

        self::assertSame($data, $info->raw());
    }
}
