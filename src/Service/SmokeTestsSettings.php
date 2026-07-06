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
            $this->projectDir.'/var/tests/browser-smoke/transporter-login',
        ) ?? $this->projectDir.'/var/tests/browser-smoke/transporter-login');
    }

    public function reportPath(): string
    {
        return rtrim($this->testsPath(), '/\\').DIRECTORY_SEPARATOR.'report.json';
    }

    public function runCommand(): string
    {
        return $this->env(
            'SMOKE_TESTS_PLAYGROUND_RUN_COMMAND',
            'npx playwright test --config=playwright.config.cjs tests/browser/transporter-login.spec.js',
        ) ?? 'npx playwright test --config=playwright.config.cjs tests/browser/transporter-login.spec.js';
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
            'SMOKE_TESTS_PLAYGROUND_TESTS_PATH='.$this->defaultTestsPathValue(),
            'SMOKE_TESTS_PLAYGROUND_RUN_COMMAND='.$this->runCommand(),
            'SMOKE_TESTS_PLAYGROUND_RUN_WORKDIR='.$this->defaultRunWorkingDirectoryValue(),
            'SMOKE_TESTS_PLAYGROUND_RUN_TIMEOUT='.$this->runTimeout(),
        ];
    }

    public function defaultTestsPathValue(): string
    {
        return 'var/tests/browser-smoke/transporter-login';
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
}
