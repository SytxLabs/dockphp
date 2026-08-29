<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Resources;

use Sytxlabs\Dockphp\Exceptions\DockerException;
use Sytxlabs\Dockphp\Http\DockerResponse;
use Sytxlabs\Dockphp\Http\DockerTransportInterface;

abstract class AbstractResource
{
    public function __construct(protected readonly DockerTransportInterface $transport) {}

    /**
     * Decodes a successful response body as a JSON object, for hydrating DTOs. Throws if the Engine unexpectedly returned an empty or non-object body on a 2xx response.
     *
     * @return array<string, mixed>
     */
    protected function decodeObject(DockerResponse $response): array
    {
        $data = $response->json();
        return $data ?? throw new DockerException('Expected a JSON object in the Docker Engine response, got an empty or invalid body.');
    }

    /**
     * Decodes a successful response body as a JSON array of objects.
     *
     * @return list<array<string, mixed>>
     */
    protected function decodeList(DockerResponse $response): array
    {
        $data = $response->json();
        return $data !== null ? array_values(array_map(static fn(mixed $item): array => is_array($item) ? $item : [], $data)) : [];
    }
}
