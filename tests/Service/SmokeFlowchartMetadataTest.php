<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Tests\Service;

use ControleOnline\SmokeTestsPlayground\Service\SmokeFlowchartMetadata;
use PHPUnit\Framework\TestCase;

final class SmokeFlowchartMetadataTest extends TestCase
{
    public function testNormalizeReturnsEmptyIdsWhenReportHasNone(): void
    {
        $metadata = (new SmokeFlowchartMetadata())->normalize([
            'suite' => 'legacy-suite',
            'tests' => [],
        ]);

        self::assertSame([], $metadata['flowchartIds']);
        self::assertSame([], $metadata['flowchartLinks']);
        self::assertNull($metadata['flowKey']);
    }

    public function testNormalizeBuildsAdminLinksForDeclaredIds(): void
    {
        $metadata = (new SmokeFlowchartMetadata())->normalize([
            'flowchartIds' => [1],
            'flowKey' => 'sales-production',
        ]);

        self::assertSame([1], $metadata['flowchartIds']);
        self::assertSame(
            ['https://admin.controleonline.com/admin/flowcharts/1'],
            $metadata['flowchartLinks'],
        );
        self::assertSame('sales-production', $metadata['flowKey']);
    }

    public function testNormalizeDoesNotInventSalesProductionId(): void
    {
        $metadata = (new SmokeFlowchartMetadata())->normalize([
            'suite' => 'venda-producao',
            'flowKey' => 'sales-production',
            'displayName' => 'Venda / produção',
        ]);

        self::assertSame([], $metadata['flowchartIds']);
        self::assertSame([], $metadata['flowchartLinks']);
        self::assertSame('sales-production', $metadata['flowKey']);
    }

    public function testNormalizeReadsIdsFromAdminLinksWithoutInventingOthers(): void
    {
        $metadata = (new SmokeFlowchartMetadata())->normalize([
            'flowchartLinks' => [
                'https://admin.controleonline.com/admin/flowcharts/1',
                'https://example.test/not-a-flowchart',
            ],
        ]);

        self::assertSame([1], $metadata['flowchartIds']);
        self::assertSame(
            ['https://admin.controleonline.com/admin/flowcharts/1'],
            $metadata['flowchartLinks'],
        );
    }

    public function testEnrichSuitePropagatesMetadataToTests(): void
    {
        $suite = (new SmokeFlowchartMetadata())->enrichSuite([
            'suite' => 'balcao',
            'flowchartIds' => [1],
            'flowKey' => 'sales-production',
            'tests' => [
                ['title' => 'pedido prepaid', 'status' => 'passed'],
            ],
        ]);

        self::assertSame([1], $suite['flowchartIds']);
        self::assertSame(
            ['https://admin.controleonline.com/admin/flowcharts/1'],
            $suite['flowchartLinks'],
        );
        self::assertSame([1], $suite['tests'][0]['flowchartIds']);
        self::assertSame(
            ['https://admin.controleonline.com/admin/flowcharts/1'],
            $suite['tests'][0]['flowchartLinks'],
        );
        self::assertSame('sales-production', $suite['tests'][0]['flowKey']);
    }

    public function testGroupSuitesIndexesByFlowchartId(): void
    {
        $helper = new SmokeFlowchartMetadata();
        $suites = [
            $helper->enrichSuite([
                'suite' => 'balcao',
                'flowchartIds' => [1],
                'flowKey' => 'sales-production',
                'tests' => [],
            ]),
            $helper->enrichSuite([
                'suite' => 'legacy',
                'tests' => [],
            ]),
        ];

        $grouped = $helper->groupSuites($suites);

        self::assertCount(1, $grouped);
        self::assertSame(1, $grouped[0]['id']);
        self::assertSame('https://admin.controleonline.com/admin/flowcharts/1', $grouped[0]['link']);
        self::assertSame(['sales-production'], $grouped[0]['flowKeys']);
        self::assertSame('balcao', $grouped[0]['suites'][0]['suite']);
    }
}
