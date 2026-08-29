<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\DTO;

/**
 * One entry from `images()->list()`.
 *
 * @see https://docs.docker.com/engine/api/latest/#tag/Image/operation/ImageList
 */
final readonly class ImageSummary
{
    /**
     * @param list<string> $repoTags Empty when the image is untagged (`<none>:<none>`).
     * @param list<string> $repoDigests
     * @param array<string, string> $labels
     * @param array<string, mixed> $raw The untouched, fully decoded source array.
     */
    public function __construct(
        public string $id,
        public string $parentId,
        public array $repoTags,
        public array $repoDigests,
        public int $created,
        public int $size,
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
            parentId: (string) ($data['ParentId'] ?? ''),
            repoTags: array_values(array_map('strval', $data['RepoTags'] ?? [])),
            repoDigests: array_values(array_map('strval', $data['RepoDigests'] ?? [])),
            created: (int) ($data['Created'] ?? 0),
            size: (int) ($data['Size'] ?? 0),
            labels: $data['Labels'] ?? [],
            raw: $data,
        );
    }

    /**
     * The first repo:tag, or null for an untagged image.
     */
    public function getName(): ?string
    {
        return $this->repoTags[0] ?? null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getParentId(): string
    {
        return $this->parentId;
    }

    /**
     * @return list<string>
     */
    public function getRepoTags(): array
    {
        return $this->repoTags;
    }

    /**
     * @return list<string>
     */
    public function getRepoDigests(): array
    {
        return $this->repoDigests;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getSize(): int
    {
        return $this->size;
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
