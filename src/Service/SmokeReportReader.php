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
        $report['tests'] = array_map(function (array $test): array {
            $test['screenshots'] = array_map(function (array $screenshot): array {
                $path = $this->settings->testsPath().DIRECTORY_SEPARATOR.($screenshot['path'] ?? '');
                $screenshot['src'] = is_file($path)
                    ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path))
                    : null;

                return $screenshot;
            }, $test['screenshots'] ?? []);

            return $test;
        }, $report['tests'] ?? []);

        if (!isset($report['status'])) {
            $report['status'] = empty($report['tests'])
                ? 'failed'
                : (count(array_filter($report['tests'], static fn (array $test): bool => ($test['status'] ?? '') !== 'passed')) === 0 ? 'passed' : 'failed');
        }

        return $report;
    }
}
