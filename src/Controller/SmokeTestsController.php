<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Controller;

use ControleOnline\SmokeTestsPlayground\Service\SmokeReportReader;
use ControleOnline\SmokeTestsPlayground\Service\SmokeRunner;
use ControleOnline\SmokeTestsPlayground\Service\SmokeTestsSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
    public function index(): JsonResponse
    {
        return $this->json($this->buildPayload());
    }

    #[Route(path: '/tests/ui', name: 'smoke_tests_playground_ui', methods: ['GET'])]
    public function ui(): Response
    {
        $html = <<<'HTML'
<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>__PAGE_TITLE__</title>
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

        .button[disabled] {
            opacity: .7;
            cursor: progress;
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
                    <p class="subtitle">Essa página consome a API JSON em `/tests` e dispara novos testes via `/tests/run`.</p>
                </div>
                <div id="status-pill" class="status-pill __STATUS_CLASS__">__STATUS_LABEL__</div>
            </div>

            <div id="flash-message" class="message warning" style="display:none;"></div>

            <div class="controls">
                <button id="run-button" class="button primary" type="button">Rodar novos testes</button>
                <button id="reload-button" class="button secondary" type="button">Recarregar relatório</button>
            </div>
        </section>

        <section class="grid">
            <aside class="card summary">
                <h2 class="section-title">Resumo</h2>
                <div id="summary-content" class="muted">Carregando...</div>
            </aside>

            <article class="card content">
                <h2 class="section-title">Testes registrados</h2>
                <div id="tests-content" class="muted">Carregando...</div>
            </article>
        </section>
    </main>

    <script>
        const endpoints = {
            tests: '/tests',
            run: '/tests/run',
        };

        const state = {
            loading: false,
            data: null,
            lastError: null,
        };

        const el = {
            statusPill: document.getElementById('status-pill'),
            flashMessage: document.getElementById('flash-message'),
            summary: document.getElementById('summary-content'),
            tests: document.getElementById('tests-content'),
            runButton: document.getElementById('run-button'),
            reloadButton: document.getElementById('reload-button'),
        };

        const escapeHtml = (value) => String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');

        const setFlash = (message, type = 'warning') => {
            if (!message) {
                el.flashMessage.style.display = 'none';
                el.flashMessage.textContent = '';
                el.flashMessage.className = 'message warning';
                return;
            }

            el.flashMessage.style.display = 'block';
            el.flashMessage.className = `message ${type}`;
            el.flashMessage.innerHTML = message;
        };

        const setLoading = (value) => {
            state.loading = value;
            el.runButton.disabled = value;
            el.reloadButton.disabled = value;
            el.runButton.textContent = value ? 'Rodando...' : 'Rodar novos testes';
        };

        const fetchJson = async (url, options = {}) => {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    ...(options.headers || {}),
                },
                ...options,
            });

            const payload = await response.json().catch(() => null);

            if (!response.ok) {
                const error = new Error(`HTTP ${response.status}`);
                error.payload = payload;
                throw error;
            }

            return payload;
        };

        const render = (payload) => {
            state.data = payload;
            const report = payload?.report ?? null;
            const status = payload?.status ?? 'idle';
            const statusLabel = status === 'passed' ? 'Passou' : (status === 'failed' ? 'Falhou' : 'Sem relatório');

            el.statusPill.className = `status-pill ${status}`;
            el.statusPill.textContent = statusLabel;

            const summaryParts = [
                `<p><strong>Suite:</strong> ${escapeHtml(payload?.suite ?? 'smoke-tests-playground')}</p>`,
                `<p><strong>Geração:</strong> ${escapeHtml(payload?.generatedAt ?? 'indisponível')}</p>`,
                `<p><strong>Total:</strong> ${escapeHtml((report?.tests || []).length)} teste(s)</p>`,
                `<p><strong>Tests path:</strong><br>${escapeHtml(payload?.testsPath ?? '')}</p>`,
                `<p><strong>Report:</strong><br>${escapeHtml(payload?.reportPath ?? '')}</p>`,
                `<p><strong>Run command:</strong><br>${escapeHtml(payload?.runCommand ?? '')}</p>`,
            ];

            el.summary.innerHTML = summaryParts.join('');

            if (!report) {
                el.tests.innerHTML = '<p class="muted">Ainda não existe relatório publicado para este ambiente.</p>';
                return;
            }

            const rows = (report.tests || []).map((test) => {
                const passed = (test.status ?? 'failed') === 'passed';
                const badgeClass = passed ? 'pass' : 'fail';
                const label = passed ? 'Passou' : 'Falhou';
                const error = escapeHtml(test.error || 'Sem erro registrado');
                return `
                    <tr>
                        <td><strong>${escapeHtml(test.title ?? 'Teste sem título')}</strong></td>
                        <td><span class="badge ${badgeClass}">${label}</span></td>
                        <td>${error}</td>
                    </tr>
                `;
            }).join('');

            const shots = (report.tests || []).flatMap((test) => (test.screenshots || []).map((shot) => {
                if (!shot.src) {
                    return '';
                }

                return `
                    <div class="shot">
                        <img src="${escapeHtml(shot.src)}" alt="${escapeHtml(shot.label ?? 'Screenshot')}">
                        <div class="shot-meta">
                            <p class="shot-title">${escapeHtml(shot.label ?? 'Screenshot')}</p>
                            <div class="muted">${escapeHtml(shot.path ?? '')}</div>
                        </div>
                    </div>
                `;
            })).join('');

            el.tests.innerHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>Teste</th>
                            <th>Status</th>
                            <th>Erro</th>
                        </tr>
                    </thead>
                    <tbody>${rows || ''}</tbody>
                </table>
                ${shots ? `<h2 class="section-title" style="margin-top:18px;">Imagens</h2><div class="shots">${shots}</div>` : ''}
            `;
        };

        const loadReport = async () => {
            setLoading(true);
            setFlash('');

            try {
                const payload = await fetchJson(endpoints.tests);
                render(payload);
            } catch (error) {
                const payload = error.payload || null;
                if (payload) {
                    render(payload);
                }
                setFlash(`Falha ao carregar a API: ${escapeHtml(error.message)}`, 'failed');
            } finally {
                setLoading(false);
            }
        };

        const runTests = async () => {
            setLoading(true);
            setFlash('');

            try {
                const payload = await fetchJson(endpoints.run, {method: 'POST'});
                render(payload);
                setFlash(`Execução concluída com sucesso em ${escapeHtml(payload.runRequestedAt || '')}.`, 'success');
            } catch (error) {
                const payload = error.payload || null;
                if (payload) {
                    render(payload);
                }

                const message = payload?.run?.errorOutput || payload?.run?.output || error.message;
                setFlash(`Execução falhou: ${escapeHtml(message)}`, 'failed');
            } finally {
                setLoading(false);
            }
        };

        el.runButton.addEventListener('click', runTests);
        el.reloadButton.addEventListener('click', loadReport);

        loadReport();
    </script>
</body>
</html>
HTML;

        $html = strtr($html, [
            '__PAGE_TITLE__' => $this->e('Smoke Tests Playground'),
            '__STATUS_CLASS__' => $this->e('idle'),
            '__STATUS_LABEL__' => $this->e('Sem relatório'),
        ]);

        return new Response($html);
    }

    #[Route(path: '/tests/run', name: 'smoke_tests_playground_run', methods: ['POST'])]
    public function run(Request $request): JsonResponse
    {
        $runResult = $this->runner->run();
        $payload = $this->buildPayload();
        $payload['run'] = [
            'successful' => $runResult->successful,
            'exitCode' => $runResult->exitCode,
            'output' => $runResult->output,
            'errorOutput' => $runResult->errorOutput,
        ];
        $payload['runRequestedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $payload['requestedMethod'] = $request->getMethod();
        $payload['statusCode'] = $runResult->successful ? 200 : 500;

        return $this->json($payload, $runResult->successful ? 200 : 500);
    }

    private function buildPayload(): array
    {
        $report = $this->reportReader->readLatest();

        return [
            'status' => $report['status'] ?? 'idle',
            'generatedAt' => $report['generatedAt'] ?? null,
            'suite' => $report['suite'] ?? 'smoke-tests-playground',
            'testsPath' => $this->settings->testsPath(),
            'reportPath' => $this->settings->reportPath(),
            'runCommand' => $this->settings->runCommand(),
            'runWorkingDirectory' => $this->settings->runWorkingDirectory(),
            'runTimeout' => $this->settings->runTimeout(),
            'report' => $report,
        ];
    }
}
