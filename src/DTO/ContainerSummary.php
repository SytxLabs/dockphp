<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\DTO;

/**
 * One entry from `containers()->list()`.
 *
 * @see https://docs.docker.com/engine/api/latest/#tag/Container/operation/ContainerList
 */
final readonly class ContainerSummary
{
    /**
     * @param list<string> $names Docker's own names, each still prefixed with a leading "/".
     * @param array<string, string> $labels
     * @param array<string, mixed> $raw The untouched, fully decoded source array.
     */
    public function __construct(
        public string $id,
        public array $names,
        public string $image,
        public string $imageId,
        public string $command,
        public int $created,
        public string $state,
        public string $status,
        public array $labels,
        private array $raw,
    ) {}

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

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        return $this->names;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function getImageId(): string
    {
        return $this->imageId;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }
}
