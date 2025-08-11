<?php

/*
 *
 * (c) Kieran Cross
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KiloSierraCharlie\DBSUpdateService\Tests;

use KiloSierraCharlie\DBSUpdateService\Models\StatusCheckResult;
use KiloSierraCharlie\DBSUpdateService\Models\StatusCheckResultType;
use KiloSierraCharlie\DBSUpdateService\Models\StatusCode;
use PHPUnit\Framework\TestCase;

final class StatusCheckResultTest extends TestCase
{
    public function testParsesValidXml(): void
    {
        $xml = '<statusCheckResult><statusCheckResultType>SUCCESS</statusCheckResultType><status>BLANK_NO_NEW_INFO</status><forename>JOHN</forename><surname>SMITH</surname><printDate class="sql-date">2020-01-01</printDate></statusCheckResult>';
        $m = StatusCheckResult::fromXml($xml);

        $this->assertSame('JOHN', $m->forename);
        $this->assertSame('SMITH', $m->surname);
        $this->assertSame('2020-01-01', $m->printDate->format('Y-m-d'));
        $this->assertSame(StatusCheckResultType::SUCCESS, $m->resultType);
        $this->assertSame($m->isCurrent(), true);
        $this->assertSame($m->isClear(), true);

        $xml = '<statusCheckResult><statusCheckResultType>FAILURE</statusCheckResultType><status>NEW_INFO</status><forename>JOHN</forename><surname>SMITH</surname><printDate class="sql-date">2020-01-01</printDate></statusCheckResult>';
        $m = StatusCheckResult::fromXml($xml);

        $this->assertSame(StatusCheckResultType::FAILURE, $m->resultType);
        $this->assertSame(StatusCode::NEW_INFO, $m->status);
        $this->assertSame($m->isClear(), false);
    }
}
