<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SmokeRemoteIndexReader implements SmokeRemoteIndexReaderInterface
{
    public function __construct(
        private readonly SmokeRemoteSourceResolver $sourceResolver,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function readSuites(): array
    {
        $url = $this->sourceResolver->remoteIndexUrl();
        if ($url === null) {
            return [];
        }

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
                return [];
            }

            $payload = json_decode($response->getContent(false), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($payload) || !is_array($payload['suites'] ?? null)) {
            return [];
        }

        return array_values(array_filter(
            $payload['suites'],
            static fn (mixed $suite): bool => is_array($suite),
        ));
    }
}
