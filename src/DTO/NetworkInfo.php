<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\DTO;

/**
 * A network, from either `networks()->list()` or `networks()->inspect()` - the Engine API uses the same shape for both.
 *
 * @see https://docs.docker.com/engine/api/latest/#tag/Network/operation/NetworkList
 */
final readonly class NetworkInfo
{
    /**
     * @param array<string, string> $labels
     * @param array<string, mixed> $options
     * @param array<string, mixed> $raw The untouched, fully decoded source array.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $driver,
        public string $scope,
        public bool $internal,
        public bool $attachable,
        public string $created,
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
            id: (string) ($data['Id'] ?? ''),
            name: (string) ($data['Name'] ?? ''),
            driver: (string) ($data['Driver'] ?? ''),
            scope: (string) ($data['Scope'] ?? ''),
            internal: (bool) ($data['Internal'] ?? false),
            attachable: (bool) ($data['Attachable'] ?? false),
            created: (string) ($data['Created'] ?? ''),
            labels: $data['Labels'] ?? [],
            options: $data['Options'] ?? [],
            raw: $data,
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getInternal(): bool
    {
        return $this->internal;
    }

    public function getAttachable(): bool
    {
        return $this->attachable;
    }

    public function getCreated(): string
    {
        return $this->created;
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
