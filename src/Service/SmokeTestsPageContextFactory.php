<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeTestsPageContextFactory
{
    public function create(): array
    {
        return [
            'page_title' => 'Smoke Tests Playground',
            'page_heading' => 'Último smoke do transporter',
            'page_subtitle' => 'Essa página consome a API JSON em `/tests/api` e dispara novos testes via `/tests/run`.',
            'status_class' => 'idle',
            'status_label' => 'Sem relatório',
            'tests_endpoint' => '/tests/api',
            'run_endpoint' => '/tests/run',
        ];
    }
}
