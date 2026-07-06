<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Tests\Command;

use ControleOnline\SmokeTestsPlayground\Command\SmokeTestsPlaygroundInstallCommand;
use ControleOnline\SmokeTestsPlayground\Service\SmokeBrowserInstallerInterface;
use ControleOnline\SmokeTestsPlayground\Service\SmokeTestsSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class SmokeTestsPlaygroundInstallCommandTest extends TestCase
{
    public function testExecuteInstallsConfigAndBrowsers(): void
    {
        $projectDir = $this->makeProjectDir();
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        $installer = $this->createMock(SmokeBrowserInstallerInterface::class);
        $installer->expects(self::once())->method('install');

        $command = new SmokeTestsPlaygroundInstallCommand(
            $kernel,
            new SmokeTestsSettings($projectDir),
            $installer,
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertFileExists($projectDir.'/.env');
        self::assertFileExists($projectDir.'/config/routes/smoke_tests_playground.yaml');
        self::assertFileExists($projectDir.'/config/services/smoke_tests_playground.yaml');
        self::assertStringContainsString('SMOKE_TESTS_PLAYGROUND_TESTS_PATH="var/tests/browser-smoke"', file_get_contents($projectDir.'/.env'));
        self::assertStringContainsString('tests/browser/*.spec.js', file_get_contents($projectDir.'/.env'));
        self::assertStringContainsString('Browsers do Playwright instalados', $tester->getDisplay());
    }

    public function testExecutePrintsRootInstructionsWhenBrowserInstallFails(): void
    {
        $projectDir = $this->makeProjectDir();
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        $installer = $this->createMock(SmokeBrowserInstallerInterface::class);
        $installer->method('install')->willThrowException(new \RuntimeException('permissão negada'));

        $command = new SmokeTestsPlaygroundInstallCommand(
            $kernel,
            new SmokeTestsSettings($projectDir),
            $installer,
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Não foi possível instalar os browsers automaticamente.', $tester->getDisplay());
        self::assertStringContainsString('Comandos para executar como root', $tester->getDisplay());
        self::assertStringContainsString('chown -R staging:staging', $tester->getDisplay());
    }

    private function makeProjectDir(): string
    {
        $projectDir = sys_get_temp_dir().'/smoke-install-'.bin2hex(random_bytes(6));
        mkdir($projectDir, 0777, true);

        return $projectDir;
    }
}
