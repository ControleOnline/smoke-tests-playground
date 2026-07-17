<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

use Symfony\Component\Process\Process;

final class SmokeCommandResolver
{
    /**
     * @return list<string>
     */
    public function toProcessArguments(string $command): array
    {
        $trimmedCommand = trim($command);
        $parts = preg_split('/\s+/', $trimmedCommand) ?: [];

        if ($parts === []) {
            throw new \InvalidArgumentException('Smoke command cannot be empty.');
        }

        $executable = array_shift($parts);
        if ($executable === null) {
            throw new \InvalidArgumentException('Smoke command cannot be empty.');
        }

        return array_merge([$this->resolveExecutable($executable)], $parts);
    }

    private function resolveExecutable(string $executable): string
    {
        $normalized = str_replace('\\', '/', $executable);

        if ($this->shouldResolveNodeExecutable($normalized)) {
            $resolvedExecutable = $this->probeExecutable($executable);
            if ($resolvedExecutable !== '') {
                return $resolvedExecutable;
            }

            $resolvedAfterNvm = $this->probeExecutableAfterNvm($executable);
            if ($resolvedAfterNvm !== '') {
                return $resolvedAfterNvm;
            }
        }

        if (preg_match('#^(?:\./)?node_modules/\.bin/playwright(?:\.cmd)?$#', $normalized) === 1) {
            if (DIRECTORY_SEPARATOR === '\\') {
                return 'node_modules/.bin/playwright';
            }

            return 'node_modules/.bin/playwright';
        }

        return $executable;
    }

    private function shouldResolveNodeExecutable(string $executable): bool
    {
        return in_array(strtolower($executable), ['node', 'nodejs', 'npm', 'npx'], true);
    }

    private function probeExecutable(string $executable): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return '';
        }

        $process = new Process([
            'bash',
            '-lc',
            'command -v '.escapeshellarg($executable).' 2>/dev/null || true',
        ]);
        $process->setTimeout(10);
        $process->run();

        if (!$process->isSuccessful()) {
            return '';
        }

        return trim($process->getOutput());
    }

    private function probeExecutableAfterNvm(string $executable): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return '';
        }

        $process = new Process([
            'bash',
            '-lc',
            'source ~/.bashrc >/dev/null 2>&1 && nvm use --lts >/dev/null 2>&1 && command -v '.escapeshellarg($executable).' 2>/dev/null || true',
        ]);
        $process->setTimeout(10);
        $process->run();

        if (!$process->isSuccessful()) {
            return '';
        }

        return trim($process->getOutput());
    }
}
