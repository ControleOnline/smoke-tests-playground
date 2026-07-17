<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

use Symfony\Component\Process\Process;

final class SmokeBrowserInstaller implements SmokeBrowserInstallerInterface
{
    public function __construct(
        private readonly SmokeTestsSettings $settings,
        private readonly SmokeCommandResolver $commandResolver,
    ) {
    }

    public function install(): void
    {
        $process = new Process(
            $this->commandResolver->toProcessArguments(
                'node node_modules/@playwright/test/cli.js install --force chromium',
            ),
            $this->settings->runWorkingDirectory(),
            [
                'PLAYWRIGHT_BROWSERS_PATH' => '0',
            ],
        );

        $process->setTimeout($this->settings->runTimeout());
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()."\n".$process->getOutput()));
        }
    }
}
