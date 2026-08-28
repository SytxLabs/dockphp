<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Resources;

use Sytxlabs\Dockphp\Http\DockerResponse;

/**
 * `docker exec` equivalent: run a new command inside a running
 * container. Output-only (read) streaming is supported via
 * startStream(); a fully interactive session (writing to stdin while
 * reading output) is out of scope — that needs a raw duplex socket,
 * which a per-call HTTP client like this one cannot provide.
 *
 * @see https://docs.docker.com/engine/api/latest/#tag/Exec
 */
final class Exec extends AbstractResource
{
    /**
     * Creates an exec instance for a running container. Returns the
     * new exec instance's Id (via `$response->json()['Id']`) — it must
     * be started with start()/startStream() to actually run.
     *
     * @param array<string, mixed> $config Supports e.g. 'Cmd', 'AttachStdout', 'AttachStderr', 'AttachStdin', 'Tty', 'Env', 'WorkingDir', 'User', 'Privileged'.
     */
    public function create(string $containerId, array $config): DockerResponse
    {
        return $this->transport->request('POST', "/containers/{$containerId}/exec", $config);
    }

    /**
     * Starts (runs) a previously created exec instance and returns its
     * full, non-streamed output. Unless the exec was created with
     * Tty=true, the output is multiplexed — see
     * {@see \Sytxlabs\Dockphp\Support\StdioDemultiplexer}.
     *
     * @param array<string, mixed> $options Supports 'Detach', 'Tty'.
     */
    public function start(string $execId, array $options = []): DockerResponse
    {
        return $this->transport->request('POST', "/exec/{$execId}/start", $options);
    }

    /**
     * Like start(), but streams output to $onChunk as it's produced.
     * Return false from $onChunk to stop reading early.
     *
     * @param array<string, mixed> $options Supports 'Tty'; 'Detach' is forced false.
     * @param callable(string): (bool|void) $onChunk
     */
    public function startStream(string $execId, callable $onChunk, array $options = []): int
    {
        $options['Detach'] = false;

        return $this->transport->stream('POST', "/exec/{$execId}/start", $options, [], $onChunk);
    }

    /**
     * Resizes the TTY of a running exec instance.
     */
    public function resize(string $execId, int $height, int $width): DockerResponse
    {
        return $this->transport->request('POST', "/exec/{$execId}/resize", null, [
            'h' => $height,
            'w' => $width,
        ]);
    }

    public function inspect(string $execId): DockerResponse
    {
        return $this->transport->request('GET', "/exec/{$execId}/json");
    }
}
