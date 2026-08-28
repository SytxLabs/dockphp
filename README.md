# DockPHP

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.2-777bb4.svg)](composer.json)

Framework-agnostic PHP client for the Docker Engine API, talking directly to
`/var/run/docker.sock` over HTTP (via Guzzle).

- Plain HTTP against the Docker Engine API, over the Unix socket

## Contents

- [Installation](#installation)
- [Quick start](#quick-start)
- [Typed results](#typed-results)
- [API version handling](#api-version-handling)
- [Timeouts](#timeouts)
- [Error handling](#error-handling)
- [Security note](#security-note)
- [Streaming](#streaming-logs-stats-pullbuild-progress-events)
- [Demultiplexing container logs](#demultiplexing-container-logs)
- [exec](#exec)
- [TCP / TLS hosts](#tcp--tls-hosts)
- [Registry authentication](#registry-authentication-pullpush)
- [Testing](#testing)

## Installation

```bash
composer require sytxlabs/dockphp
```

## Quick start

```php
use Sytxlabs\Dockphp\DockerClient;

$docker = new DockerClient('/var/run/docker.sock');

// Containers
$docker->containers()->list();
$docker->containers()->inspect('container-id');
$docker->containers()->start('container-id');
$docker->containers()->stop('container-id');
$docker->containers()->restart('container-id');
$docker->containers()->remove('container-id');
$docker->containers()->logs('container-id');

// Images
$docker->images()->list();
$docker->images()->inspect('nginx:latest');
$docker->images()->pull('nginx', 'latest');
$docker->images()->remove('image-id');

// Networks
$docker->networks()->list();
$docker->networks()->create('my-network');
$docker->networks()->remove('network-id');

// Volumes
$docker->volumes()->list();
$docker->volumes()->create('my-volume');
$docker->volumes()->remove('my-volume');
```

## Typed results

`list()` and `inspect()` on Containers, Images, Networks and Volumes return
typed DTOs — not raw JSON — so you get autocompletion and don't have to
remember Docker's field names:

```php
$container = $docker->containers()->inspect('container-id');
echo $container->getName();      // "web", not "/web"
echo $container->isRunning();    // bool, reads State.Running for you

$image = $docker->images()->inspect('nginx:latest');
echo $image->getName();          // "nginx:latest" (first RepoTag), or null if untagged

foreach ($docker->containers()->list(['all' => true]) as $summary) {
    echo $summary->getName(), ' — ', $summary->status, "\n";
}
```

| Method | Returns |
|---|---|
| `containers()->list()` | `list<DTO\ContainerSummary>` |
| `containers()->inspect()` | `DTO\ContainerInfo` |
| `images()->list()` | `list<DTO\ImageSummary>` |
| `images()->inspect()` | `DTO\ImageInfo` |
| `networks()->list()` / `inspect()` | `list<DTO\NetworkInfo>` / `DTO\NetworkInfo` |
| `volumes()->list()` / `inspect()` | `list<DTO\VolumeInfo>` / `DTO\VolumeInfo` |

Every DTO only models the commonly-needed fields (id, name, state, labels,
...) — call `->raw()` on any of them to get the full, untouched decoded
array for anything not promoted to a typed property.

Every other resource method (`start()`, `stop()`, `remove()`, `create()`,
...) still returns a `Sytxlabs\Dockphp\Http\DockerResponse` — there's no
useful entity to hydrate from an action's ack/204 response. Use `->json()`
for the decoded body or `->getBody()` for the raw string.

### Creating a container

`containers()->create()` accepts the full Docker container config as a
single array. Query-only parameters (`name`, `platform`) are automatically
split out of the array and sent as query string parameters, the rest is
sent as the JSON request body — you never have to think about the split:

```php
$container = $docker->containers()->create([
    'Image' => 'nginx:latest',
    'name' => 'web',
    'HostConfig' => [
        'PortBindings' => [
            '80/tcp' => [
                ['HostPort' => '8080'],
            ],
        ],
    ],
]);

$id = $container->json()['Id'];
$docker->containers()->start($id);
```

### Networks and volumes

`networks()->create()` and `volumes()->create()` take the name directly,
with an optional array of additional Docker options:

```php
$docker->networks()->create('my-network', ['Driver' => 'bridge']);
$docker->volumes()->create('my-volume', ['Driver' => 'local']);
```

## API version handling

By default, the API version is **not** fetched eagerly when you construct
`DockerClient` — it is resolved lazily via a single `GET /version` call the
first time a request is made, then cached for the lifetime of the client.

You can override it manually to skip that lookup entirely and pin a
specific version:

```php
$docker = new DockerClient('/var/run/docker.sock', apiVersion: '1.43');
```

## Timeouts

```php
$docker = new DockerClient(
    socketPath: '/var/run/docker.sock',
    connectTimeout: 5.0, // seconds
    timeout: 30.0,       // seconds
);
```

## Error handling

- `Sytxlabs\Dockphp\Exceptions\DockerConnectionException` — the socket
  could not be reached at all (missing socket, connection refused, timeout).
- `Sytxlabs\Dockphp\Exceptions\DockerApiException` — the Engine responded
  with a non-2xx HTTP status. Carries `getStatusCode()` and
  `getDockerMessage()` (Docker's own JSON error message, when present).
- `Sytxlabs\Dockphp\Exceptions\DockerNotFoundException` — a `DockerApiException`
  subclass specifically for HTTP 404 (e.g. inspecting a container that
  doesn't exist).

```php
use Sytxlabs\Dockphp\Exceptions\DockerNotFoundException;
use Sytxlabs\Dockphp\Exceptions\DockerApiException;
use Sytxlabs\Dockphp\Exceptions\DockerConnectionException;

try {
    $docker->containers()->inspect('does-not-exist');
} catch (DockerNotFoundException $e) {
    // 404
} catch (DockerApiException $e) {
    // any other non-2xx response
    $e->getStatusCode();
    $e->getDockerMessage();
} catch (DockerConnectionException $e) {
    // could not reach the socket at all
}
```

## Security note

The Docker Engine API, reached through `/var/run/docker.sock`, grants
practically full control over the Docker host. **This package never
changes socket permissions itself** (no `chmod`, no ownership changes) —
managing who can read/write that socket is entirely up to you and your
deployment. Only grant access to the socket to trusted, trusted-equivalent
code.

## Streaming (logs, stats, pull/build progress, events)

Anything that can run indefinitely or produce output incrementally has a
`*Stream()`/`*Stream` counterpart that invokes a callback per chunk instead
of buffering the whole response. Return `false` from the callback to stop
early:

```php
// Follow logs
$docker->containers()->logsStream('web', function (string $chunk) {
    echo $chunk;
});

// Live stats
$docker->containers()->statsStream('web', function (string $chunk) {
    // one or more JSON objects per chunk — see NdjsonLineBuffer below
});

// Pull with progress
$docker->images()->pullStream('nginx', 'latest', null, function (array $event) {
    echo $event['status'] ?? '', "\n";
});

// Build with progress
$docker->images()->buildStream($tarContent, function (array $event) {
    echo $event['stream'] ?? '';
});

// Real-time event feed
$docker->system()->events(function (array $event) {
    echo $event['Type'], ' ', $event['Action'], "\n";
});
```

`pullStream()`/`buildStream()`/`events()` already decode newline-delimited
JSON for you via `Sytxlabs\Dockphp\Support\NdjsonLineBuffer`. `logsStream()`
and `attachStream()` hand you raw bytes instead — see the demux section
below.

## Demultiplexing container logs

Unless a container was created with a TTY, Docker interleaves stdout and
stderr into one stream using an 8-byte frame header per chunk of output.
`Sytxlabs\Dockphp\Support\StdioDemultiplexer` decodes that format:

```php
use Sytxlabs\Dockphp\Support\StdioDemultiplexer;

$response = $docker->containers()->logs('web');
foreach (StdioDemultiplexer::demuxAll($response->getBody()) as $frame) {
    echo "[{$frame['stream']}] {$frame['payload']}";
}
```

For `logsStream()`/`attachStream()`, use a stateful instance instead so
frames split across chunks are handled correctly:

```php
$demux = new StdioDemultiplexer();
$docker->containers()->logsStream('web', function (string $chunk) use ($demux) {
    $demux->push($chunk, function (string $stream, string $payload) {
        echo "[$stream] $payload";
    });
});
```

## exec

```php
$exec = $docker->exec()->create('web', [
    'Cmd' => ['ls', '-la'],
    'AttachStdout' => true,
    'AttachStderr' => true,
])->json()['Id'];

$output = $docker->exec()->start($exec)->getBody();
```

Not supported: a fully interactive exec/attach session (writing to stdin
while concurrently reading output). That needs a raw duplex socket outside
of normal HTTP request/response semantics, which a per-call cURL client
cannot provide. `exec()->startStream()` and `containers()->attachStream()`
cover the read side; send a fixed block of input up front via the request
body if you need to feed stdin.

## TCP / TLS hosts

The Unix socket is the default and recommended way to connect. To reach a
Docker Engine exposed over TCP instead (a remote host, or Docker Desktop's
TCP endpoint):

```php
$docker = DockerClient::tcp('docker.example.com', 2376, tls: true, caFile: '/path/ca.pem', certFile: '/path/cert.pem', keyFile: '/path/key.pem');
```

## Registry authentication (pull/push)

`pull()`, `pullStream()`, `push()` and `pushStream()` take an optional
`$registryAuth` array — the usual Docker auth config
(`username`/`password`/`serveraddress`, or `identitytoken`). It's sent as
the base64-encoded `X-Registry-Auth` header Docker expects:

```php
$docker->images()->pull('registry.example.com/private/app', 'latest', registryAuth: [
    'username' => 'me',
    'password' => 'secret',
    'serveraddress' => 'registry.example.com',
]);

$docker->images()->push('registry.example.com/private/app', 'latest', registryAuth: [
    'username' => 'me',
    'password' => 'secret',
    'serveraddress' => 'registry.example.com',
]);
```

## Testing

```bash
composer install
composer test              # unit tests, no Docker required
composer test:integration  # integration tests, requires a real Docker socket
composer stan               # static analysis (PHPStan)
```

Integration tests automatically skip themselves when
`/var/run/docker.sock` is not present (e.g. on Windows, or a machine
without Docker installed) — no configuration required.

## License

MIT — see [LICENSE](LICENSE).
