<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

interface SmokeRemoteIndexReaderInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function readSuites(): array;
}
