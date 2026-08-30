<?php

namespace FlexiCore\Service;

use FlexiCore\Core\RegistryVersionResolver;

class RegistryListService
{
    public function __construct(
        private RegistryVersionResolver $versionResolver = new RegistryVersionResolver()
    ) {}

    /**
     * List all components from a registry
     */
    public function listComponents(
        string $registry,
        array $filters = [],
        array $headers = [],
        array $params = []
    ): array
    {
        $components = $this->fetchRegistry($registry, $headers, $params);

        if (empty($components)) {
            return [
                'success' => false,
                'message' => "No components found in registry: {$registry}",
                'components' => [],
            ];
        }

        // Apply filters
        if (!empty($filters['type'])) {
            $components = $this->filterByType($components, $filters['type']);
        }

        if (!empty($filters['search'])) {
            $components = $this->filterBySearch($components, $filters['search']);
        }

        // Sort
        $sortBy = $filters['sort'] ?? 'name';
        $components = match ($sortBy) {
            'version' => $this->sortByVersion($components),
            'type' => $this->sortByType($components),
            default => $this->sortByName($components),
        };

        return [
            'success' => true,
            'registry' => $registry,
            'count' => count($components),
            'components' => $components,
        ];
    }

    /**
     * Get a single component details
     */
    public function getComponent(
        string $registry,
        string $componentName,
        array $headers = [],
        array $params = []
    ): ?array
    {
        $components = $this->fetchRegistry($registry, $headers, $params);

        foreach ($components as $component) {
            if (($component['name'] ?? null) === $componentName) {
                return $component;
            }
        }

        return null;
    }

    /**
     * Filter components by type
     */
    public function filterByType(array $components, string $type): array
    {
        return array_filter($components, fn($c) => ($c['type'] ?? null) === $type);
    }

    /**
     * Filter components by name search
     */
    public function filterBySearch(array $components, string $search): array
    {
        $search = strtolower($search);

        return array_filter($components, function ($component) use ($search) {
            $name = strtolower($component['name'] ?? '');
            $description = strtolower($component['description'] ?? '');
            $title = strtolower($component['title'] ?? '');

            return str_contains($name, $search)
                || str_contains($description, $search)
                || str_contains($title, $search);
        });
    }

    /**
     * Sort components by name
     */
    public function sortByName(array $components): array
    {
        usort($components, fn($a, $b) => strcmp(
            $a['name'] ?? '',
            $b['name'] ?? ''
        ));

        return $components;
    }

    /**
     * Sort components by version
     */
    public function sortByVersion(array $components): array
    {
        usort($components, fn($a, $b) => version_compare(
            $b['version'] ?? '0.0.0',
            $a['version'] ?? '0.0.0'
        ));

        return $components;
    }

    /**
     * Sort components by type
     */
    public function sortByType(array $components): array
    {
        usort($components, fn($a, $b) => strcmp(
            $a['type'] ?? '',
            $b['type'] ?? ''
        ));

        return $components;
    }

    /**
     * Get component statistics
     */
    public function getStatistics(array $components): array
    {
        $stats = [
            'total' => count($components),
            'by_type' => [],
        ];

        foreach ($components as $component) {
            $type = $component['type'] ?? 'unknown';
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Format components for display
     */
    public function formatForDisplay(array $components, array $options = []): string
    {
        if (empty($components)) {
            return "No components found.\n";
        }

        $output = [];
        $showFiles = $options['show_files'] ?? false;
        $showDeps = $options['show_deps'] ?? false;

        $output[] = $this->formatTable($components);

        if ($showDeps) {
            $output[] = $this->formatDependencies($components);
        }

        if ($showFiles) {
            $output[] = $this->formatFiles($components);
        }

        return implode("\n\n", $output);
    }

    /**
     * Format components as table
     */
    private function formatTable(array $components): string
    {
        $lines = [];
        $lines[] = "Name                 Version    Type              Description";
        $lines[] = str_repeat("─", 80);

        foreach ($components as $comp) {
            $name = substr($comp['name'] ?? '', 0, 20);
            $version = substr($comp['version'] ?? '0.0.0', 0, 10);
            $type = substr($comp['type'] ?? '', 0, 17);
            $desc = substr($comp['description'] ?? '', 0, 25);

            $line = sprintf(
                "%-20s %-10s %-17s %s",
                $name,
                $version,
                $type,
                $desc
            );
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Format dependencies section
     */
    private function formatDependencies(array $components): string
    {
        $lines = [];

        foreach ($components as $comp) {
            $deps = $comp['dependencies'] ?? [];
            if (empty($deps)) {
                continue;
            }

            $lines[] = $comp['name'] . ':';

            foreach ($deps as $type => $packages) {
                if (empty($packages)) {
                    continue;
                }
                $lines[] = "  $type:";
                foreach ($packages as $pkg) {
                    $lines[] = "    • $pkg";
                }
            }
        }

        return !empty($lines) ? implode("\n", $lines) : "No dependencies";
    }

    /**
     * Format files section
     */
    private function formatFiles(array $components): string
    {
        $lines = [];

        foreach ($components as $comp) {
            $files = $comp['files'] ?? [];
            if (empty($files)) {
                continue;
            }

            $lines[] = $comp['name'] . ' (' . count($files) . ' files):';
            foreach ($files as $file) {
                $path = $file['path'] ?? '';
                $target = $file['target'] ?? '';
                $lines[] = "  ✓ $path → $target";
            }
        }

        return !empty($lines) ? implode("\n", $lines) : "No files";
    }

    /**
     * Fetch registry components
     */
    private function fetchRegistry(
        string $registry,
        array $headers = [],
        array $params = []
    ): array
    {
        // This would normally fetch from a registry URL
        // For now, return empty - will be implemented with actual registry fetching
        return [];
    }
}
