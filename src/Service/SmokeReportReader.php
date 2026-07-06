<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeReportReader
{
    public function __construct(
        private readonly SmokeTestsSettings $settings,
    ) {
    }

    /**
     * @return list<string>
     */
    public function discoverReportFiles(): array
    {
        $pattern = rtrim($this->settings->testsPath(), '/\\').DIRECTORY_SEPARATOR.'*/report.json';
        $files = glob($pattern) ?: [];
        sort($files, SORT_STRING);

        return array_values(array_filter($files, static fn (string $file): bool => is_file($file)));
    }

    public function readReportFile(string $reportPath): ?array
    {
        if (!is_file($reportPath)) {
            return null;
        }

        $suite = basename(dirname($reportPath));
        $updatedAt = $this->formatFileTime($reportPath);

        try {
            $decoded = json_decode((string) file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->buildInvalidReport($suite, $updatedAt, 'O arquivo report.json da suite é inválido.');
        }

        if (!is_array($decoded)) {
            return $this->buildInvalidReport($suite, $updatedAt, 'O arquivo report.json da suite não contém um objeto JSON.');
        }

        return $this->normalizeReport($suite, $decoded, $reportPath, $updatedAt);
    }

    public function buildArtifactUrl(string $suite, string $relativePath): string
    {
        $segments = array_values(array_filter(explode('/', str_replace('\\', '/', $relativePath)), static fn (string $segment): bool => $segment !== ''));
        array_unshift($segments, $suite);

        return '/tests/artifacts/'.implode('/', array_map('rawurlencode', $segments));
    }

    private function normalizeReport(string $suite, array $report, string $reportPath, ?string $updatedAt): array
    {
        $reportDirectory = dirname($reportPath);
        $tests = $this->normalizeTests($suite, $reportDirectory, $report['tests'] ?? []);
        $summary = $this->buildSummary($tests);
        $status = $this->resolveStatus($report['status'] ?? null, $summary);

        return [
            'suite' => $suite,
            'displayName' => $this->humanizeSuiteName($suite),
            'generatedAt' => isset($report['generatedAt']) ? (string) $report['generatedAt'] : null,
            'updatedAt' => $updatedAt,
            'status' => $status,
            'summary' => $summary,
            'tests' => $tests,
            'error' => isset($report['error']) ? (string) $report['error'] : null,
            'links' => [
                'report' => $this->buildArtifactUrl($suite, 'report.json'),
            ],
        ];
    }

    private function buildInvalidReport(string $suite, ?string $updatedAt, string $error): array
    {
        return [
            'suite' => $suite,
            'displayName' => $this->humanizeSuiteName($suite),
            'generatedAt' => null,
            'updatedAt' => $updatedAt,
            'status' => 'failed',
            'summary' => [
                'total' => 0,
                'passed' => 0,
                'failed' => 0,
            ],
            'tests' => [],
            'error' => $error,
            'links' => [
                'report' => $this->buildArtifactUrl($suite, 'report.json'),
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $tests
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeTests(string $suite, string $reportDirectory, array $tests): array
    {
        return array_values(array_map(function (array $test) use ($suite, $reportDirectory): array {
            return [
                'title' => (string) ($test['title'] ?? $test['name'] ?? 'Teste sem nome'),
                'status' => (string) ($test['status'] ?? 'failed'),
                'error' => isset($test['error']) ? (string) $test['error'] : null,
                'screenshots' => $this->normalizeArtifacts($suite, $reportDirectory, $test['screenshots'] ?? []),
                'steps' => $this->normalizeSteps($suite, $reportDirectory, $test['steps'] ?? []),
            ];
        }, $tests));
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeSteps(string $suite, string $reportDirectory, array $steps): array
    {
        return array_values(array_map(function (array $step) use ($suite, $reportDirectory): array {
            return [
                'title' => (string) ($step['title'] ?? $step['name'] ?? 'Etapa sem nome'),
                'status' => (string) ($step['status'] ?? 'failed'),
                'error' => isset($step['error']) ? (string) $step['error'] : null,
                'screenshots' => $this->normalizeArtifacts($suite, $reportDirectory, $step['screenshots'] ?? []),
            ];
        }, $steps));
    }

    /**
     * @param array<int, array<string, mixed>> $artifacts
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeArtifacts(string $suite, string $reportDirectory, array $artifacts): array
    {
        $normalized = [];

        foreach ($artifacts as $artifact) {
            if (!is_array($artifact)) {
                continue;
            }

            $relativePath = $this->normalizeRelativePath((string) ($artifact['path'] ?? ''));
            if ($relativePath === null) {
                continue;
            }

            $absolutePath = $reportDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            $normalized[] = [
                'label' => (string) ($artifact['label'] ?? 'Print'),
                'name' => basename($relativePath),
                'url' => $this->buildArtifactUrl($suite, $relativePath),
                'mimeType' => $this->mimeTypeFromPath($relativePath),
                'kind' => $this->kindFromMimeType($this->mimeTypeFromPath($relativePath)),
                'available' => is_file($absolutePath),
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $tests
     *
     * @return array{total:int,passed:int,failed:int}
     */
    private function buildSummary(array $tests): array
    {
        $passed = 0;
        $failed = 0;

        foreach ($tests as $test) {
            if (($test['status'] ?? null) === 'passed') {
                $passed++;

                continue;
            }

            $failed++;
        }

        return [
            'total' => count($tests),
            'passed' => $passed,
            'failed' => $failed,
        ];
    }

    /**
     * @param array{total:int,passed:int,failed:int} $summary
     *
     * @return 'passed'|'failed'
     */
    private function resolveStatus(mixed $rawStatus, array $summary): string
    {
        $status = is_string($rawStatus) ? $rawStatus : '';

        if ($status === 'passed' || $status === 'failed') {
            return $status;
        }

        return ($summary['total'] > 0 && $summary['failed'] === 0) ? 'passed' : 'failed';
    }

    private function humanizeSuiteName(string $suite): string
    {
        $value = preg_replace('/[-_]+/', ' ', $suite) ?? $suite;
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return ucwords($value);
    }

    private function normalizeRelativePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);
        $cleanSegments = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }

            $cleanSegments[] = $segment;
        }

        return implode('/', $cleanSegments);
    }

    private function formatFileTime(string $path): ?string
    {
        $mtime = filemtime($path);
        if ($mtime === false) {
            return null;
        }

        return date(DATE_ATOM, $mtime);
    }

    private function mimeTypeFromPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            'avif' => 'image/avif',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'json' => 'application/json',
            'txt' => 'text/plain; charset=UTF-8',
            default => 'application/octet-stream',
        };
    }

    private function kindFromMimeType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_contains($mimeType, 'json') => 'json',
            str_starts_with($mimeType, 'text/') => 'text',
            default => 'binary',
        };
    }
}
