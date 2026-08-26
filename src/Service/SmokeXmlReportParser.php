<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeXmlReportParser
{
    public function __construct(
        private readonly SmokeSuitePathCodec $suitePathCodec,
    ) {
    }

    /**
     * @return array{valid: bool, suite?: string, displayName?: string, generatedAt?: ?string, tests?: list<array<string, mixed>>}|null
     */
    public function parse(string $reportPath, string $fallbackSuite, ?string $updatedAt): ?array
    {
        $previousUseInternalErrors = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_file($reportPath, \SimpleXMLElement::class, LIBXML_NOCDATA);
        } finally {
            libxml_use_internal_errors($previousUseInternalErrors);
        }

        if (!$xml instanceof \SimpleXMLElement) {
            return ['valid' => false];
        }

        $rootName = $xml->getName();
        if ($rootName !== 'testsuite' && $rootName !== 'testsuites') {
            return null;
        }

        $suite = $this->extractSuiteName($xml, $fallbackSuite);

        return [
            'valid' => true,
            'suite' => $suite,
            'displayName' => $this->suitePathCodec->humanizeLabel($suite),
            'generatedAt' => $this->extractGeneratedAt($xml, $updatedAt),
            'tests' => $this->normalizeTests($xml),
        ];
    }

    private function extractSuiteName(\SimpleXMLElement $xml, string $fallback): string
    {
        $rootName = trim((string) ($xml['name'] ?? ''));
        if ($rootName !== '') {
            return $rootName;
        }

        foreach ($xml->testsuite as $suiteNode) {
            $childName = trim((string) ($suiteNode['name'] ?? ''));
            if ($childName !== '') {
                return $childName;
            }
        }

        return $fallback;
    }

    private function extractGeneratedAt(\SimpleXMLElement $xml, ?string $updatedAt): ?string
    {
        $candidates = [
            trim((string) ($xml['timestamp'] ?? '')),
        ];

        foreach ($xml->testsuite as $suiteNode) {
            $candidates[] = trim((string) ($suiteNode['timestamp'] ?? ''));
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return $updatedAt;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeTests(\SimpleXMLElement $xml): array
    {
        $tests = [];

        if ($xml->getName() === 'testsuites') {
            foreach ($xml->testsuite as $suiteNode) {
                $tests = array_merge($tests, $this->normalizeTests($suiteNode));
            }

            return $tests;
        }

        foreach ($xml->testcase as $testcase) {
            $tests[] = $this->normalizeTestCase($testcase);
        }

        foreach ($xml->testsuite as $suiteNode) {
            $tests = array_merge($tests, $this->normalizeTests($suiteNode));
        }

        return $tests;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeTestCase(\SimpleXMLElement $testcase): array
    {
        $error = $this->extractFailureMessage($testcase);

        return [
            'title' => $this->buildTestTitle($testcase),
            'status' => $error === null ? 'passed' : 'failed',
            'error' => $error,
            'screenshots' => [],
            'steps' => [],
        ];
    }

    private function buildTestTitle(\SimpleXMLElement $testcase): string
    {
        $classname = trim((string) ($testcase['classname'] ?? ''));
        $name = trim((string) ($testcase['name'] ?? ''));

        if ($classname === '' && $name === '') {
            return 'Teste sem nome';
        }

        if ($classname === '') {
            return $name;
        }

        if ($name === '') {
            return $classname;
        }

        return $classname.'::'.$name;
    }

    private function extractFailureMessage(\SimpleXMLElement $testcase): ?string
    {
        foreach (['failure', 'error'] as $nodeName) {
            if (!isset($testcase->{$nodeName})) {
                continue;
            }

            foreach ($testcase->{$nodeName} as $failureNode) {
                $message = trim((string) ($failureNode['message'] ?? ''));
                $body = trim((string) $failureNode);

                if ($message !== '') {
                    return $message;
                }

                if ($body !== '') {
                    return $body;
                }
            }
        }

        return null;
    }
}
