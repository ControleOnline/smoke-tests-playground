<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeTestsIndexFactory
{
    public function __construct(
        private readonly SmokeReportReader $reportReader,
    ) {
    }

    public function create(): array
    {
        $suites = [];

        foreach ($this->reportReader->discoverReportFiles() as $reportPath) {
            $report = $this->reportReader->readReportFile($reportPath);
            if ($report === null) {
                continue;
            }

            $suites[] = $report;
        }

        usort($suites, function (array $left, array $right): int {
            $leftTimestamp = $this->reportTimestamp($left);
            $rightTimestamp = $this->reportTimestamp($right);

            return $rightTimestamp <=> $leftTimestamp
                ?: strcmp((string) ($left['suite'] ?? ''), (string) ($right['suite'] ?? ''));
        });

        if ($suites === []) {
            return $this->emptyIndex();
        }

        $suiteSummary = $this->buildSuiteSummary($suites);
        $testSummary = $this->buildTestSummary($suites);

        return [
            'generatedAt' => date(DATE_ATOM),
            'status' => $suiteSummary['failed'] === 0 ? 'passed' : 'failed',
            'progress' => $testSummary['total'] > 0 ? (int) round($testSummary['passed'] * 100 / $testSummary['total']) : 0,
            'message' => $this->buildMessage($suiteSummary, $testSummary),
            'lastRunAt' => $this->lastRunAt($suites),
            'summary' => [
                'suites' => $suiteSummary,
                'tests' => $testSummary,
            ],
            'suites' => $suites,
            'links' => [
                'self' => '/tests/index.json',
                'artifacts' => '/tests/artifacts',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $suite
     */
    private function reportTimestamp(array $suite): int
    {
        foreach ([$suite['generatedAt'] ?? null, $suite['updatedAt'] ?? null] as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            $timestamp = strtotime($candidate);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        return 0;
    }

    /**
     * @param list<array<string, mixed>> $suites
     *
     * @return array{total:int,passed:int,failed:int}
     */
    private function buildSuiteSummary(array $suites): array
    {
        $passed = 0;
        $failed = 0;

        foreach ($suites as $suite) {
            if (($suite['status'] ?? null) === 'passed') {
                $passed++;

                continue;
            }

            $failed++;
        }

        return [
            'total' => count($suites),
            'passed' => $passed,
            'failed' => $failed,
        ];
    }

    /**
     * @param list<array<string, mixed>> $suites
     *
     * @return array{total:int,passed:int,failed:int}
     */
    private function buildTestSummary(array $suites): array
    {
        $passed = 0;
        $failed = 0;
        $total = 0;

        foreach ($suites as $suite) {
            foreach (($suite['tests'] ?? []) as $test) {
                $total++;

                if (($test['status'] ?? null) === 'passed') {
                    $passed++;

                    continue;
                }

                $failed++;
            }
        }

        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
        ];
    }

    /**
     * @param array{total:int,passed:int,failed:int} $suiteSummary
     * @param array{total:int,passed:int,failed:int} $testSummary
     */
    private function buildMessage(array $suiteSummary, array $testSummary): string
    {
        if ($suiteSummary['total'] === 0) {
            return 'Nenhum relatório publicado ainda.';
        }

        if ($suiteSummary['failed'] === 0) {
            return sprintf(
                '%d suite%s publicada%s com sucesso e %d teste%s passaram.',
                $suiteSummary['total'],
                $suiteSummary['total'] === 1 ? '' : 's',
                $suiteSummary['total'] === 1 ? '' : 's',
                $testSummary['passed'],
                $testSummary['passed'] === 1 ? '' : 's',
            );
        }

        return sprintf(
            '%d suite%s com falha em %d publicad%s.',
            $suiteSummary['failed'],
            $suiteSummary['failed'] === 1 ? '' : 's',
            $suiteSummary['total'],
            $suiteSummary['total'] === 1 ? '' : 'as',
        );
    }

    /**
     * @param list<array<string, mixed>> $suites
     */
    private function lastRunAt(array $suites): ?string
    {
        foreach ($suites as $suite) {
            foreach (['generatedAt', 'updatedAt'] as $key) {
                $value = $suite[$key] ?? null;
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function emptyIndex(): array
    {
        return [
            'generatedAt' => date(DATE_ATOM),
            'status' => 'idle',
            'progress' => 0,
            'message' => 'Nenhum relatório publicado ainda.',
            'lastRunAt' => null,
            'summary' => [
                'suites' => [
                    'total' => 0,
                    'passed' => 0,
                    'failed' => 0,
                ],
                'tests' => [
                    'total' => 0,
                    'passed' => 0,
                    'failed' => 0,
                ],
            ],
            'suites' => [],
            'links' => [
                'self' => '/tests/index.json',
                'artifacts' => '/tests/artifacts',
            ],
        ];
    }
}
