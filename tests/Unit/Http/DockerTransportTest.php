<?php

declare(strict_types=1);

namespace Sytxlabs\Dockphp\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Sytxlabs\Dockphp\Tests\Support\TestableDockerTransport;

final class DockerTransportTest extends TestCase
{
    private TestableDockerTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new TestableDockerTransport('/var/run/docker.sock', '1.43');
    }

    public function testBuildUrlWithoutQuery(): void
    {
        self::assertSame(
            'http://localhost/containers/json',
            $this->transport->publicBuildUrl('/containers/json', []),
        );
    }

    public function testBuildUrlWithScalarQuery(): void
    {
        $url = $this->transport->publicBuildUrl('/containers/abc/json', ['size' => true]);

        self::assertSame('http://localhost/containers/abc/json?size=true', $url);
    }

    public function testBuildQueryStringDropsNullValues(): void
    {
        $query = $this->transport->publicBuildQueryString(['t' => null, 'signal' => 'SIGTERM']);

        self::assertSame('signal=SIGTERM', $query);
    }

    public function testBuildQueryStringEncodesBooleans(): void
    {
        $query = $this->transport->publicBuildQueryString(['force' => true, 'noprune' => false]);

        self::assertSame('force=true&noprune=false', $query);
    }

    public function testBuildQueryStringJsonEncodesArrayValues(): void
    {
        $query = $this->transport->publicBuildQueryString([
            'filters' => ['status' => ['running']],
        ]);

        self::assertSame('filters=' . rawurlencode('{"status":["running"]}'), $query);
    }

    public function testBuildQueryStringUrlEncodesValues(): void
    {
        $query = $this->transport->publicBuildQueryString(['name' => 'my container']);

        self::assertSame('name=my%20container', $query);
    }

    public function testBuildUrlCombinesPathAndMultipleQueryParams(): void
    {
        $url = $this->transport->publicBuildUrl('/containers/json', ['all' => true, 'limit' => 5]);

        self::assertSame('http://localhost/containers/json?all=true&limit=5', $url);
    }

    public function testGetApiVersionReturnsManuallyConfiguredOverride(): void
    {
        self::assertSame('1.43', $this->transport->getApiVersion());
    }

    public function testEncodeJsonEncodesEmptyArrayAsObjectNotList(): void
    {
        // Regression: Docker's Go structs (e.g. ExecStartOptions) reject an
        // empty JSON array `[]` where an object `{}` is expected - PHP's
        // empty array is otherwise indistinguishable from an empty list.
        self::assertSame('{}', $this->transport->publicEncodeJson([]));
    }

    public function testEncodeJsonPreservesListsAndAssociativeArrays(): void
    {
        self::assertSame('["ls","-la"]', $this->transport->publicEncodeJson(['ls', '-la']));
        self::assertSame('{"Detach":false,"Tty":true}', $this->transport->publicEncodeJson(['Detach' => false, 'Tty' => true]));
    }
}
