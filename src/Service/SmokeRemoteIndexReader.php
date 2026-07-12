<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SmokeRemoteIndexReader implements SmokeRemoteIndexReaderInterface
{
    public function __construct(
        private readonly SmokeRemoteSourceResolver $sourceResolver,
        private readonly HttpClientInterface $httpClient,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function readSuites(): array
    {
        $url = $this->sourceResolver->remoteIndexUrl();
        if ($url === null) {
            $this->logger?->warning('Smoke tests remote index skipped because no remote base URL could be resolved.');

            return [];
        }

        $this->logger?->info('Fetching smoke tests remote index.', [
            'url' => $url,
        ]);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'verify_peer' => false,
                'verify_host' => false,
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                $this->logger?->warning('Smoke tests remote index returned a non-success HTTP status.', [
                    'url' => $url,
                    'status_code' => $response->getStatusCode(),
                ]);

                return [];
            }

            $payload = json_decode($response->getContent(false), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            $this->logger?->warning('Smoke tests remote index fetch failed.', [
                'url' => $url,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        if (!is_array($payload) || !is_array($payload['suites'] ?? null)) {
            $this->logger?->warning('Smoke tests remote index payload is invalid.', [
                'url' => $url,
            ]);

            return [];
        }

        $suites = array_values(array_filter(
            $payload['suites'],
            static fn (mixed $suite): bool => is_array($suite),
        ));

        $this->logger?->info('Smoke tests remote index loaded.', [
            'url' => $url,
            'suites' => count($suites),
        ]);

        return $suites;
    }
}
