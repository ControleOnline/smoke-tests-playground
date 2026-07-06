<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeRunResponseFactory
{
    public function create(
        SmokeRunResult $runResult,
        string $requestedMethod,
        string $requestedAt,
    ): array {
        return [
            'successful' => $runResult->successful,
            'status' => $runResult->successful ? 'passed' : 'failed',
            'progress' => 100,
            'message' => $this->buildRunMessage($runResult),
            'requestedAt' => $requestedAt,
            'requestedMethod' => $requestedMethod,
            'exitCode' => $runResult->exitCode,
        ];
    }

    private function buildRunMessage(SmokeRunResult $runResult): string
    {
        if ($runResult->successful) {
            return 'Execução concluída com sucesso.';
        }

        $parts = [];

        if (trim($runResult->errorOutput) !== '') {
            $parts[] = trim($runResult->errorOutput);
        }

        if (trim($runResult->output) !== '') {
            $parts[] = trim($runResult->output);
        }

        if ($parts === []) {
            return 'A execução falhou sem detalhes adicionais.';
        }

        return implode("\n\n--- stdout ---\n\n", $parts);
    }
}
