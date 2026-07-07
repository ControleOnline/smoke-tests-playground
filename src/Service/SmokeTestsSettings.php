<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeTestsSettings
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function testsPath(): string
    {
        return $this->normalizePath($this->env(
            'SMOKE_TESTS_PLAYGROUND_TESTS_PATH',
            $this->projectDir.'/var/tests',
        ) ?? $this->projectDir.'/var/tests');
    }

    public function indexPath(): string
    {
        return rtrim($this->testsPath(), '/\\').DIRECTORY_SEPARATOR.'index.json';
    }

    /**
     * @deprecated Kept for compatibility with older callers.
     */
    public function reportPath(): string
    {
        return $this->indexPath();
    }

    public function suiteReportPath(string $suite): string
    {
        return rtrim($this->testsPath(), '/\\').DIRECTORY_SEPARATOR.$suite.DIRECTORY_SEPARATOR.'report.json';
    }

    public function runCommand(): string
    {
        return $this->env(
            'SMOKE_TESTS_PLAYGROUND_RUN_COMMAND',
            'node node_modules/@playwright/test/cli.js test --config=playwright.config.cjs tests/browser/*.spec.js',
        ) ?? 'node node_modules/@playwright/test/cli.js test --config=playwright.config.cjs tests/browser/*.spec.js';
    }

    public function runWorkingDirectory(): string
    {
        return $this->normalizePath($this->env(
            'SMOKE_TESTS_PLAYGROUND_RUN_WORKDIR',
            $this->projectDir,
        ) ?? $this->projectDir);
    }

    public function runTimeout(): int
    {
        return max(30, (int) ($this->env('SMOKE_TESTS_PLAYGROUND_RUN_TIMEOUT', '600') ?? '600'));
    }

    public function defaultEnvLines(): array
    {
        return [
            $this->formatEnvLine('PLAYWRIGHT_BROWSERS_PATH', '0'),
            $this->formatEnvLine('SMOKE_TESTS_PLAYGROUND_TESTS_PATH', $this->defaultTestsPathValue()),
            $this->formatEnvLine('SMOKE_TESTS_PLAYGROUND_RUN_COMMAND', $this->runCommand()),
            $this->formatEnvLine('SMOKE_TESTS_PLAYGROUND_RUN_WORKDIR', $this->defaultRunWorkingDirectoryValue()),
            $this->formatEnvLine('SMOKE_TESTS_PLAYGROUND_RUN_TIMEOUT', (string) $this->runTimeout()),
        ];
    }

    public function defaultTestsPathValue(): string
    {
        return 'var/tests';
    }

    public function defaultRunWorkingDirectoryValue(): string
    {
        return '.';
    }

    private function env(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $default;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '' || $this->isAbsolutePath($path)) {
            return $path;
        }

        return rtrim($this->projectDir, '/\\').DIRECTORY_SEPARATOR.$path;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private function formatEnvLine(string $key, string $value): string
    {
        return $key.'="'.$this->escapeEnvValue($value).'"';
    }

    private function escapeEnvValue(string $value): string
    {
        return str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value);
    }
}
