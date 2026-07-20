<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

use Symfony\Component\HttpFoundation\Response;

interface SmokeRemoteArtifactReaderInterface
{
    public function create(string $suiteId, string $artifactPath): ?Response;
}
