<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeReportReader
{
    public function __construct(
        private readonly SmokeTestsSettings $settings,
    ) {
    }

    public function readLatest(): ?array
    {
        $reportPath = $this->settings->reportPath();

        if (!is_file($reportPath)) {
            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [
                'generatedAt' => null,
                'suite' => 'smoke-tests-playground',
                'tests' => [],
                'status' => 'failed',
                'error' => 'The report.json file is invalid JSON.',
            ];
        }

        if (!is_array($decoded)) {
            return null;
        }

        $report = $decoded;
        $report['tests'] = $this->normalizeTests($report['tests'] ?? []);

        if (!isset($report['status'])) {
            $report['status'] = empty($report['tests'])
                ? 'failed'
                : (count(array_filter($report['tests'], static fn (array $test): bool => ($test['status'] ?? '') !== 'passed')) === 0 ? 'passed' : 'failed');
        }

        $report['summary'] = [
            'total' => count($report['tests']),
            'passed' => count(array_filter($report['tests'], static fn (array $test): bool => ($test['status'] ?? '') === 'passed')),
            'failed' => count(array_filter($report['tests'], static fn (array $test): bool => ($test['status'] ?? '') !== 'passed')),
        ];

        return $report;
    }

    /**
     * @param array<int, array<string, mixed>> $tests
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTests(array $tests): array
    {
        return array_map(function (array $test): array {
            $screenshots = $this->normalizeScreenshots($test['screenshots'] ?? []);

            return [
                'name' => (string) ($test['title'] ?? $test['name'] ?? 'Teste sem nome'),
                'status' => (string) ($test['status'] ?? 'failed'),
                'error' => isset($test['error']) ? (string) $test['error'] : null,
                'screenshots' => $screenshots,
                'steps' => $this->normalizeSteps($test['steps'] ?? []),
            ];
        }, $tests);
    }

    /**
     * @param array<int, array<string, mixed>> $screenshots
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeScreenshots(array $screenshots): array
    {
        return array_map(function (array $screenshot): array {
            $path = $this->settings->testsPath().DIRECTORY_SEPARATOR.($screenshot['path'] ?? '');

            return [
                'label' => (string) ($screenshot['label'] ?? 'Print'),
                'src' => is_file($path)
                    ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path))
                    : null,
            ];
        }, $screenshots);
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSteps(array $steps): array
    {
        return array_map(function (array $step): array {
            return [
                'name' => (string) ($step['title'] ?? $step['name'] ?? 'Etapa sem nome'),
                'status' => (string) ($step['status'] ?? 'failed'),
                'error' => isset($step['error']) ? (string) $step['error'] : null,
                'screenshots' => $this->normalizeScreenshots($step['screenshots'] ?? []),
            ];
        }, $steps);
    }
}
