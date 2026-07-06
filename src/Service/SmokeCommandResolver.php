<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeCommandResolver
{
    /**
     * @return list<string>
     */
    public function toProcessArguments(string $command): array
    {
        $parts = preg_split('/\s+/', trim($command)) ?: [];

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

        if (preg_match('#^(?:\./)?node_modules/\.bin/playwright(?:\.cmd)?$#', $normalized) === 1) {
            if (DIRECTORY_SEPARATOR === '\\') {
                return 'node_modules/.bin/playwright';
            }

            return 'node_modules/.bin/playwright';
        }

        return $executable;
    }
}
