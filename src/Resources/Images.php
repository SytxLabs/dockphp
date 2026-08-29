<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Resources;

use JsonException;
use RuntimeException;
use Sytxlabs\Dockphp\DTO\ImageInfo;
use Sytxlabs\Dockphp\DTO\ImageSummary;
use Sytxlabs\Dockphp\Http\DockerResponse;
use Sytxlabs\Dockphp\Support\NdjsonLineBuffer;

/**
 * @see https://docs.docker.com/engine/api/latest/#tag/Image
 */
final class Images extends AbstractResource
{
    /**
     * @param array<string, mixed> $query Supports e.g. 'all', 'filters', 'digests'.
     *
     * @return list<ImageSummary>
     */
    public function list(array $query = []): array
    {
        $response = $this->transport->request('GET', '/images/json', null, $query);

        return array_map(static fn(array $item): ImageSummary => ImageSummary::fromArray($item), $this->decodeList($response));
    }

    public function inspect(string $name): ImageInfo
    {
        return ImageInfo::fromArray($this->decodeObject($this->transport->request('GET', "/images/{$name}/json")));
    }

    /**
     * Pulls (creates) an image from a registry. Returns the full, non-streamed body of newline-delimited JSON progress events. Use pullStream() to react to progress as it happens.
     *
     * @param array<string, mixed>|null $registryAuth Credentials for a private registry, e.g. ['username' => ..., 'password' => ..., 'serveraddress' => ...] or ['identitytoken' => ...].
     */
    public function pull(string $name, ?string $tag = null, ?string $platform = null, ?array $registryAuth = null): DockerResponse
    {
        return $this->transport->request('POST', '/images/create', null, [
            'fromImage' => $name,
            'tag' => $tag,
            'platform' => $platform,
        ], $this->registryAuthHeader($registryAuth));
    }

    /**
     * Pulls an image, invoking $onProgress with each decoded progress event as it arrives. Return false from $onProgress to abort the pull early.
     *
     * @param array<string, mixed>|null $registryAuth See pull().
     * @param callable(array<string, mixed>): (bool|void) $onProgress
     */
    public function pullStream(string $name, ?string $tag, ?string $platform, callable $onProgress, ?array $registryAuth = null): int
    {
        $lineBuffer = new NdjsonLineBuffer();

        return $this->transport->stream('POST', '/images/create', null, [
            'fromImage' => $name,
            'tag' => $tag,
            'platform' => $platform,
        ], static fn(string $chunk): bool => $lineBuffer->push($chunk, $onProgress), $this->registryAuthHeader($registryAuth));
    }

    /**
     * Pushes an image to a registry. Returns the full, non-streamed body of newline-delimited JSON progress events. Use pushStream() to react to progress as it happens.
     *
     * @param array<string, mixed>|null $registryAuth See pull(). Required by most registries.
     */
    public function push(string $name, ?string $tag = null, ?array $registryAuth = null): DockerResponse
    {
        return $this->transport->request('POST', "/images/{$name}/push", null, ['tag' => $tag], $this->registryAuthHeader($registryAuth));
    }

    /**
     * Like push(), but invokes $onProgress with each decoded progress event as it arrives. Return false from $onProgress to abort.
     *
     * @param array<string, mixed>|null $registryAuth See pull().
     * @param callable(array<string, mixed>): (bool|void) $onProgress
     */
    public function pushStream(string $name, callable $onProgress, ?string $tag = null, ?array $registryAuth = null): int
    {
        $lineBuffer = new NdjsonLineBuffer();
        return $this->transport->stream('POST', "/images/{$name}/push", null, ['tag' => $tag], static fn(string $chunk): bool => $lineBuffer->push($chunk, $onProgress), $this->registryAuthHeader($registryAuth));
    }

    /**
     * Searches Docker Hub (or the configured registry) for images.
     *
     * @param array<string, array<int, string>> $filters
     */
    public function search(string $term, ?int $limit = null, array $filters = []): DockerResponse
    {
        return $this->transport->request('GET', '/images/search', null, ['term' => $term, 'limit' => $limit, 'filters' => $filters === [] ? null : $filters]);
    }

    /**
     * Imports one or more images from a tar archive previously produced by save()/saveMultiple() (or `docker save`).
     */
    public function load(string $tarContent, bool $quiet = false): DockerResponse
    {
        return $this->transport->requestRaw('POST', '/images/load', $tarContent, ['quiet' => $quiet], ['Content-Type: application/x-tar']);
    }

    /**
     * Exports a single image (with its history) as a tar archive. Read the raw tar bytes via DockerResponse::getBody().
     */
    public function save(string $name): DockerResponse
    {
        return $this->transport->request('GET', "/images/{$name}/get");
    }

    /**
     * Exports multiple images (and their shared layers) as a single tar archive. Read the raw tar bytes via DockerResponse::getBody().
     * Docker expects the repeated `names=a&names=b` query format here rather than the JSON-array-per-key format used everywhere else (e.g. `filters`), so the query string is built manually.
     *
     * @param list<string> $names
     */
    public function saveMultiple(array $names): DockerResponse
    {
        $namesQuery = implode('&', array_map(static fn(string $name): string => 'names=' . rawurlencode($name), $names));
        return $this->transport->request('GET', '/images/get' . ($namesQuery !== '' ? '?' . $namesQuery : ''));
    }

    /** Returns distribution (registry) information for an image reference without pulling it useful to check a digest or available platforms up front. */
    public function inspectDistribution(string $name): DockerResponse
    {
        return $this->transport->request('GET', "/distribution/{$name}/json");
    }

    /**
     * @param array<string, mixed>|null $registryAuth
     *
     * @return array<int, string>
     */
    private function registryAuthHeader(?array $registryAuth): array
    {
        if ($registryAuth === null) {
            return [];
        }
        try {
            return ['X-Registry-Auth: ' . base64_encode(json_encode($registryAuth, JSON_THROW_ON_ERROR))];
        } catch (JsonException $e) {
            throw new RuntimeException('Failed to encode registry auth as JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Creates a new image from a container's changes.
     *
     * @param list<string> $changes Dockerfile-style instructions to apply while committing (e.g. 'CMD ["/app"]').
     * @param array<string, mixed>|null $config Optional container config to merge into the resulting image.
     */
    public function commit(string $containerId, ?string $repo = null, ?string $tag = null, ?string $comment = null, ?string $author = null, bool $pause = true, array $changes = [], ?array $config = null): DockerResponse
    {
        return $this->transport->request('POST', '/commit', $config, [
            'container' => $containerId,
            'repo' => $repo,
            'tag' => $tag,
            'comment' => $comment,
            'author' => $author,
            'pause' => $pause,
            'changes' => $changes === [] ? null : implode("\n", $changes),
        ]);
    }

    /**
     * Builds an image from a tar-encoded build context (the same archive `docker build` sends a directory containing a Dockerfile, tarred but not compressed, or gzip/bzip2/xz compressed).
     * Returns the full, non-streamed build log body. Use buildStream() to react to build progress as it happens.
     *
     * @param array<string, mixed> $query Supports e.g. 't' (tag), 'dockerfile', 'nocache', 'buildargs' (as a JSON-encoded string), 'platform'.
     */
    public function build(string $tarContent, array $query = []): DockerResponse
    {
        return $this->transport->requestRaw('POST', '/build', $tarContent, $query, ['Content-Type: application/x-tar']);
    }

    /**
     * Like build(), but invokes $onProgress with each decoded build
     * log line as it arrives. Return false from $onProgress to abort.
     *
     * @param array<string, mixed> $query
     * @param callable(array<string, mixed>): (bool|void) $onProgress
     */
    public function buildStream(string $tarContent, callable $onProgress, array $query = []): int
    {
        $lineBuffer = new NdjsonLineBuffer();
        return $this->transport->streamRaw('POST', '/build', $tarContent, $query, static fn(string $chunk): bool => $lineBuffer->push($chunk, $onProgress), ['Content-Type: application/x-tar']);
    }

    public function remove(string $name, bool $force = false, bool $noprune = false): DockerResponse
    {
        return $this->transport->request('DELETE', "/images/{$name}", null, ['force' => $force, 'noprune' => $noprune]);
    }

    public function tag(string $name, string $repo, ?string $tag = null): DockerResponse
    {
        return $this->transport->request('POST', "/images/{$name}/tag", null, ['repo' => $repo, 'tag' => $tag]);
    }

    public function history(string $name): DockerResponse
    {
        return $this->transport->request('GET', "/images/{$name}/history");
    }

    /** @param array<string, array<int, string>> $filters */
    public function prune(array $filters = []): DockerResponse
    {
        return $this->transport->request('POST', '/images/prune', null, ['filters' => $filters === [] ? null : $filters]);
    }
}
