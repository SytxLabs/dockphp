<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\DTO\ContainerInfo;
use Sytxlabs\Dockphp\DTO\ContainerSummary;
use Sytxlabs\Dockphp\Http\DockerResponse;
use Sytxlabs\Dockphp\Resources\Containers;
use Sytxlabs\Dockphp\Tests\Support\FakeDockerTransport;

final class ContainersTest extends TestCase
{
    private FakeDockerTransport $transport;
    private Containers $containers;

    protected function setUp(): void
    {
        $this->transport = new FakeDockerTransport();
        $this->containers = new Containers($this->transport);
    }

    public function testListSendsGetRequestAndHydratesSummaries(): void
    {
        $this->transport->setResponse(new DockerResponse(200, '[{"Id":"abc123","Names":["/web"]}]'));

        $result = $this->containers->list(['all' => true]);

        $call = $this->transport->lastCall();

        self::assertSame('GET', $call['method']);
        self::assertSame('/containers/json', $call['path']);
        self::assertSame(['all' => true], $call['query']);
        self::assertNull($call['body']);

        self::assertCount(1, $result);
        self::assertInstanceOf(ContainerSummary::class, $result[0]);
        self::assertSame('abc123', $result[0]->id);
        self::assertSame('web', $result[0]->getName());
    }

    public function testInspectBuildsIdInPathAndHydratesInfo(): void
    {
        $this->transport->setResponse(new DockerResponse(200, '{"Id":"abc123","Name":"/web"}'));

        $result = $this->containers->inspect('abc123');

        $call = $this->transport->lastCall();

        self::assertInstanceOf(ContainerInfo::class, $result);
        self::assertSame('abc123', $result->id);
        self::assertSame('web', $result->getName());
        self::assertSame('GET', $call['method']);
        self::assertSame('/containers/abc123/json', $call['path']);
        self::assertSame(['size' => false], $call['query']);
    }

    public function testCreateSplitsNameIntoQueryAndRestIntoBody(): void
    {
        $this->containers->create([
            'Image' => 'nginx:latest',
            'name' => 'web',
            'HostConfig' => [
                'PortBindings' => [
                    '80/tcp' => [['HostPort' => '8080']],
                ],
            ],
        ]);

        $call = $this->transport->lastCall();

        self::assertSame('POST', $call['method']);
        self::assertSame('/containers/create', $call['path']);
        self::assertSame(['name' => 'web'], $call['query']);
        self::assertSame([
            'Image' => 'nginx:latest',
            'HostConfig' => [
                'PortBindings' => [
                    '80/tcp' => [['HostPort' => '8080']],
                ],
            ],
        ], $call['body']);
    }

    public function testCreateSplitsPlatformIntoQuery(): void
    {
        $this->containers->create(['Image' => 'nginx:latest', 'platform' => 'linux/amd64']);

        $call = $this->transport->lastCall();

        self::assertSame(['platform' => 'linux/amd64'], $call['query']);
        self::assertSame(['Image' => 'nginx:latest'], $call['body']);
    }

    public function testCreateWithoutNameSendsNoQuery(): void
    {
        $this->containers->create(['Image' => 'nginx:latest']);

        $call = $this->transport->lastCall();

        self::assertSame([], $call['query']);
    }

    public function testStartPostsToStartEndpoint(): void
    {
        $this->containers->start('abc123');

        $call = $this->transport->lastCall();

        self::assertSame('POST', $call['method']);
        self::assertSame('/containers/abc123/start', $call['path']);
    }

    public function testStopPassesTimeoutAndSignal(): void
    {
        $this->containers->stop('abc123', 10, 'SIGTERM');

        $call = $this->transport->lastCall();

        self::assertSame('/containers/abc123/stop', $call['path']);
        self::assertSame(['t' => 10, 'signal' => 'SIGTERM'], $call['query']);
    }

    public function testRestartPostsToRestartEndpoint(): void
    {
        $this->containers->restart('abc123');

        $call = $this->transport->lastCall();

        self::assertSame('/containers/abc123/restart', $call['path']);
    }

    public function testKillDefaultsToSigkill(): void
    {
        $this->containers->kill('abc123');

        $call = $this->transport->lastCall();

        self::assertSame(['signal' => 'SIGKILL'], $call['query']);
    }

    public function testPauseAndUnpause(): void
    {
        $this->containers->pause('abc123');
        self::assertSame('/containers/abc123/pause', $this->transport->lastCall()['path']);

        $this->containers->unpause('abc123');
        self::assertSame('/containers/abc123/unpause', $this->transport->lastCall()['path']);
    }

    public function testRenamePassesNewNameAsQuery(): void
    {
        $this->containers->rename('abc123', 'new-name');

        $call = $this->transport->lastCall();

        self::assertSame('/containers/abc123/rename', $call['path']);
        self::assertSame(['name' => 'new-name'], $call['query']);
    }

    public function testRemoveSendsDeleteWithFlags(): void
    {
        $this->containers->remove('abc123', force: true, removeVolumes: true);

        $call = $this->transport->lastCall();

        self::assertSame('DELETE', $call['method']);
        self::assertSame(['force' => true, 'v' => true, 'link' => false], $call['query']);
    }

    public function testLogsDefaultsStdoutAndStderrToTrue(): void
    {
        $this->containers->logs('abc123');

        $call = $this->transport->lastCall();

        self::assertSame('/containers/abc123/logs', $call['path']);
        self::assertSame(['stdout' => true, 'stderr' => true, 'follow' => false], $call['query']);
    }

    public function testStatsRequestsSingleSnapshot(): void
    {
        $this->containers->stats('abc123');

        $call = $this->transport->lastCall();

        self::assertSame(['stream' => false], $call['query']);
    }

    public function testTopPassesPsArgs(): void
    {
        $this->containers->top('abc123', '-aux');

        $call = $this->transport->lastCall();

        self::assertSame(['ps_args' => '-aux'], $call['query']);
    }

    public function testWaitDefaultsToNotRunningCondition(): void
    {
        $this->containers->wait('abc123');

        $call = $this->transport->lastCall();

        self::assertSame('POST', $call['method']);
        self::assertSame('/containers/abc123/wait', $call['path']);
        self::assertSame(['condition' => 'not-running'], $call['query']);
    }

    public function testChangesSendsGetRequest(): void
    {
        $this->containers->changes('abc123');

        $call = $this->transport->lastCall();

        self::assertSame('GET', $call['method']);
        self::assertSame('/containers/abc123/changes', $call['path']);
    }

    public function testGetArchivePassesPathAsQuery(): void
    {
        $this->containers->getArchive('abc123', '/etc/hosts');

        $call = $this->transport->lastCall();

        self::assertSame('GET', $call['method']);
        self::assertSame('/containers/abc123/archive', $call['path']);
        self::assertSame(['path' => '/etc/hosts'], $call['query']);
    }

    public function testPutArchiveSendsRawTarBodyWithContentTypeHeader(): void
    {
        $this->containers->putArchive('abc123', '/app', 'FAKE-TAR-BYTES', noOverwriteDirNonDir: true);

        $call = $this->transport->lastCall();

        self::assertSame('requestRaw', $call['kind']);
        self::assertSame('PUT', $call['method']);
        self::assertSame('/containers/abc123/archive', $call['path']);
        self::assertSame('FAKE-TAR-BYTES', $call['body']);
        self::assertSame(['path' => '/app', 'noOverwriteDirNonDir' => true, 'copyUIDGID' => false], $call['query']);
        self::assertSame(['Content-Type: application/x-tar'], $call['headers']);
    }

    public function testExportSendsGetRequest(): void
    {
        $this->containers->export('abc123');

        $call = $this->transport->lastCall();

        self::assertSame('GET', $call['method']);
        self::assertSame('/containers/abc123/export', $call['path']);
    }

    public function testLogsStreamForcesFollowTrueAndStreamsChunks(): void
    {
        $this->transport->setStreamChunks(['chunk-1', 'chunk-2']);

        $received = [];
        $status = $this->containers->logsStream('abc123', function (string $chunk) use (&$received): void {
            $received[] = $chunk;
        });

        $call = $this->transport->lastCall();

        self::assertSame('stream', $call['kind']);
        self::assertSame('/containers/abc123/logs', $call['path']);
        self::assertSame(['stdout' => true, 'stderr' => true, 'follow' => true], $call['query']);
        self::assertSame(200, $status);
        self::assertSame(['chunk-1', 'chunk-2'], $received);
    }

    public function testStatsStreamRequestsContinuousStream(): void
    {
        $this->transport->setStreamChunks(['{"cpu":1}']);

        $this->containers->statsStream('abc123', function (): void {
        });

        $call = $this->transport->lastCall();

        self::assertSame('stream', $call['kind']);
        self::assertSame(['stream' => true], $call['query']);
    }

    public function testAttachStreamDefaultsToStdoutStderrStream(): void
    {
        $this->containers->attachStream('abc123', function (): void {
        });

        $call = $this->transport->lastCall();

        self::assertSame('stream', $call['kind']);
        self::assertSame('POST', $call['method']);
        self::assertSame('/containers/abc123/attach', $call['path']);
        self::assertSame(['stdout' => true, 'stderr' => true, 'stream' => true, 'logs' => false], $call['query']);
    }

    public function testAttachStreamStopsWhenCallbackReturnsFalse(): void
    {
        $this->transport->setStreamChunks(['a', 'b', 'c']);

        $received = [];
        $this->containers->attachStream('abc123', function (string $chunk) use (&$received) {
            $received[] = $chunk;

            return $chunk !== 'b';
        });

        self::assertSame(['a', 'b'], $received);
    }

    public function testPruneOmitsEmptyFilters(): void
    {
        $this->containers->prune();

        self::assertSame(['filters' => null], $this->transport->lastCall()['query']);
    }

    public function testPruneForwardsFilters(): void
    {
        $this->containers->prune(['until' => ['24h']]);

        self::assertSame(['filters' => ['until' => ['24h']]], $this->transport->lastCall()['query']);
    }

    public function testUpdateSendsResourcesAsBody(): void
    {
        $this->containers->update('abc123', ['Memory' => 536870912, 'CpuShares' => 512]);

        $call = $this->transport->lastCall();

        self::assertSame('POST', $call['method']);
        self::assertSame('/containers/abc123/update', $call['path']);
        self::assertSame(['Memory' => 536870912, 'CpuShares' => 512], $call['body']);
    }
}
