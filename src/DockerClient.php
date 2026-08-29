<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp;

use Sytxlabs\Dockphp\Http\DockerTransport;
use Sytxlabs\Dockphp\Http\DockerTransportInterface;
use Sytxlabs\Dockphp\Resources\Containers;
use Sytxlabs\Dockphp\Resources\Exec;
use Sytxlabs\Dockphp\Resources\Images;
use Sytxlabs\Dockphp\Resources\Networks;
use Sytxlabs\Dockphp\Resources\System;
use Sytxlabs\Dockphp\Resources\Volumes;

/**
 * Entry point for the Docker Engine API client.
 *
 * $docker = new DockerClient('/var/run/docker.sock');
 * $docker->containers()->list();
 */
final class DockerClient
{
    private DockerTransportInterface $transport;

    private ?Containers $containers = null;
    private ?Images $images = null;
    private ?Networks $networks = null;
    private ?Volumes $volumes = null;
    private ?System $system = null;
    private ?Exec $exec = null;

    /**
     * @param string $socketPath Path to the Docker Engine Unix socket.
     * @param string|null $apiVersion Manually pin the API version (e.g. "1.43"). When null, it is auto-detected from `/version` on first use.
     * @param float $connectTimeout Connection timeout in seconds.
     * @param float $timeout Total request timeout in seconds.
     */
    public function __construct(string $socketPath = '/var/run/docker.sock', ?string $apiVersion = null, float $connectTimeout = 5.0, float $timeout = 30.0)
    {
        $this->transport = DockerTransport::forSocket($socketPath, $apiVersion, $connectTimeout, $timeout);
    }

    /**
     * Connects to a Docker Engine exposed over TCP instead of a Unix socket (e.g. a remote host, or Docker Desktop's TCP endpoint).
     *
     * @param string $host Hostname or IP only, without scheme.
     * @param string|null $caFile Path to a CA bundle used to verify the server certificate.
     * @param string|null $certFile Path to the client certificate (mutual TLS).
     * @param string|null $keyFile Path to the client private key (mutual TLS).
     */
    public static function tcp(string $host, int $port = 2375, bool $tls = false, ?string $caFile = null, ?string $certFile = null, ?string $keyFile = null, ?string $apiVersion = null, float $connectTimeout = 5.0, float $timeout = 30.0): self
    {
        $client = new self();
        $client->transport = DockerTransport::forTcp($host, $port, $tls, $caFile, $certFile, $keyFile, $apiVersion, $connectTimeout, $timeout);
        return $client;
    }

    public function containers(): Containers
    {
        return $this->containers ??= new Containers($this->transport);
    }

    public function images(): Images
    {
        return $this->images ??= new Images($this->transport);
    }

    public function networks(): Networks
    {
        return $this->networks ??= new Networks($this->transport);
    }

    public function volumes(): Volumes
    {
        return $this->volumes ??= new Volumes($this->transport);
    }

    public function system(): System
    {
        return $this->system ??= new System($this->transport);
    }

    public function exec(): Exec
    {
        return $this->exec ??= new Exec($this->transport);
    }

    public function getApiVersion(): string
    {
        return $this->transport->getApiVersion();
    }
}
