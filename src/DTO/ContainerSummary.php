<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\DTO;

/**
 * One entry from `containers()->list()`.
 *
 * @see https://docs.docker.com/engine/api/latest/#tag/Container/operation/ContainerList
 */
final class ContainerSummary
{
    /**
     * @param list<string> $names Docker's own names, each still prefixed with a leading "/".
     * @param array<string, string> $labels
     * @param array<string, mixed> $raw The untouched, fully decoded source array.
     */
    public function __construct(
        public readonly string $id,
        public readonly array $names,
        public readonly string $image,
        public readonly string $imageId,
        public readonly string $command,
        public readonly int $created,
        public readonly string $state,
        public readonly string $status,
        public readonly array $labels,
        private readonly array $raw,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['Id'] ?? ''),
            names: array_values(array_map('strval', $data['Names'] ?? [])),
            image: (string) ($data['Image'] ?? ''),
            imageId: (string) ($data['ImageID'] ?? ''),
            command: (string) ($data['Command'] ?? ''),
            created: (int) ($data['Created'] ?? 0),
            state: (string) ($data['State'] ?? ''),
            status: (string) ($data['Status'] ?? ''),
            labels: $data['Labels'] ?? [],
            raw: $data,
        );
    }

    /**
     * The first name Docker assigned, without its leading "/". Empty
     * string if the container has no name (should not normally happen).
     */
    public function getName(): string
    {
        return ltrim($this->names[0] ?? '', '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }
}
