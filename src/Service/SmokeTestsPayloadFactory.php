<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeTestsPayloadFactory
{
    public function __construct(
        private readonly SmokeReportReader $reportReader,
        private readonly SmokeTestsSettings $settings,
    ) {
    }

    public function create(): array
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

    public function createRunPayload(
        SmokeRunResult $runResult,
        string $requestedMethod,
        string $runRequestedAt,
    ): array {
        $payload = $this->create();
        $payload['run'] = [
            'successful' => $runResult->successful,
            'exitCode' => $runResult->exitCode,
            'output' => $runResult->output,
            'errorOutput' => $runResult->errorOutput,
        ];
        $payload['runRequestedAt'] = $runRequestedAt;
        $payload['requestedMethod'] = $requestedMethod;
        $payload['statusCode'] = $runResult->successful ? 200 : 500;

        return $payload;
    }
}
