<?php

declare(strict_types=1);

namespace ControleOnline\SmokeTestsPlayground\Service;

final class SmokeFlowchartMetadata
{
    public const ADMIN_FLOWCHART_BASE = 'https://admin.controleonline.com/admin/flowcharts';

    /**
     * @param array<string, mixed> $source
     *
     * @return array{flowchartIds: list<int>, flowchartLinks: list<string>, flowKey: ?string}
     */
    public function normalize(array $source): array
    {
        $ids = $this->extractIds($source);
        $flowKey = $this->extractFlowKey($source);

        return [
            'flowchartIds' => $ids,
            'flowchartLinks' => $this->buildLinks($ids),
            'flowKey' => $flowKey,
        ];
    }

    /**
     * @param array<string, mixed> $suite
     *
     * @return array<string, mixed>
     */
    public function enrichSuite(array $suite): array
    {
        $metadata = $this->normalize($suite);
        $suite['flowchartIds'] = $metadata['flowchartIds'];
        $suite['flowchartLinks'] = $metadata['flowchartLinks'];
        $suite['flowKey'] = $metadata['flowKey'];
        $suite['tests'] = $this->enrichTests($suite['tests'] ?? [], $metadata);

        return $suite;
    }

    /**
     * @param list<array<string, mixed>> $suites
     *
     * @return list<array<string, mixed>>
     */
    public function groupSuites(array $suites): array
    {
        $grouped = [];

        foreach ($suites as $suite) {
            $ids = $suite['flowchartIds'] ?? [];
            if (!is_array($ids) || $ids === []) {
                continue;
            }

            foreach ($ids as $id) {
                if (!is_int($id)) {
                    continue;
                }

                if (!isset($grouped[$id])) {
                    $grouped[$id] = [
                        'id' => $id,
                        'link' => $this->buildLink($id),
                        'flowKeys' => [],
                        'suites' => [],
                    ];
                }

                $grouped[$id]['suites'][] = $suite;
                $flowKey = $suite['flowKey'] ?? null;
                if (is_string($flowKey) && $flowKey !== '' && !in_array($flowKey, $grouped[$id]['flowKeys'], true)) {
                    $grouped[$id]['flowKeys'][] = $flowKey;
                }
            }
        }

        ksort($grouped, SORT_NUMERIC);

        return array_values($grouped);
    }

    /**
     * @param list<mixed> $tests
     * @param array{flowchartIds: list<int>, flowchartLinks: list<string>, flowKey: ?string} $suiteMetadata
     *
     * @return list<array<string, mixed>>
     */
    private function enrichTests(array $tests, array $suiteMetadata): array
    {
        $enriched = [];

        foreach ($tests as $test) {
            if (!is_array($test)) {
                continue;
            }

            $hasOwnIds = $this->extractIds($test) !== [];
            $metadata = $hasOwnIds ? $this->normalize($test) : $suiteMetadata;
            $test['flowchartIds'] = $metadata['flowchartIds'];
            $test['flowchartLinks'] = $metadata['flowchartLinks'];
            $test['flowKey'] = $this->extractFlowKey($test) ?? $metadata['flowKey'];
            $enriched[] = $test;
        }

        return $enriched;
    }

    /**
     * @param array<string, mixed> $source
     *
     * @return list<int>
     */
    private function extractIds(array $source): array
    {
        $ids = [];

        foreach (['flowchartIds', 'flowchart_ids'] as $key) {
            $ids = array_merge($ids, $this->idsFromValue($source[$key] ?? null));
        }

        $ids = array_merge($ids, $this->idsFromValue($source['flowchartId'] ?? $source['flowchart_id'] ?? null));

        $flowchart = $source['flowchart'] ?? null;
        if (is_array($flowchart)) {
            $ids = array_merge($ids, $this->idsFromValue($flowchart['id'] ?? $flowchart['ids'] ?? null));
        }

        $ids = array_merge($ids, $this->idsFromLinks($source['flowchartLinks'] ?? $source['flowchart_links'] ?? null));

        $unique = [];
        foreach ($ids as $id) {
            $unique[$id] = $id;
        }

        return array_values($unique);
    }

    /**
     * @return list<int>
     */
    private function idsFromValue(mixed $value): array
    {
        if (is_int($value) || is_float($value) || is_string($value)) {
            $id = $this->positiveInt($value);

            return $id === null ? [] : [$id];
        }

        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $ids = array_merge($ids, $this->idsFromValue($item['id'] ?? null));
                continue;
            }

            $id = $this->positiveInt($item);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function idsFromLinks(mixed $value): array
    {
        if (!is_array($value)) {
            return is_string($value) ? $this->idsFromLinks([$value]) : [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            if (preg_match('#/admin/flowcharts/(\d+)#', $item, $matches) !== 1) {
                continue;
            }

            $id = $this->positiveInt($matches[1]);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function extractFlowKey(array $source): ?string
    {
        foreach (['flowKey', 'flow_key', 'flowchartKey'] as $key) {
            $value = $source[$key] ?? null;
            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<string>
     */
    private function buildLinks(array $ids): array
    {
        return array_values(array_map(fn (int $id): string => $this->buildLink($id), $ids));
    }

    public function buildLink(int $id): string
    {
        return self::ADMIN_FLOWCHART_BASE.'/'.$id;
    }

    private function positiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_float($value) && $value > 0 && floor($value) === $value) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            $id = (int) trim($value);

            return $id > 0 ? $id : null;
        }

        return null;
    }
}
