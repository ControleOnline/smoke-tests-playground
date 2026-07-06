<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Command;

use ControleOnline\SmokeTestsPlayground\Service\SmokeTestsSettings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'smoke-tests-playground:install',
    description: 'Instala os arquivos de configuração padrão do Smoke Tests Playground no projeto atual.',
)]
final class SmokeTestsPlaygroundInstallCommand extends Command
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly SmokeTestsSettings $settings,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectDir = rtrim($this->kernel->getProjectDir(), '/\\');

        $this->writeEnvLocal($projectDir.'/.env.local');
        $this->writeRoutesConfig($projectDir.'/config/routes/smoke_tests_playground.yaml');
        $this->writeServicesConfig($projectDir.'/config/services/smoke_tests_playground.yaml');

        $output->writeln('<info>Smoke Tests Playground configurado.</info>');
        $output->writeln('Reinicie o cache se necessário: <comment>php bin/console cache:clear</comment>');

        return Command::SUCCESS;
    }

    private function writeEnvLocal(string $path): void
    {
        $header = [
            '# Smoke Tests Playground',
            '# Configuração padrão instalada pelo pacote controleonline/smoke-tests-playground.',
        ];

        $lines = array_merge($header, $this->settings->defaultEnvLines(), ['']);
        $this->appendUniqueLines($path, $lines);
    }

    private function writeRoutesConfig(string $path): void
    {
        $content = <<<YAML
smoke_tests_playground:
    resource: '../../vendor/controleonline/smoke-tests-playground/src/Controller/'
    type: attribute

YAML;

        $this->writeFileIfNeeded($path, $content);
    }

    private function writeServicesConfig(string $path): void
    {
        $content = <<<YAML
services:
    ControleOnline\\SmokeTestsPlayground\\:
        resource: '%kernel.project_dir%/vendor/controleonline/smoke-tests-playground/src/'
        exclude: '%kernel.project_dir%/vendor/controleonline/smoke-tests-playground/src/{DependencyInjection,Resources,SmokeTestsPlaygroundBundle.php}'

YAML;

        $this->writeFileIfNeeded($path, $content);
    }

    private function appendUniqueLines(string $path, array $lines): void
    {
        $existing = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
        $existing = $existing === false ? [] : $existing;
        $existingMap = array_fill_keys($existing, true);

        $merged = $existing;
        foreach ($lines as $line) {
            if (!isset($existingMap[$line])) {
                $merged[] = $line;
                $existingMap[$line] = true;
            }
        }

        $this->writeFileIfNeeded($path, implode(PHP_EOL, $merged).PHP_EOL);
    }

    private function writeFileIfNeeded(string $path, string $content): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (!is_file($path) || file_get_contents($path) !== $content) {
            file_put_contents($path, $content);
        }
    }
}
