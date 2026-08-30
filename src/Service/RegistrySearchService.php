<?php

namespace FlexiCore\Service;

class RegistrySearchService
{
    /**
     * Search for components across registries or in a specific one
     */
    public function search(
        string $query,
        array $components,
        array $filters = []
    ): array
    {
        $results = [];
        $query = strtolower(trim($query));

        if (empty($query)) {
            return ['success' => false, 'message' => 'Search query cannot be empty'];
        }

        foreach ($components as $component) {
            if ($this->matches($component, $query, $filters)) {
                $relevance = $this->calculateRelevance($component, $query);
                $results[] = [
                    'component' => $component,
                    'relevance' => $relevance,
                ];
            }
        }

        // Sort by relevance
        usort($results, fn($a, $b) => $b['relevance'] <=> $a['relevance']);

        return [
            'success' => true,
            'query' => $query,
            'count' => count($results),
            'results' => array_map(fn($r) => $r['component'], $results),
        ];
    }

    /**
     * Check if component matches the search query and filters
     */
    private function matches(array $component, string $query, array $filters = []): bool
    {
        // Check query match
        if (!$this->matchesQuery($component, $query)) {
            return false;
        }

        // Check filters
        if (!empty($filters['type']) && ($component['type'] ?? '') !== $filters['type']) {
            return false;
        }

        if (!empty($filters['version'])) {
            if (!version_compare($component['version'] ?? '0.0.0', $filters['version'], '>=')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if query matches component
     */
    private function matchesQuery(array $component, string $query): bool
    {
        $name = strtolower($component['name'] ?? '');
        $description = strtolower($component['description'] ?? '');
        $title = strtolower($component['title'] ?? '');

        // Exact name match is highest priority
        if ($name === $query) {
            return true;
        }

        // Prefix match
        if (str_starts_with($name, $query)) {
            return true;
        }

        // Substring match
        if (str_contains($name, $query)) {
            return true;
        }

        // Description match
        if (str_contains($description, $query)) {
            return true;
        }

        // Title match
        if (str_contains($title, $query)) {
            return true;
        }

        return false;
    }

    /**
     * Calculate relevance score for ranking results
     */
    private function calculateRelevance(array $component, string $query): int
    {
        $score = 0;
        $name = strtolower($component['name'] ?? '');
        $description = strtolower($component['description'] ?? '');
        $title = strtolower($component['title'] ?? '');

        // Exact match
        if ($name === $query) {
            $score += 100;
        }

        // Starts with query
        if (str_starts_with($name, $query)) {
            $score += 50;
        }

        // Contains in name
        if (str_contains($name, $query)) {
            $score += 30;
        }

        // Contains in title
        if (str_contains($title, $query)) {
            $score += 20;
        }

        // Contains in description
        if (str_contains($description, $query)) {
            $score += 10;
        }

        return $score;
    }

    /**
     * Format search results for display
     */
    public function formatResults(array $results, string $query): string
    {
        if (empty($results)) {
            return "No components found for: {$query}\n";
        }

        $lines = [];
        $lines[] = "Search Results for: {$query}";
        $lines[] = str_repeat('-', 80);
        $lines[] = '';

        foreach ($results as $comp) {
            $name = $comp['name'] ?? '';
            $version = $comp['version'] ?? '0.0.0';
            $type = $comp['type'] ?? '';
            $desc = substr($comp['description'] ?? '', 0, 50);

            $lines[] = "* {$name}@{$version} ({$type})";
            if ($desc) {
                $lines[] = "  {$desc}";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Group search results by registry
     */
    public function groupByRegistry(array $results, array $registryMap = []): array
    {
        $grouped = [];

        foreach ($results as $component) {
            $registry = $registryMap[$component['name']] ?? 'unknown';

            if (!isset($grouped[$registry])) {
                $grouped[$registry] = [];
            }

            $grouped[$registry][] = $component;
        }

        return $grouped;
    }

    /**
     * Format grouped results
     */
    public function formatGroupedResults(array $grouped, string $query): string
    {
        if (empty($grouped)) {
            return "No components found for: {$query}\n";
        }

        $lines = [];
        $lines[] = "Search Results for: {$query}";
        $lines[] = '';

        foreach ($grouped as $registry => $components) {
            $lines[] = "Matches in {$registry}:";

            foreach ($components as $comp) {
                $name = $comp['name'] ?? '';
                $version = $comp['version'] ?? '0.0.0';
                $type = $comp['type'] ?? '';
                $desc = substr($comp['description'] ?? '', 0, 40);

                $lines[] = "  * {$name}@{$version} ({$type})";
                if ($desc) {
                    $lines[] = "    {$desc}";
                }
            }

            $lines[] = '';
        }

        $total = array_sum(array_map('count', $grouped));
        $lines[] = "Total: {$total} match" . ($total !== 1 ? 'es' : '');

        return implode("\n", $lines);
    }
}
