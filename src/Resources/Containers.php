<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Resources;

use Sytxlabs\Dockphp\DTO\ContainerInfo;
use Sytxlabs\Dockphp\DTO\ContainerSummary;
use Sytxlabs\Dockphp\Http\DockerResponse;

/**
 * @see https://docs.docker.com/engine/api/latest/#tag/Container
 */
final class Containers extends AbstractResource
{
    /**
     * @param array<string, mixed> $query Supports e.g. 'all', 'limit', 'size', 'filters'.
     *
     * @return list<ContainerSummary>
     */
    public function list(array $query = []): array
    {
        $response = $this->transport->request('GET', '/containers/json', null, $query);

        return array_map(
            static fn (array $item): ContainerSummary => ContainerSummary::fromArray($item),
            $this->decodeList($response),
        );
    }

    public function inspect(string $id, bool $size = false): ContainerInfo
    {
        $response = $this->transport->request('GET', "/containers/{$id}/json", null, ['size' => $size]);

        return ContainerInfo::fromArray($this->decodeObject($response));
    }

    /**
     * Creates a container. Accepts the full Docker container config as
     * a single array; 'name' (and 'platform', if present) are split out
     * into query parameters as required by the Engine API, the rest is
     * sent as the JSON request body.
     *
     * @param array<string, mixed> $config
     */
    public function create(array $config): DockerResponse
    {
        $query = [];

        if (isset($config['name'])) {
            $query['name'] = $config['name'];
            unset($config['name']);
        }

        if (isset($config['platform'])) {
            $query['platform'] = $config['platform'];
            unset($config['platform']);
        }

        return $this->transport->request('POST', '/containers/create', $config, $query);
    }

    public function start(string $id, ?string $detachKeys = null): DockerResponse
    {
        return $this->transport->request('POST', "/containers/{$id}/start", null, [
            'detachKeys' => $detachKeys,
        ]);
    }

    public function stop(string $id, ?int $timeoutSeconds = null, ?string $signal = null): DockerResponse
    {
        return $this->transport->request('POST', "/containers/{$id}/stop", null, [
            't' => $timeoutSeconds,
            'signal' => $signal,
        ]);
    }

    public function restart(string $id, ?int $timeoutSeconds = null, ?string $signal = null): DockerResponse
    {
        return $this->transport->request('POST', "/containers/{$id}/restart", null, [
            't' => $timeoutSeconds,
            'signal' => $signal,
        ]);
    }

    public function kill(string $id, string $signal = 'SIGKILL'): DockerResponse
    {
        return $this->transport->request('POST', "/containers/{$id}/kill", null, [
            'signal' => $signal,
        ]);
    }

    public function pause(string $id): DockerResponse
    {
        return $this->transport->request('POST', "/containers/{$id}/pause");
    }

    public function unpause(string $id): DockerResponse
    {
        return $this->transport->request('POST', "/containers/{$id}/unpause");
    }

    public function rename(string $id, string $newName): DockerResponse
    {
        return $this->transport->request('POST', "/containers/{$id}/rename", null, [
            'name' => $newName,
        ]);
    }

    public function remove(string $id, bool $force = false, bool $removeVolumes = false, bool $removeLinks = false): DockerResponse
    {
        return $this->transport->request('DELETE', "/containers/{$id}", null, [
            'force' => $force,
            'v' => $removeVolumes,
            'link' => $removeLinks,
        ]);
    }

    /**
     * Returns the full (non-streamed) log output. Note: unless the
     * container was created with a TTY, Docker multiplexes stdout and
     * stderr into a framed binary format — run the body through
     * {@see \Sytxlabs\Dockphp\Support\StdioDemultiplexer::demuxAll()}
     * to split it back into per-stream text.
     *
     * @param array<string, mixed> $query Supports 'stdout', 'stderr', 'since', 'until', 'timestamps', 'tail'.
     */
    public function logs(string $id, array $query = []): DockerResponse
    {
        $query += ['stdout' => true, 'stderr' => true, 'follow' => false];

        return $this->transport->request('GET', "/containers/{$id}/logs", null, $query);
    }

    /**
     * Streams log output as it's produced ('follow' forced true). Feed
     * chunks through {@see \Sytxlabs\Dockphp\Support\StdioDemultiplexer}
     * if the container has no TTY. Return false from $onChunk to stop
     * following.
     *
     * @param array<string, mixed> $query Supports 'stdout', 'stderr', 'since', 'timestamps', 'tail'.
     * @param callable(string): (bool|void) $onChunk
     */
    public function logsStream(string $id, callable $onChunk, array $query = []): int
    {
        $query += ['stdout' => true, 'stderr' => true];
        $query['follow'] = true;

        return $this->transport->stream('GET', "/containers/{$id}/logs", null, $query, $onChunk);
    }

    /**
     * Single stats snapshot.
     */
    public function stats(string $id): DockerResponse
    {
        return $this->transport->request('GET', "/containers/{$id}/stats", null, [
            'stream' => false,
        ]);
    }

    /**
     * Streams stats snapshots continuously. Each chunk is a JSON object;
     * see {@see \Sytxlabs\Dockphp\Support\NdjsonLineBuffer} to decode
     * them line by line. Return false from $onChunk to stop.
     *
     * @param callable(string): (bool|void) $onChunk
     */
    public function statsStream(string $id, callable $onChunk): int
    {
        return $this->transport->stream('GET', "/containers/{$id}/stats", null, [
            'stream' => true,
        ], $onChunk);
    }

    public function top(string $id, ?string $psArgs = null): DockerResponse
    {
        return $this->transport->request('GET', "/containers/{$id}/top", null, [
            'ps_args' => $psArgs,
        ]);
    }

    /**
     * Blocks until the container stops (or the given condition is met),
     * then returns the exit status. Relies on the client's configured
     * request timeout — pass a longer $timeout to DockerClient if you
     * expect a long wait.
     */
    public function wait(string $id, string $condition = 'not-running'): DockerResponse
    {
        return $this->transport->request('POST', "/containers/{$id}/wait", null, [
            'condition' => $condition,
        ]);
    }

    /**
     * Lists filesystem changes (added/changed/deleted paths) since the
     * container was created.
     */
    public function changes(string $id): DockerResponse
    {
        return $this->transport->request('GET', "/containers/{$id}/changes");
    }

    /**
     * Downloads a tar archive of a path inside the container. Read the
     * raw tar bytes via DockerResponse::getBody().
     */
    public function getArchive(string $id, string $path): DockerResponse
    {
        return $this->transport->request('GET', "/containers/{$id}/archive", null, [
            'path' => $path,
        ]);
    }

    /**
     * Uploads a tar archive to be extracted at $path inside the
     * container. $tarContent must be a valid (optionally gzip/bzip2/xz
     * compressed) tar stream.
     */
    public function putArchive(
        string $id,
        string $path,
        string $tarContent,
        bool $noOverwriteDirNonDir = false,
        bool $copyUIDGID = false,
    ): DockerResponse {
        return $this->transport->requestRaw('PUT', "/containers/{$id}/archive", $tarContent, [
            'path' => $path,
            'noOverwriteDirNonDir' => $noOverwriteDirNonDir,
            'copyUIDGID' => $copyUIDGID,
        ], ['Content-Type: application/x-tar']);
    }

    /**
     * Exports the entire container filesystem as a tar archive. Read
     * the raw tar bytes via DockerResponse::getBody().
     */
    public function export(string $id): DockerResponse
    {
        return $this->transport->request('GET', "/containers/{$id}/export");
    }

    /**
     * Streams a container's stdout/stderr (read-only). This is not a
     * full interactive attach — stdin cannot be interleaved with
     * reading output over a single request/response cURL call; use
     * $stdin to send one fixed block of input up front if needed.
     *
     * @param array<string, mixed> $query Supports 'stdout', 'stderr', 'stream', 'logs'.
     * @param callable(string): (bool|void) $onChunk
     */
    public function attachStream(string $id, callable $onChunk, array $query = []): int
    {
        $query += ['stdout' => true, 'stderr' => true, 'stream' => true, 'logs' => false];

        return $this->transport->stream('POST', "/containers/{$id}/attach", null, $query, $onChunk);
    }

    /**
     * Removes all stopped containers.
     *
     * @param array<string, array<int, string>> $filters
     */
    public function prune(array $filters = []): DockerResponse
    {
        return $this->transport->request('POST', '/containers/prune', null, [
            'filters' => $filters === [] ? null : $filters,
        ]);
    }

    /**
     * Updates a running (or stopped) container's resource limits without
     * recreating it.
     *
     * @param array<string, mixed> $resources Supports e.g. 'Memory', 'MemorySwap', 'CpuShares', 'CpuPeriod', 'CpuQuota', 'RestartPolicy'.
     */
    public function update(string $id, array $resources): DockerResponse
    {
        return $this->transport->request('POST', "/containers/{$id}/update", $resources);
    }
}
