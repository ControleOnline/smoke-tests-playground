<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Tests\Service;

use ControleOnline\SmokeTestsPlayground\Service\SmokeCommandResolver;
use ControleOnline\SmokeTestsPlayground\Service\SmokeRunner;
use ControleOnline\SmokeTestsPlayground\Service\SmokeTestsSettings;
use PHPUnit\Framework\TestCase;

final class SmokeRunnerTest extends TestCase
{
    /**
     * @var array<string, string|null>
     */
    private array $envBackup = [];

    public function testRunReturnsSuccessfulResultForANonShellCommand(): void
    {
        $scriptPath = tempnam(sys_get_temp_dir(), 'smoke-runner-');
        self::assertIsString($scriptPath);
        file_put_contents($scriptPath, "<?php echo 'runner-ok';\n");

        try {
            $this->setEnv('SMOKE_TESTS_PLAYGROUND_RUN_COMMAND', 'php '.$scriptPath);
            $this->setEnv('SMOKE_TESTS_PLAYGROUND_RUN_WORKDIR', sys_get_temp_dir());
            $this->setEnv('SMOKE_TESTS_PLAYGROUND_RUN_TIMEOUT', '30');

            $runner = new SmokeRunner(
                new SmokeTestsSettings(sys_get_temp_dir()),
                new SmokeCommandResolver(),
            );

            $result = $runner->run();

            self::assertTrue($result->successful);
            self::assertSame(0, $result->exitCode);
            self::assertStringContainsString('runner-ok', $result->output);
            self::assertSame('', $result->errorOutput);
        } finally {
            @unlink($scriptPath);
            $this->restoreEnv();
        }
    }

    private function setEnv(string $key, string $value): void
    {
        if (!array_key_exists($key, $this->envBackup)) {
            $this->envBackup[$key] = $_ENV[$key] ?? null;
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key.'='.$value);
    }

    private function restoreEnv(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);

                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }

        $this->envBackup = [];
    }
}
