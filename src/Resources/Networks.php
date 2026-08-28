<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Resources;

use Sytxlabs\Dockphp\DTO\NetworkInfo;
use Sytxlabs\Dockphp\Http\DockerResponse;

/**
 * @see https://docs.docker.com/engine/api/latest/#tag/Network
 */
final class Networks extends AbstractResource
{
    /**
     * @param array<string, mixed> $filters
     *
     * @return list<NetworkInfo>
     */
    public function list(array $filters = []): array
    {
        $response = $this->transport->request('GET', '/networks', null, [
            'filters' => $filters === [] ? null : $filters,
        ]);

        return array_map(
            static fn (array $item): NetworkInfo => NetworkInfo::fromArray($item),
            $this->decodeList($response),
        );
    }

    public function inspect(string $id, bool $verbose = false, ?string $scope = null): NetworkInfo
    {
        $response = $this->transport->request('GET', "/networks/{$id}", null, [
            'verbose' => $verbose,
            'scope' => $scope,
        ]);

        return NetworkInfo::fromArray($this->decodeObject($response));
    }

    /**
     * @param array<string, mixed> $options Additional network config (Driver, IPAM, Labels, ...).
     */
    public function create(string $name, array $options = []): DockerResponse
    {
        $body = ['Name' => $name] + $options;

        return $this->transport->request('POST', '/networks/create', $body);
    }

    /**
     * @param array<string, mixed> $endpointConfig
     */
    public function connect(string $id, string $containerId, array $endpointConfig = []): DockerResponse
    {
        $body = ['Container' => $containerId];

        if ($endpointConfig !== []) {
            $body['EndpointConfig'] = $endpointConfig;
        }

        return $this->transport->request('POST', "/networks/{$id}/connect", $body);
    }

    public function disconnect(string $id, string $containerId, bool $force = false): DockerResponse
    {
        return $this->transport->request('POST', "/networks/{$id}/disconnect", [
            'Container' => $containerId,
            'Force' => $force,
        ]);
    }

    public function remove(string $id): DockerResponse
    {
        return $this->transport->request('DELETE', "/networks/{$id}");
    }

    /**
     * @param array<string, array<int, string>> $filters
     */
    public function prune(array $filters = []): DockerResponse
    {
        return $this->transport->request('POST', '/networks/prune', null, [
            'filters' => $filters === [] ? null : $filters,
        ]);
    }
}
