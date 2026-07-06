<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeTestsPageContextFactory
{
    public function __construct(
        private readonly SmokeTestsPublicStateFactory $publicStateFactory,
    ) {
    }

    public function create(): array
    {
        $state = $this->publicStateFactory->create();

        return [
            'page_title' => 'Smoke Tests Playground',
            'page_heading' => 'Conferência do último smoke',
            'page_subtitle' => 'Essa tela mostra apenas o estado público da execução e dispara novos testes via API.',
            'status_class' => $state['status'],
            'status_label' => $this->statusLabel($state['status']),
            'progress' => $state['progress'],
            'message' => $state['message'],
            'last_run_at' => $state['lastRunAt'] ?? null,
            'tests_endpoint' => '/tests/api',
            'run_endpoint' => '/tests/run',
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'passed' => 'Passou',
            'failed' => 'Falhou',
            default => 'Sem relatório',
        };
    }
}
