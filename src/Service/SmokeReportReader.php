<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeReportReader
{
    public function __construct(
        private readonly SmokeTestsSettings $settings,
        private readonly SmokeSuitePathCodec $suitePathCodec,
    ) {
    }

    /**
     * @return list<string>
     */
    public function discoverReportFiles(): array
    {
        $root = rtrim($this->settings->testsPath(), '/\\');
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $filename = strtolower($fileInfo->getFilename());
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if ($filename !== 'report.json' && $extension !== 'xml') {
                continue;
            }

            $files[] = $fileInfo->getPathname();
        }

        sort($files, SORT_STRING);

        return array_values(array_filter($files, static fn (string $file): bool => is_file($file)));
    }

    public function readReportFile(string $reportPath): ?array
    {
        if (!is_file($reportPath)) {
            return null;
        }

        $extension = strtolower(pathinfo($reportPath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['json', 'xml'], true)) {
            return null;
        }

        $context = $this->buildSuiteContext($reportPath);
        $updatedAt = $this->formatFileTime($reportPath);

        if ($extension === 'json') {
            try {
                $decoded = json_decode((string) file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return $this->buildInvalidReport($context, $updatedAt, basename($reportPath), 'O arquivo report.json da suite é inválido.');
            }

            if (!is_array($decoded)) {
                return $this->buildInvalidReport($context, $updatedAt, basename($reportPath), 'O arquivo report.json da suite não contém um objeto JSON.');
            }

            return $this->normalizeStructuredReport($context, $decoded, $reportPath, $updatedAt);
        }

        return $this->readXmlReportFile($context, $reportPath, $updatedAt);
    }

    public function buildArtifactUrl(string $suiteId, string $relativePath): string
    {
        $segments = array_values(array_filter(explode('/', str_replace('\\', '/', $relativePath)), static fn (string $segment): bool => $segment !== ''));
        array_unshift($segments, $suiteId);

        return '/tests/artifacts/'.implode('/', array_map('rawurlencode', $segments));
    }

    /**
     * @param array<string, mixed> $report
     *
     * @return array<string, string>
     */
    private function buildSuiteContext(string $reportPath): array
    {
        $reportDirectory = dirname($reportPath);
        $testsRoot = rtrim($this->settings->testsPath(), '/\\');
        $relativeSuitePath = $this->suitePathCodec->relativePath($testsRoot, $reportDirectory);

        if ($relativeSuitePath === null || $relativeSuitePath === '') {
            $relativeSuitePath = basename($reportDirectory);
        }

        $relativeSuitePath = $this->suitePathCodec->normalizeRelativePath($relativeSuitePath) ?? basename($reportDirectory);
        $segments = explode('/', $relativeSuitePath);
        $suiteLeaf = basename($relativeSuitePath);
        $rootName = $this->normalizeTypeKey(basename($testsRoot));

        if (count($segments) > 1) {
            $type = $segments[0];
        } elseif ($rootName !== '' && $rootName !== 'tests') {
            $type = $rootName;
        } else {
            $type = $suiteLeaf;
        }

        $type = $this->normalizeTypeKey($type);
        if ($type === '') {
            $type = 'general';
        }

        return [
            'suitePath' => $relativeSuitePath,
            'suiteId' => $this->suitePathCodec->encode($relativeSuitePath),
            'suite' => $suiteLeaf,
            'type' => $type,
            'typeDisplayName' => $this->suitePathCodec->humanizeLabel($type),
        ];
    }

    /**
     * @param array<string, mixed> $report
     *
     * @return array<string, mixed>
     */
    private function normalizeStructuredReport(array $context, array $report, string $reportPath, ?string $updatedAt): array
    {
        $tests = $this->normalizeTests($context['suiteId'], dirname($reportPath), $report['tests'] ?? []);

        return $this->normalizeReportPayload($context, $report, $reportPath, $updatedAt, $tests);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>|null
     */
    private function readXmlReportFile(array $context, string $reportPath, ?string $updatedAt): ?array
    {
        $previousUseInternalErrors = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_file($reportPath, \SimpleXMLElement::class, LIBXML_NOCDATA);
        } finally {
            libxml_use_internal_errors($previousUseInternalErrors);
        }

        if (!$xml instanceof \SimpleXMLElement) {
            return $this->buildInvalidReport($context, $updatedAt, basename($reportPath), 'O arquivo XML da suite é inválido.');
        }

        $rootName = $xml->getName();
        if ($rootName !== 'testsuite' && $rootName !== 'testsuites') {
            return null;
        }

        $report = [
            'suite' => $this->extractXmlSuiteName($xml, $context['suite']),
            'displayName' => $this->extractXmlDisplayName($xml, $context['suite']),
            'generatedAt' => $this->extractXmlGeneratedAt($xml, $updatedAt),
            'type' => $context['type'],
            'typeDisplayName' => $context['typeDisplayName'],
        ];

        return $this->normalizeReportPayload(
            $context,
            $report,
            $reportPath,
            $updatedAt,
            $this->normalizeXmlTests($xml),
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $report
     * @param list<array<string, mixed>> $tests
     *
     * @return array<string, mixed>
     */
    private function normalizeReportPayload(array $context, array $report, string $reportPath, ?string $updatedAt, array $tests): array
    {
        $suite = $this->firstString($report, ['suite', 'suiteName']) ?? $context['suite'];
        $displayName = $this->firstString($report, ['displayName']) ?? $this->suitePathCodec->humanizeLabel($suite);
        $type = $this->normalizeTypeKey($this->firstString($report, ['type', 'suiteType', 'category']) ?? $context['type']);
        $typeDisplayName = $this->firstString($report, ['typeDisplayName']) ?? $this->suitePathCodec->humanizeLabel($type);
        $generatedAt = $this->firstString($report, ['generatedAt', 'timestamp', 'createdAt']) ?? $updatedAt;
        $summary = $this->buildSummary($tests);
        $status = $this->resolveStatus($this->firstString($report, ['status']), $summary);

        return [
            'type' => $type,
            'typeDisplayName' => $typeDisplayName,
            'suite' => $suite,
            'suitePath' => $context['suitePath'],
            'suiteId' => $context['suiteId'],
            'displayName' => $displayName,
            'generatedAt' => $generatedAt,
            'updatedAt' => $updatedAt,
            'status' => $status,
            'summary' => $summary,
            'tests' => $tests,
            'error' => $this->firstString($report, ['error']),
            'links' => [
                'report' => $this->buildArtifactUrl($context['suiteId'], basename($reportPath)),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function buildInvalidReport(array $context, ?string $updatedAt, string $reportFileName, string $error): array
    {
        return [
            'type' => $context['type'],
            'typeDisplayName' => $context['typeDisplayName'],
            'suite' => $context['suite'],
            'suitePath' => $context['suitePath'],
            'suiteId' => $context['suiteId'],
            'displayName' => $this->suitePathCodec->humanizeLabel($context['suite']),
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
                'report' => $this->buildArtifactUrl($context['suiteId'], $reportFileName),
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $tests
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeTests(string $suiteId, string $reportDirectory, array $tests): array
    {
        return array_values(array_map(function (array $test) use ($suiteId, $reportDirectory): array {
            return [
                'title' => (string) ($test['title'] ?? $test['name'] ?? 'Teste sem nome'),
                'status' => (string) ($test['status'] ?? 'failed'),
                'error' => isset($test['error']) ? (string) $test['error'] : null,
                'screenshots' => $this->normalizeArtifacts($suiteId, $reportDirectory, $test['screenshots'] ?? []),
                'steps' => $this->normalizeSteps($suiteId, $reportDirectory, $test['steps'] ?? []),
            ];
        }, $tests));
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeSteps(string $suiteId, string $reportDirectory, array $steps): array
    {
        return array_values(array_map(function (array $step) use ($suiteId, $reportDirectory): array {
            return [
                'title' => (string) ($step['title'] ?? $step['name'] ?? 'Etapa sem nome'),
                'status' => (string) ($step['status'] ?? 'failed'),
                'error' => isset($step['error']) ? (string) $step['error'] : null,
                'screenshots' => $this->normalizeArtifacts($suiteId, $reportDirectory, $step['screenshots'] ?? []),
            ];
        }, $steps));
    }

    /**
     * @param array<int, array<string, mixed>> $artifacts
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeArtifacts(string $suiteId, string $reportDirectory, array $artifacts): array
    {
        $normalized = [];

        foreach ($artifacts as $artifact) {
            if (!is_array($artifact)) {
                continue;
            }

            $relativePath = $this->suitePathCodec->normalizeRelativePath((string) ($artifact['path'] ?? ''));
            if ($relativePath === null) {
                continue;
            }

            $absolutePath = $reportDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            $normalized[] = [
                'label' => (string) ($artifact['label'] ?? 'Print'),
                'name' => basename($relativePath),
                'url' => $this->buildArtifactUrl($suiteId, $relativePath),
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

    /**
     * @param array<int, string> $keys
     */
    private function firstString(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $source[$key] ?? null;
            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normalizeTypeKey(string $value): string
    {
        $normalized = $this->suitePathCodec->normalizeKey($value);

        return $normalized !== '' ? $normalized : 'general';
    }

    private function extractXmlSuiteName(\SimpleXMLElement $xml, string $fallback): string
    {
        $rootName = trim((string) ($xml['name'] ?? ''));
        if ($rootName !== '') {
            return $rootName;
        }

        foreach ($xml->testsuite as $suiteNode) {
            $childName = trim((string) ($suiteNode['name'] ?? ''));
            if ($childName !== '') {
                return $childName;
            }
        }

        return $fallback;
    }

    private function extractXmlDisplayName(\SimpleXMLElement $xml, string $fallback): string
    {
        return $this->suitePathCodec->humanizeLabel($this->extractXmlSuiteName($xml, $fallback));
    }

    private function extractXmlGeneratedAt(\SimpleXMLElement $xml, ?string $updatedAt): ?string
    {
        $candidates = [
            trim((string) ($xml['timestamp'] ?? '')),
        ];

        foreach ($xml->testsuite as $suiteNode) {
            $candidates[] = trim((string) ($suiteNode['timestamp'] ?? ''));
        }

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            return $candidate;
        }

        return $updatedAt;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeXmlTests(\SimpleXMLElement $xml): array
    {
        $tests = [];

        if ($xml->getName() === 'testsuites') {
            foreach ($xml->testsuite as $suiteNode) {
                $tests = array_merge($tests, $this->normalizeXmlTests($suiteNode));
            }

            return $tests;
        }

        foreach ($xml->testcase as $testcase) {
            $tests[] = $this->normalizeXmlTestCase($testcase);
        }

        foreach ($xml->testsuite as $suiteNode) {
            $tests = array_merge($tests, $this->normalizeXmlTests($suiteNode));
        }

        return $tests;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeXmlTestCase(\SimpleXMLElement $testcase): array
    {
        $error = $this->extractXmlFailureMessage($testcase);

        return [
            'title' => $this->buildXmlTestTitle($testcase),
            'status' => $error === null ? 'passed' : 'failed',
            'error' => $error,
            'screenshots' => [],
            'steps' => [],
        ];
    }

    private function buildXmlTestTitle(\SimpleXMLElement $testcase): string
    {
        $classname = trim((string) ($testcase['classname'] ?? ''));
        $name = trim((string) ($testcase['name'] ?? ''));

        if ($classname === '' && $name === '') {
            return 'Teste sem nome';
        }

        if ($classname === '') {
            return $name;
        }

        if ($name === '') {
            return $classname;
        }

        return $classname.'::'.$name;
    }

    private function extractXmlFailureMessage(\SimpleXMLElement $testcase): ?string
    {
        foreach (['failure', 'error'] as $nodeName) {
            if (!isset($testcase->{$nodeName})) {
                continue;
            }

            foreach ($testcase->{$nodeName} as $failureNode) {
                $message = trim((string) ($failureNode['message'] ?? ''));
                $body = trim((string) $failureNode);

                if ($message !== '') {
                    return $message;
                }

                if ($body !== '') {
                    return $body;
                }
            }
        }

        return null;
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
