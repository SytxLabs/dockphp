<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Resources;

use Sytxlabs\Dockphp\DTO\VolumeInfo;
use Sytxlabs\Dockphp\Http\DockerResponse;

/**
 * @see https://docs.docker.com/engine/api/latest/#tag/Volume
 */
final class Volumes extends AbstractResource
{
    /**
     * @param array<string, mixed> $filters
     *
     * @return list<VolumeInfo>
     */
    public function list(array $filters = []): array
    {
        $data = $this->decodeObject($this->transport->request('GET', '/volumes', null, ['filters' => $filters === [] ? null : $filters]));
        return array_map(static fn(mixed $item): VolumeInfo => VolumeInfo::fromArray(is_array($item) ? $item : []), array_values(is_array($data['Volumes'] ?? null) ? $data['Volumes'] : []), );
    }

    public function inspect(string $name): VolumeInfo
    {
        return VolumeInfo::fromArray($this->decodeObject($this->transport->request('GET', "/volumes/{$name}")));
    }

    /**
     * @param array<string, mixed> $options Additional volume config (Driver, DriverOpts, Labels, ...).
     */
    public function create(string $name, array $options = []): DockerResponse
    {
        return $this->transport->request('POST', '/volumes/create', ['Name' => $name] + $options);
    }

    public function remove(string $name, bool $force = false): DockerResponse
    {
        return $this->transport->request('DELETE', "/volumes/{$name}", null, ['force' => $force]);
    }

    /**
     * @param array<string, array<int, string>> $filters
     */
    public function prune(array $filters = []): DockerResponse
    {
        return $this->transport->request('POST', '/volumes/prune', null, ['filters' => $filters === [] ? null : $filters]);
    }
}
