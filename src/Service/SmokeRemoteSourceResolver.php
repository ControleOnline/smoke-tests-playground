<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

use ControleOnline\Service\DomainService;

final class SmokeRemoteSourceResolver
{
    public function __construct(
        private readonly ?DomainService $domainService = null,
    ) {
    }

    public function remoteBaseUrl(): ?string
    {
        if (!$this->domainService instanceof DomainService) {
            return null;
        }

        $domain = $this->normalizeHost($this->domainService->getDomain());
        $mainDomain = $this->normalizeHost($this->domainService->getMainDomain());

        if ($domain === '' || $domain === $mainDomain) {
            return null;
        }

        return 'https://'.$domain;
    }

    public function remoteIndexUrl(): ?string
    {
        $baseUrl = $this->remoteBaseUrl();

        if ($baseUrl === null) {
            return null;
        }

        return $baseUrl.'/tests/index.json';
    }

    public function remoteArtifactUrl(string $suiteId, string $artifactPath): ?string
    {
        $baseUrl = $this->remoteBaseUrl();

        if ($baseUrl === null) {
            return null;
        }

        $segments = array_filter(
            explode('/', str_replace('\\', '/', trim($artifactPath))),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..',
        );

        return $baseUrl.'/tests/artifacts/'.implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            array_merge([$suiteId], $segments),
        ));
    }

    private function normalizeHost(string $host): string
    {
        $host = trim($host);

        if ($host === '') {
            return '';
        }

        $host = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $host) ?? $host;
        $separatorPosition = strcspn($host, '/?#');
        if ($separatorPosition < strlen($host)) {
            $host = substr($host, 0, $separatorPosition);
        }

        return strtolower(trim($host));
    }
}
