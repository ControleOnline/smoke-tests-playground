<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeTestsIndexFactory
{
    public function __construct(
        private readonly SmokeReportReader $reportReader,
        private readonly SmokeSuitePathCodec $suitePathCodec,
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
                ?: strcmp((string) ($left['suiteId'] ?? $left['suite'] ?? ''), (string) ($right['suiteId'] ?? $right['suite'] ?? ''));
        });

        if ($suites === []) {
            return $this->emptyIndex();
        }

        $types = $this->buildTypeSections($suites);
        $suiteSummary = $this->buildSuiteSummary($suites);
        $testSummary = $this->buildTestSummary($suites);
        $typeSummary = $this->buildTypeSummary($types);

        return [
            'generatedAt' => date(DATE_ATOM),
            'status' => $suiteSummary['failed'] === 0 ? 'passed' : 'failed',
            'progress' => $testSummary['total'] > 0 ? (int) round($testSummary['passed'] * 100 / $testSummary['total']) : 0,
            'message' => $this->buildMessage($suiteSummary, $testSummary),
            'lastRunAt' => $this->lastRunAt($suites),
            'summary' => [
                'types' => $typeSummary,
                'suites' => $suiteSummary,
                'tests' => $testSummary,
            ],
            'types' => $types,
            'suites' => $suites,
            'links' => [
                'self' => '/tests',
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
     * @return list<array<string, mixed>>
     */
    private function buildTypeSections(array $suites): array
    {
        $grouped = [];

        foreach ($suites as $suite) {
            $type = (string) ($suite['type'] ?? 'general');
            $grouped[$type][] = $suite;
        }

        $types = [];

        foreach ($grouped as $typeKey => $typeSuites) {
            usort($typeSuites, function (array $left, array $right): int {
                $leftTimestamp = $this->reportTimestamp($left);
                $rightTimestamp = $this->reportTimestamp($right);

                return $rightTimestamp <=> $leftTimestamp
                    ?: strcmp((string) ($left['suiteId'] ?? $left['suite'] ?? ''), (string) ($right['suiteId'] ?? $right['suite'] ?? ''));
            });

            $suiteSummary = $this->buildSuiteSummary($typeSuites);
            $testSummary = $this->buildTestSummary($typeSuites);

            $types[] = [
                'type' => $typeKey,
                'displayName' => (string) ($typeSuites[0]['typeDisplayName'] ?? $this->suitePathCodec->humanizeLabel($typeKey)),
                'status' => $suiteSummary['failed'] === 0 ? 'passed' : 'failed',
                'progress' => $testSummary['total'] > 0 ? (int) round($testSummary['passed'] * 100 / $testSummary['total']) : 0,
                'message' => $this->buildTypeMessage($suiteSummary, $testSummary),
                'summary' => [
                    'suites' => $suiteSummary,
                    'tests' => $testSummary,
                ],
                'suites' => $typeSuites,
            ];
        }

        usort($types, function (array $left, array $right): int {
            $leftTimestamp = $this->typeTimestamp($left);
            $rightTimestamp = $this->typeTimestamp($right);

            return $rightTimestamp <=> $leftTimestamp
                ?: strcmp((string) ($left['displayName'] ?? $left['type'] ?? ''), (string) ($right['displayName'] ?? $right['type'] ?? ''));
        });

        return $types;
    }

    /**
     * @param array<string, mixed> $type
     */
    private function typeTimestamp(array $type): int
    {
        foreach (($type['suites'] ?? []) as $suite) {
            $timestamp = $this->reportTimestamp($suite);
            if ($timestamp > 0) {
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
    private function buildTypeSummary(array $suites): array
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
     * @param array{total:int,passed:int,failed:int} $suiteSummary
     * @param array{total:int,passed:int,failed:int} $testSummary
     */
    private function buildTypeMessage(array $suiteSummary, array $testSummary): string
    {
        if ($suiteSummary['total'] === 0) {
            return 'Nenhuma suite publicada neste tipo.';
        }

        if ($suiteSummary['failed'] === 0) {
            return sprintf(
                '%d suite%s publicada%s e %d teste%s passaram.',
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
                'types' => [
                    'total' => 0,
                    'passed' => 0,
                    'failed' => 0,
                ],
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
            'types' => [],
            'suites' => [],
            'links' => [
                'self' => '/tests',
                'artifacts' => '/tests/artifacts',
            ],
        ];
    }
}
