<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeRunResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly int $exitCode,
        public readonly string $output,
        public readonly string $errorOutput,
    ) {
    }
}
