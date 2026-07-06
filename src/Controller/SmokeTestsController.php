<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Controller;

use ControleOnline\SmokeTestsPlayground\Service\SmokeReportReader;
use ControleOnline\SmokeTestsPlayground\Service\SmokeRunResult;
use ControleOnline\SmokeTestsPlayground\Service\SmokeRunner;
use ControleOnline\SmokeTestsPlayground\Service\SmokeTestsSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SmokeTestsController extends AbstractController
{
    public function __construct(
        private readonly SmokeReportReader $reportReader,
        private readonly SmokeRunner $runner,
        private readonly SmokeTestsSettings $settings,
    ) {
    }

    #[Route(path: '/tests', name: 'smoke_tests_playground_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->renderPage($this->reportReader->readLatest());
    }

    #[Route(path: '/tests/run', name: 'smoke_tests_playground_run', methods: ['POST'])]
    public function run(): Response
    {
        $runResult = $this->runner->run();

        return $this->renderPage(
            $this->reportReader->readLatest(),
            $runResult,
        );
    }

    private function renderPage(?array $report, ?SmokeRunResult $runResult = null): Response
    {
        $title = 'Smoke Tests Playground';
        $overallStatus = $report['status'] ?? 'idle';
        $statusLabel = $overallStatus === 'passed' ? 'Passou' : ($overallStatus === 'failed' ? 'Falhou' : 'Sem relatório');
        $statusClass = $overallStatus === 'passed' ? 'passed' : ($overallStatus === 'failed' ? 'failed' : 'idle');
        $titleEsc = $this->e($title);
        $statusLabelEsc = $this->e($statusLabel);
        $statusClassEsc = $this->e($statusClass);
        $flashHtml = $this->buildRunFeedback($runResult);
        $testsHtml = $this->buildTestsHtml($report['tests'] ?? []);
        $summaryHtml = $this->buildSummaryHtml($report);

        $html = <<<HTML
<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$titleEsc}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f3f4f6;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #f59e0b;
            --border: #dbe2ea;
            --shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(180deg, #e2e8f0 0%, var(--bg) 240px, #eef2ff 100%);
            color: var(--text);
        }

        .shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 32px 20px 56px;
        }

        .hero {
            background: linear-gradient(135deg, #0f172a, #111827 45%, #1f2937);
            color: #fff;
            border-radius: 24px;
            padding: 28px 28px 24px;
            box-shadow: var(--shadow);
        }

        .hero-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: start;
            flex-wrap: wrap;
        }

        .eyebrow {
            text-transform: uppercase;
            letter-spacing: .14em;
            font-size: 12px;
            opacity: .7;
            margin: 0 0 10px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: clamp(28px, 4vw, 44px);
            line-height: 1.05;
        }

        .subtitle {
            margin: 0;
            color: rgba(255,255,255,.8);
            max-width: 72ch;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 999px;
            font-weight: 700;
            background: rgba(255,255,255,.12);
            color: #fff;
            white-space: nowrap;
        }

        .status-pill.passed { background: rgba(34,197,94,.18); color: #86efac; }
        .status-pill.failed { background: rgba(239,68,68,.18); color: #fca5a5; }
        .status-pill.idle { background: rgba(148,163,184,.18); color: #e2e8f0; }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--shadow);
        }

        .controls {
            margin-top: 18px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .button {
            border: 0;
            border-radius: 14px;
            padding: 14px 18px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .button.primary {
            background: linear-gradient(135deg, #38bdf8, #2563eb);
            color: #fff;
        }

        .button.secondary {
            background: #e2e8f0;
            color: #0f172a;
        }

        .grid {
            margin-top: 22px;
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 18px;
        }

        .summary {
            grid-column: span 4;
            padding: 20px;
        }

        .content {
            grid-column: span 8;
            padding: 20px;
        }

        .section-title {
            margin: 0 0 10px;
            font-size: 18px;
        }

        .muted {
            color: var(--muted);
        }

        .message {
            margin: 18px 0 0;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 600;
        }

        .message.success {
            background: rgba(34,197,94,.12);
            color: var(--success);
        }

        .message.failed {
            background: rgba(239,68,68,.12);
            color: var(--danger);
        }

        .message.warning {
            background: rgba(245,158,11,.16);
            color: #b45309;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
        }

        .badge.pass { background: rgba(34,197,94,.12); color: var(--success); }
        .badge.fail { background: rgba(239,68,68,.12); color: var(--danger); }

        .shots {
            margin-top: 18px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .shot {
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .shot img {
            width: 100%;
            display: block;
        }

        .shot-meta {
            padding: 12px 14px 14px;
        }

        .shot-title {
            margin: 0 0 4px;
            font-weight: 700;
        }

        pre {
            white-space: pre-wrap;
            word-break: break-word;
            background: #0f172a;
            color: #dbeafe;
            padding: 14px 16px;
            border-radius: 14px;
            overflow: auto;
            margin: 14px 0 0;
        }

        @media (max-width: 960px) {
            .summary, .content { grid-column: span 12; }
            .shots { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="hero">
            <div class="hero-row">
                <div>
                    <p class="eyebrow">Smoke tests playground</p>
                    <h1>Último teste do transporter</h1>
                    <p class="subtitle">Página inicial da lib para conferir o último relatório disponível e disparar uma nova execução sem sair do navegador.</p>
                </div>
                <div class="status-pill {$statusClassEsc}">{$statusLabelEsc}</div>
            </div>

            {$flashHtml}

            <div class="controls">
                <form method="post" action="/tests/run">
                    <button class="button primary" type="submit">Rodar novos testes</button>
                </form>
                <a class="button secondary" href="/tests">Recarregar relatório</a>
            </div>
        </section>

        <section class="grid">
            <aside class="card summary">
                <h2 class="section-title">Resumo</h2>
                {$summaryHtml}
            </aside>

            <article class="card content">
                <h2 class="section-title">Testes registrados</h2>
                {$testsHtml}
            </article>
        </section>
    </main>
</body>
</html>
HTML;

        return new Response($html);
    }

    private function buildSummaryHtml(?array $report): string
    {
        if ($report === null) {
            return '<p class="muted">Ainda não existe relatório publicado para este ambiente.</p>';
        }

        $testsCount = count($report['tests'] ?? []);
        $generatedAt = $report['generatedAt'] ?? null;
        $suite = $report['suite'] ?? 'smoke-tests-playground';
        $reportPath = $this->settings->reportPath();
        $testsPath = $this->settings->testsPath();

        return sprintf(
            '<p><strong>Suite:</strong> %s</p><p><strong>Geração:</strong> %s</p><p><strong>Total:</strong> %d teste(s)</p><p class="muted"><strong>Tests path:</strong><br>%s</p><p class="muted"><strong>Report:</strong><br>%s</p>',
            $this->e((string) $suite),
            $this->e($generatedAt ? (string) $generatedAt : 'indisponível'),
            $testsCount,
            $this->e($testsPath),
            $this->e($reportPath),
        );
    }

    private function buildTestsHtml(array $tests): string
    {
        if ($tests === []) {
            return '<p class="muted">Nenhum teste encontrado no relatório atual.</p>';
        }

        $rows = [];

        foreach ($tests as $test) {
            $status = ($test['status'] ?? 'failed') === 'passed' ? 'Passou' : 'Falhou';
            $statusClass = ($test['status'] ?? 'failed') === 'passed' ? 'pass' : 'fail';
            $error = trim((string) ($test['error'] ?? ''));
            $error = $error !== '' ? $error : 'Sem erro registrado';

            $rows[] = sprintf(
                '<tr><td><strong>%s</strong></td><td><span class="badge %s">%s</span></td><td>%s</td></tr>',
                $this->e((string) ($test['title'] ?? 'Teste sem título')),
                $statusClass,
                $status,
                $this->e($error),
            );
        }

        $html = '<table><thead><tr><th>Teste</th><th>Status</th><th>Erro</th></tr></thead><tbody>'.implode('', $rows).'</tbody></table>';

        $shots = [];
        foreach ($tests as $test) {
            foreach (($test['screenshots'] ?? []) as $screenshot) {
                $src = $screenshot['src'] ?? null;
                if (!is_string($src) || $src === '') {
                    continue;
                }

                $shots[] = sprintf(
                    '<div class="shot"><img src="%s" alt="%s"><div class="shot-meta"><p class="shot-title">%s</p><div class="muted">%s</div></div></div>',
                    $this->e($src),
                    $this->e((string) ($screenshot['label'] ?? 'Screenshot')),
                    $this->e((string) ($screenshot['label'] ?? 'Screenshot')),
                    $this->e((string) ($screenshot['path'] ?? '')),
                );
            }
        }

        if ($shots !== []) {
            $html .= '<h2 class="section-title" style="margin-top:18px;">Imagens</h2><div class="shots">'.implode('', $shots).'</div>';
        }

        return $html;
    }

    private function buildRunFeedback(?SmokeRunResult $runResult): string
    {
        if ($runResult === null) {
            return '';
        }

        $statusClass = $runResult->successful ? 'success' : 'failed';
        $title = $runResult->successful ? 'Execução concluída com sucesso.' : 'Execução falhou.';
        $details = trim($runResult->output."\n".$runResult->errorOutput);
        $detailsHtml = $details !== '' ? '<pre>'.$this->e($details).'</pre>' : '';

        return sprintf(
            '<div class="message %s">%s%s</div>',
            $statusClass,
            $this->e($title),
            $detailsHtml,
        );
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
