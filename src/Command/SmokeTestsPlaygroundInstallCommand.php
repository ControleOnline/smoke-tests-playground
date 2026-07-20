<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Command;

use ControleOnline\SmokeTestsPlayground\Service\SmokeBrowserInstallerInterface;
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
        private readonly SmokeBrowserInstallerInterface $browserInstaller,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectDir = rtrim($this->kernel->getProjectDir(), '/\\');

        $this->writeEnvLocal($projectDir.'/.env');
        $this->writeRoutesConfig($projectDir.'/config/routes/smoke_tests_playground.yaml');
        $this->writeServicesConfig($projectDir.'/config/services/smoke_tests_playground.yaml');

        try {
            $this->browserInstaller->install();
            $output->writeln('<info>Browsers do Playwright instalados no projeto consumidor.</info>');
        } catch (\Throwable $exception) {
            $output->writeln('<comment>Não foi possível instalar os browsers automaticamente.</comment>');
            $output->writeln('<comment>Mensagem:</comment> '.$exception->getMessage());
            $this->writeRootInstructions($output, $projectDir);

            return Command::FAILURE;
        }

        $output->writeln('<info>Smoke Tests Playground configurado.</info>');
        $output->writeln('Reinicie o cache se necessário: <comment>php bin/console cache:clear</comment>');

        return Command::SUCCESS;
    }

    private function writeEnvLocal(string $path): void
    {
        $header = [
            '# Smoke Tests Playground',
            '# Configuração padrão instalada pelo pacote controleonline/smoke-tests-playground.',
            '# O playground expõe JSON em /tests, com /tests/index.json mantido como alias, e os prints ficam em /tests/artifacts.',
            '# Antes de usar o runner, o projeto consumidor precisa instalar o Playwright localmente e gerar os browsers com o mesmo usuário que executa o app.',
        ];

        $lines = array_merge($header, $this->settings->defaultEnvLines(), ['']);
        $this->writeEnvLines($path, $lines);
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

    private function writeRootInstructions(OutputInterface $output, string $projectDir): void
    {
        $output->writeln('');
        $output->writeln('<comment>Comandos para executar como root, se houver problema de permissão ou cache de browser:</comment>');
        $output->writeln(sprintf('<comment>chown -R staging:staging %s</comment>', escapeshellarg($projectDir)));
        $output->writeln(sprintf(
            '<comment>su - staging -c %s</comment>',
            escapeshellarg('cd '.$projectDir.' && source ~/.bashrc && nvm use --lts && npm run test:browser:install'),
        ));
        $output->writeln(sprintf(
            '<comment>su - staging -c %s</comment>',
            escapeshellarg('cd '.$projectDir.' && php bin/console smoke-tests-playground:install'),
        ));
    }

    private function writeEnvLines(string $path, array $lines): void
    {
        $existing = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
        $existing = $existing === false ? [] : $existing;
        $newValues = [];
        foreach ($lines as $line) {
            if (preg_match('/^([A-Z0-9_]+)=/', $line, $matches) === 1) {
                $newValues[$matches[1]] = $line;
            }
        }

        $merged = [];
        $seenKeys = [];

        foreach ($existing as $line) {
            if (preg_match('/^([A-Z0-9_]+)=/', $line, $matches) === 1) {
                $key = $matches[1];
                if (isset($newValues[$key])) {
                    if (!isset($seenKeys[$key])) {
                        $merged[] = $newValues[$key];
                        $seenKeys[$key] = true;
                    }
                    continue;
                }
            }

            $merged[] = $line;
        }

        foreach ($lines as $line) {
            if (preg_match('/^([A-Z0-9_]+)=/', $line, $matches) !== 1) {
                if (!in_array($line, $merged, true)) {
                    $merged[] = $line;
                }

                continue;
            }

            $key = $matches[1];
            if (!isset($seenKeys[$key])) {
                $merged[] = $line;
                $seenKeys[$key] = true;
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
