<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\DTO;

/**
 * A volume, from either `volumes()->list()` or `volumes()->inspect()` - the Engine API uses the same shape for both.
 *
 * @see https://docs.docker.com/engine/api/latest/#tag/Volume/operation/VolumeList
 */
final readonly class VolumeInfo
{
    /**
     * @param array<string, string> $labels
     * @param array<string, mixed> $options
     * @param array<string, mixed> $raw The untouched, fully decoded source array.
     */
    public function __construct(
        public string $name,
        public string $driver,
        public string $mountpoint,
        public string $createdAt,
        public string $scope,
        public array $labels,
        public array $options,
        private array $raw,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['Name'] ?? ''),
            driver: (string) ($data['Driver'] ?? ''),
            mountpoint: (string) ($data['Mountpoint'] ?? ''),
            createdAt: (string) ($data['CreatedAt'] ?? ''),
            scope: (string) ($data['Scope'] ?? ''),
            labels: $data['Labels'] ?? [],
            options: $data['Options'] ?? [],
            raw: $data,
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getMountpoint(): string
    {
        return $this->mountpoint;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getScope(): string
    {
        return $this->scope;
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
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }
}
