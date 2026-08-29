<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Resources;

use Sytxlabs\Dockphp\Http\DockerResponse;
use Sytxlabs\Dockphp\Support\NdjsonLineBuffer;

/**
 * @see https://docs.docker.com/engine/api/latest/#tag/System
 */
final class System extends AbstractResource
{
    public function info(): DockerResponse
    {
        return $this->transport->request('GET', '/info');
    }

    public function version(): DockerResponse
    {
        return $this->transport->request('GET', '/version');
    }

    public function ping(): bool
    {
        return $this->transport->request('GET', '/_ping')->isSuccessful();
    }

    public function df(): DockerResponse
    {
        return $this->transport->request('GET', '/system/df');
    }

    /**
     * Streams Docker's real-time event feed (container/image/network/volume lifecycle events).
     * This call blocks until the connection ends or $onEvent returns false - pass a generous $timeout to DockerClient (or expect it to run until the process is killed).
     *
     * @param array<string, array<int, string>> $filters
     * @param callable(array<string, mixed>): (bool|void) $onEvent
     */
    public function events(callable $onEvent, array $filters = [], ?string $since = null, ?string $until = null): void
    {
        $lineBuffer = new NdjsonLineBuffer();
        $this->transport->stream('GET', '/events', null, ['filters' => $filters === [] ? null : $filters, 'since' => $since, 'until' => $until], static fn(string $chunk): bool => $lineBuffer->push($chunk, $onEvent));
    }

    /**
     * Prunes containers, images, networks and the build cache in one call. Note this does not include volumes - use Volumes::prune() for those, matching the Engine API's own separation.
     *
     * @param array<string, array<int, string>> $filters
     */
    public function prune(array $filters = []): DockerResponse
    {
        return $this->transport->request('POST', '/system/prune', null, ['filters' => $filters === [] ? null : $filters]);
    }
}
