<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Support;

use Sytxlabs\Dockphp\Http\DockerTransport;

/**
 * Exposes DockerTransport's protected pure helper methods for unit
 * testing without touching curl or a real socket.
 */
final class TestableDockerTransport extends DockerTransport
{
    /**
     * @param array<string, mixed> $query
     */
    public function publicBuildUrl(string $path, array $query): string
    {
        return $this->buildUrl($path, $query);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function publicBuildQueryString(array $query): string
    {
        return $this->buildQueryString($query);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function publicEncodeJson(array $data): string
    {
        return $this->encodeJson($data);
    }
}
