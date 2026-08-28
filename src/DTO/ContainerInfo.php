<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\DTO;

/**
 * Result of `containers()->inspect()`.
 *
 * @see https://docs.docker.com/engine/api/latest/#tag/Container/operation/ContainerInspect
 */
final class ContainerInfo
{
    /**
     * @param list<string> $args
     * @param array<string, mixed> $state The full `State` object (Running, Status, ExitCode, StartedAt, ...).
     * @param array<string, mixed> $config The full `Config` object (Env, Cmd, Labels, ExposedPorts, ...).
     * @param array<string, mixed> $raw The untouched, fully decoded source array.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $image,
        public readonly string $path,
        public readonly array $args,
        public readonly array $state,
        public readonly array $config,
        public readonly string $created,
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
            name: ltrim((string) ($data['Name'] ?? ''), '/'),
            image: (string) ($data['Image'] ?? ''),
            path: (string) ($data['Path'] ?? ''),
            args: array_values(array_map('strval', $data['Args'] ?? [])),
            state: $data['State'] ?? [],
            config: $data['Config'] ?? [],
            created: (string) ($data['Created'] ?? ''),
            raw: $data,
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isRunning(): bool
    {
        return (bool) ($this->state['Running'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }
}
