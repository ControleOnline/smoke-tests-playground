<?php

namespace ControleOnline\SmokeTestsPlayground\Tests\Service;

use PHPUnit\Framework\TestCase;

class SymfonyConstraintTest extends TestCase
{
    public function testSymfonyPackagesAllow74And81(): void
    {
        $composerFile = dirname(__DIR__, 2) . '/composer.json';
        $this->assertFileExists($composerFile);

        $composer = json_decode((string) file_get_contents($composerFile), true);
        $this->assertIsArray($composer);
        $this->assertArrayHasKey('require', $composer);

        $packages = [
            'symfony/config',
            'symfony/console',
            'symfony/dependency-injection',
            'symfony/framework-bundle',
            'symfony/process',
            'symfony/yaml',
        ];

        foreach ($packages as $package) {
            $this->assertArrayHasKey($package, $composer['require']);
            $constraint = $composer['require'][$package];
            $this->assertStringContainsString('7.4', $constraint, $package);
            $this->assertStringContainsString('8.1', $constraint, $package);
        }
    }
}
