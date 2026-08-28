<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\DTO;

/**
 * Result of `images()->inspect()`.
 *
 * @see https://docs.docker.com/engine/api/latest/#tag/Image/operation/ImageInspect
 */
final class ImageInfo
{
    /**
     * @param list<string> $repoTags Empty when the image is untagged.
     * @param list<string> $repoDigests
     * @param array<string, mixed> $config The full `Config` object (Env, Cmd, Labels, ExposedPorts, ...).
     * @param array<string, mixed> $raw The untouched, fully decoded source array.
     */
    public function __construct(
        public readonly string $id,
        public readonly array $repoTags,
        public readonly array $repoDigests,
        public readonly string $parent,
        public readonly string $comment,
        public readonly string $created,
        public readonly string $author,
        public readonly string $architecture,
        public readonly string $os,
        public readonly int $size,
        public readonly array $config,
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
            repoTags: array_values(array_map('strval', $data['RepoTags'] ?? [])),
            repoDigests: array_values(array_map('strval', $data['RepoDigests'] ?? [])),
            parent: (string) ($data['Parent'] ?? ''),
            comment: (string) ($data['Comment'] ?? ''),
            created: (string) ($data['Created'] ?? ''),
            author: (string) ($data['Author'] ?? ''),
            architecture: (string) ($data['Architecture'] ?? ''),
            os: (string) ($data['Os'] ?? ''),
            size: (int) ($data['Size'] ?? 0),
            config: $data['Config'] ?? [],
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

    public function getParent(): string
    {
        return $this->parent;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function getCreated(): string
    {
        return $this->created;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getArchitecture(): string
    {
        return $this->architecture;
    }

    public function getOs(): string
    {
        return $this->os;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }
}
