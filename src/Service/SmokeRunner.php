<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

use Symfony\Component\Process\Process;

final class SmokeRunner
{
    public function __construct(
        private readonly SmokeTestsSettings $settings,
    ) {
    }

    public function run(): SmokeRunResult
    {
        $process = Process::fromShellCommandline(
            $this->settings->runCommand(),
            $this->settings->runWorkingDirectory(),
            null,
            null,
            $this->settings->runTimeout(),
        );

        $process->run();

        return new SmokeRunResult(
            $process->isSuccessful(),
            $process->getExitCode() ?? 1,
            $process->getOutput(),
            $process->getErrorOutput(),
        );
    }
}
