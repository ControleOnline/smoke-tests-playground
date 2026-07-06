<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeTestsPublicStateFactory
{
    public function __construct(
        private readonly SmokeReportReader $reportReader,
    ) {
    }

    public function create(): array
    {
        $report = $this->reportReader->readLatest();

        return $this->normalizePublicState($report);
    }

    public function createRunResponse(
        SmokeRunResult $runResult,
        string $requestedMethod,
        string $runRequestedAt,
    ): array {
        $state = $this->create();
        $state['run'] = [
            'successful' => $runResult->successful,
            'message' => $this->buildRunMessage($runResult),
            'requestedAt' => $runRequestedAt,
            'requestedMethod' => $requestedMethod,
        ];
        $state['status'] = $runResult->successful ? 'passed' : 'failed';
        $state['progress'] = 100;
        $state['message'] = $this->buildRunMessage($runResult);

        return $state;
    }

    private function normalizePublicState(?array $report): array
    {
        if ($report === null) {
            return [
                'status' => 'idle',
                'progress' => 0,
                'message' => 'Ainda não existe um relatório publicado.',
                'lastRunAt' => null,
            ];
        }

        $status = $report['status'] ?? 'failed';
        $progress = $status === 'passed' ? 100 : 100;

        return [
            'status' => $status,
            'progress' => $progress,
            'message' => $this->buildReportMessage($report),
            'lastRunAt' => $report['generatedAt'] ?? null,
        ];
    }

    private function buildReportMessage(array $report): string
    {
        if (($report['status'] ?? null) === 'passed') {
            return 'Última execução concluída com sucesso.';
        }

        if (($report['error'] ?? null) !== null) {
            return 'Última execução falhou ao publicar o relatório.';
        }

        return 'Última execução retornou falha.';
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

        if (count($parts) === 1) {
            return $parts[0];
        }

        return implode("\n\n--- stdout ---\n\n", $parts);
    }
}
