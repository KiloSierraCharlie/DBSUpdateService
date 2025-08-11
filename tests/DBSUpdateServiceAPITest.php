<?php

/*
 *
 * (c) Kieran Cross
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KiloSierraCharlie\DBSUpdateService\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use KiloSierraCharlie\DBSUpdateService\DBSUpdateServiceAPI;
use KiloSierraCharlie\DBSUpdateService\Exceptions\AccessDeniedException;
use KiloSierraCharlie\DBSUpdateService\Exceptions\CertificateNotFoundException;
use KiloSierraCharlie\DBSUpdateService\Exceptions\ConnectionFailureException;
use KiloSierraCharlie\DBSUpdateService\Exceptions\InvalidResponseException;
use KiloSierraCharlie\DBSUpdateService\Exceptions\MalformedDataException;
use PHPUnit\Framework\TestCase;

final class DBSUpdateServiceAPITest extends TestCase
{
    private const XML_OK = '<statusCheckResult><statusCheckResultType>SUCCESS</statusCheckResultType><status>BLANK_NO_NEW_INFO</status><forename>JOHN</forename><surname>SMITH</surname><printDate class="sql-date">2020-01-01</printDate></statusCheckResult>';

    private function makeClient(array $queue, array &$history): Client
    {
        $mock = new MockHandler($queue);
        $stack = HandlerStack::create($mock);
        $history = [];
        $stack->push(Middleware::history($history));

        return new Client([
            'handler' => $stack,
            'base_uri' => 'https://secure.crbonline.gov.uk',
            'http_errors' => true,
            'timeout' => 5,
        ]);
    }

    private function makeApi(Client $client): DBSUpdateServiceAPI
    {
        $api = new DBSUpdateServiceAPI('Test', 'Alice', 'Smith');

        $ref = new \ReflectionClass($api);
        $prop = $ref->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($api, $client);

        return $api;
    }

    public function testSuccessParsesXmlAndSendsCorrectQuery(): void
    {
        $history = [];
        $client = $this->makeClient([new Response(200, [], self::XML_OK)], $history);
        $api = $this->makeApi($client);

        $res = $api->getCertificateStatus('012345678901', 'SMITH', '2000-01-01');

        $this->assertSame('JOHN', $res->forename);
        $this->assertSame('SMITH', $res->surname);
        $this->assertSame('2020-01-01', $res->printDate->format('Y-m-d'));

        $this->assertCount(1, $history);
        $req = $history[0]['request'];

        $this->assertSame('/crsc/api/status/012345678901', $req->getUri()->getPath());

        parse_str($req->getUri()->getQuery(), $q);
        $this->assertSame('true', $q['hasAgreedTermsAndConditions'] ?? null);
        $this->assertSame('Test', $q['organisationName'] ?? null);
        $this->assertSame('Alice', $q['employeeForename'] ?? null);
        $this->assertSame('Smith', $q['employeeSurname'] ?? null);
        $this->assertSame('SMITH', $q['surname'] ?? null);
        $this->assertSame('01/01/2000', $q['dateOfBirth'] ?? null);
    }

    public function test401BecomesAccessDenied(): void
    {
        $history = [];
        $client = $this->makeClient([new Response(401, [], 'nope')], $history);
        $api = $this->makeApi($client);

        $this->expectException(AccessDeniedException::class);
        $api->getCertificateStatus('012345678901', 'SMITH', '2000-01-01');
    }

    public function test403BecomesAccessDenied(): void
    {
        $history = [];
        $client = $this->makeClient([new Response(403, [], 'nope')], $history);
        $api = $this->makeApi($client);

        $this->expectException(AccessDeniedException::class);
        $api->getCertificateStatus('012345678901', 'SMITH', '2000-01-01');
    }

    public function test404BecomesCertificateNotFound(): void
    {
        $history = [];
        $client = $this->makeClient([new Response(404, [], 'not found')], $history);
        $api = $this->makeApi($client);

        $this->expectException(CertificateNotFoundException::class);
        $api->getCertificateStatus('does-not-exist', 'SMITH', '2000-01-01');
    }

    public function testNetworkErrorBecomesConnectionFailure(): void
    {
        $history = [];
        $client = $this->makeClient([
            function () { throw new TransferException('boom'); },
        ], $history);
        $api = $this->makeApi($client);

        $this->expectException(ConnectionFailureException::class);
        $api->getCertificateStatus('012345678901', 'SMITH', '2000-01-01');
    }

    public function testUnexpecedXmlAsInvalidResponse(): void
    {
        $history = [];
        $client = $this->makeClient([new Response(200, [], '<nope></nope>')], $history);
        $api = $this->makeApi($client);

        $this->expectException(InvalidResponseException::class);
        $api->getCertificateStatus('012345678901', 'SMITH', '2000-01-01');
    }

    public function testWrongDOBCertificateDataAsMalformedData(): void
    {
        $history = [];
        $client = $this->makeClient([new Response(200, [], '<nope></nope>')], $history);
        $api = $this->makeApi($client);

        $this->expectException(MalformedDataException::class);
        $api->getCertificateStatus('012345678901', 'SMITH', '1');
    }

    public function testWrongFormattedDOBCertificateDataAsMalformedData(): void
    {
        $history = [];
        $client = $this->makeClient([new Response(200, [], '<nope></nope>')], $history);
        $api = $this->makeApi($client);

        $this->expectException(MalformedDataException::class);
        $api->getCertificateStatus('012345678901', 'SMITH', '201001-01-01');
    }
}
