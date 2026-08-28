<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\DTO\ImageInfo;
use Sytxlabs\Dockphp\DTO\ImageSummary;
use Sytxlabs\Dockphp\Http\DockerResponse;
use Sytxlabs\Dockphp\Resources\Images;
use Sytxlabs\Dockphp\Tests\Support\FakeDockerTransport;

final class ImagesTest extends TestCase
{
    private FakeDockerTransport $transport;
    private Images $images;

    protected function setUp(): void
    {
        $this->transport = new FakeDockerTransport();
        $this->images = new Images($this->transport);
    }

    public function testListSendsGetRequestAndHydratesSummaries(): void
    {
        $this->transport->setResponse(new DockerResponse(200, '[{"Id":"sha256:abc","RepoTags":["nginx:latest"]}]'));

        $result = $this->images->list(['all' => true]);

        $call = $this->transport->lastCall();

        self::assertSame('GET', $call['method']);
        self::assertSame('/images/json', $call['path']);
        self::assertSame(['all' => true], $call['query']);

        self::assertCount(1, $result);
        self::assertInstanceOf(ImageSummary::class, $result[0]);
        self::assertSame('nginx:latest', $result[0]->getName());
    }

    public function testInspectBuildsNameInPathAndHydratesInfo(): void
    {
        $this->transport->setResponse(new DockerResponse(200, '{"Id":"sha256:abc","RepoTags":["nginx:latest"]}'));

        $result = $this->images->inspect('nginx:latest');

        self::assertInstanceOf(ImageInfo::class, $result);
        self::assertSame('nginx:latest', $result->getName());
        self::assertSame('/images/nginx:latest/json', $this->transport->lastCall()['path']);
    }

    public function testPullBuildsFromImageAndTagQuery(): void
    {
        $this->images->pull('nginx', 'latest');

        $call = $this->transport->lastCall();

        self::assertSame('POST', $call['method']);
        self::assertSame('/images/create', $call['path']);
        self::assertSame(['fromImage' => 'nginx', 'tag' => 'latest', 'platform' => null], $call['query']);
        self::assertNull($call['body']);
        self::assertSame([], $call['headers']);
    }

    public function testPullSendsRegistryAuthHeaderWhenGiven(): void
    {
        $this->images->pull('private/app', 'latest', null, ['username' => 'me', 'password' => 'secret']);

        $call = $this->transport->lastCall();

        self::assertSame(
            ['X-Registry-Auth: ' . base64_encode('{"username":"me","password":"secret"}')],
            $call['headers'],
        );
    }

    public function testRemoveSendsDeleteWithFlags(): void
    {
        $this->images->remove('nginx:latest', force: true);

        $call = $this->transport->lastCall();

        self::assertSame('DELETE', $call['method']);
        self::assertSame('/images/nginx:latest', $call['path']);
        self::assertSame(['force' => true, 'noprune' => false], $call['query']);
    }

    public function testTagPassesRepoAndTag(): void
    {
        $this->images->tag('nginx:latest', 'myrepo/nginx', 'v1');

        $call = $this->transport->lastCall();

        self::assertSame('/images/nginx:latest/tag', $call['path']);
        self::assertSame(['repo' => 'myrepo/nginx', 'tag' => 'v1'], $call['query']);
    }

    public function testHistoryBuildsNameInPath(): void
    {
        $this->images->history('nginx:latest');

        self::assertSame('/images/nginx:latest/history', $this->transport->lastCall()['path']);
    }

    public function testPruneOmitsEmptyFilters(): void
    {
        $this->images->prune();

        self::assertSame(['filters' => null], $this->transport->lastCall()['query']);
    }

    public function testPruneForwardsFilters(): void
    {
        $this->images->prune(['dangling' => ['true']]);

        self::assertSame(['filters' => ['dangling' => ['true']]], $this->transport->lastCall()['query']);
    }

    public function testPullStreamDecodesNdjsonProgressEvents(): void
    {
        $this->transport->setStreamChunks(["{\"status\":\"Pulling\"}\n{\"status\":\"Downloading\"}\n"]);

        $events = [];
        $status = $this->images->pullStream('nginx', 'latest', null, function (array $event) use (&$events): void {
            $events[] = $event;
        });

        $call = $this->transport->lastCall();

        self::assertSame('stream', $call['kind']);
        self::assertSame('/images/create', $call['path']);
        self::assertSame(['fromImage' => 'nginx', 'tag' => 'latest', 'platform' => null], $call['query']);
        self::assertSame(200, $status);
        self::assertSame([['status' => 'Pulling'], ['status' => 'Downloading']], $events);
    }

    public function testCommitBuildsQueryAndOptionalBody(): void
    {
        $this->images->commit('container123', 'myrepo/app', 'v1', 'a comment', 'me', true, ['CMD ["/app"]'], ['Labels' => ['x' => 'y']]);

        $call = $this->transport->lastCall();

        self::assertSame('POST', $call['method']);
        self::assertSame('/commit', $call['path']);
        self::assertSame([
            'container' => 'container123',
            'repo' => 'myrepo/app',
            'tag' => 'v1',
            'comment' => 'a comment',
            'author' => 'me',
            'pause' => true,
            'changes' => 'CMD ["/app"]',
        ], $call['query']);
        self::assertSame(['Labels' => ['x' => 'y']], $call['body']);
    }

    public function testBuildSendsRawTarBodyWithContentTypeHeader(): void
    {
        $this->images->build('FAKE-TAR-BYTES', ['t' => 'myimage:latest']);

        $call = $this->transport->lastCall();

        self::assertSame('requestRaw', $call['kind']);
        self::assertSame('POST', $call['method']);
        self::assertSame('/build', $call['path']);
        self::assertSame('FAKE-TAR-BYTES', $call['body']);
        self::assertSame(['t' => 'myimage:latest'], $call['query']);
        self::assertSame(['Content-Type: application/x-tar'], $call['headers']);
    }

    public function testBuildStreamDecodesNdjsonProgressEvents(): void
    {
        $this->transport->setStreamChunks(["{\"stream\":\"Step 1/1\"}\n"]);

        $events = [];
        $status = $this->images->buildStream('FAKE-TAR-BYTES', function (array $event) use (&$events): void {
            $events[] = $event;
        });

        $call = $this->transport->lastCall();

        self::assertSame('streamRaw', $call['kind']);
        self::assertSame('/build', $call['path']);
        self::assertSame('FAKE-TAR-BYTES', $call['body']);
        self::assertSame(['Content-Type: application/x-tar'], $call['headers']);
        self::assertSame(200, $status);
        self::assertSame([['stream' => 'Step 1/1']], $events);
    }

    public function testPushBuildsTagQueryAndRegistryAuthHeader(): void
    {
        $this->images->push('private/app', 'latest', ['identitytoken' => 'tok']);

        $call = $this->transport->lastCall();

        self::assertSame('POST', $call['method']);
        self::assertSame('/images/private/app/push', $call['path']);
        self::assertSame(['tag' => 'latest'], $call['query']);
        self::assertSame(['X-Registry-Auth: ' . base64_encode('{"identitytoken":"tok"}')], $call['headers']);
    }

    public function testPushWithoutRegistryAuthSendsNoExtraHeader(): void
    {
        $this->images->push('private/app');

        self::assertSame([], $this->transport->lastCall()['headers']);
    }

    public function testPushStreamDecodesNdjsonProgressEvents(): void
    {
        $this->transport->setStreamChunks(["{\"status\":\"Pushing\"}\n"]);

        $events = [];
        $status = $this->images->pushStream('private/app', function (array $event) use (&$events): void {
            $events[] = $event;
        }, 'latest');

        $call = $this->transport->lastCall();

        self::assertSame('stream', $call['kind']);
        self::assertSame('/images/private/app/push', $call['path']);
        self::assertSame(200, $status);
        self::assertSame([['status' => 'Pushing']], $events);
    }

    public function testSearchBuildsQuery(): void
    {
        $this->images->search('nginx', 5, ['is-official' => ['true']]);

        $call = $this->transport->lastCall();

        self::assertSame('GET', $call['method']);
        self::assertSame('/images/search', $call['path']);
        self::assertSame(['term' => 'nginx', 'limit' => 5, 'filters' => ['is-official' => ['true']]], $call['query']);
    }

    public function testLoadSendsRawTarBodyWithContentTypeHeader(): void
    {
        $this->images->load('FAKE-TAR-BYTES', true);

        $call = $this->transport->lastCall();

        self::assertSame('requestRaw', $call['kind']);
        self::assertSame('POST', $call['method']);
        self::assertSame('/images/load', $call['path']);
        self::assertSame('FAKE-TAR-BYTES', $call['body']);
        self::assertSame(['quiet' => true], $call['query']);
        self::assertSame(['Content-Type: application/x-tar'], $call['headers']);
    }

    public function testSaveBuildsNameInPath(): void
    {
        $this->images->save('nginx:latest');

        $call = $this->transport->lastCall();

        self::assertSame('GET', $call['method']);
        self::assertSame('/images/nginx:latest/get', $call['path']);
    }

    public function testSaveMultipleBuildsRepeatedNamesQueryString(): void
    {
        $this->images->saveMultiple(['nginx:latest', 'redis:7']);

        $call = $this->transport->lastCall();

        self::assertSame('GET', $call['method']);
        self::assertSame('/images/get?names=nginx%3Alatest&names=redis%3A7', $call['path']);
        self::assertSame([], $call['query']);
    }

    public function testInspectDistributionBuildsNameInPath(): void
    {
        $this->images->inspectDistribution('nginx:latest');

        $call = $this->transport->lastCall();

        self::assertSame('GET', $call['method']);
        self::assertSame('/distribution/nginx:latest/json', $call['path']);
    }
}
