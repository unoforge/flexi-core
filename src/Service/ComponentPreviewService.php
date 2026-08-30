<?php

namespace FlexiCore\Service;

class ComponentPreviewService
{
    /**
     * Generate preview of a component
     */
    public function preview(array $component, array $options = []): array
    {
        return [
            'name' => $component['name'] ?? 'Unknown',
            'version' => $component['version'] ?? '0.0.0',
            'type' => $component['type'] ?? 'unknown',
            'title' => $component['title'] ?? '',
            'description' => $component['description'] ?? '',
            'files' => $this->getFilesPreview($component['files'] ?? [], $options),
            'css_vars' => $this->getCssVarsPreview($component['cssVars'] ?? []),
            'css_rules' => $this->getCssRulesPreview($component['css'] ?? []),
            'dependencies' => $component['dependencies'] ?? [],
            'dev_dependencies' => $component['devDependencies'] ?? [],
            'registry_dependencies' => $component['registryDependencies'] ?? [],
        ];
    }

    /**
     * Get files preview
     */
    private function getFilesPreview(array $files, array $options = []): array
    {
        $preview = [];

        foreach ($files as $file) {
            $path = $file['path'] ?? '';
            $target = $file['target'] ?? '';
            $content = $file['content'] ?? '';
            $fileSize = strlen($content);

            $preview[] = [
                'path' => $path,
                'target' => $target,
                'size' => $fileSize,
                'sizeFormatted' => $this->formatBytes($fileSize),
                'preview' => $this->truncatePreview($content, 10),
            ];
        }

        return $preview;
    }

    /**
     * Get CSS variables preview
     */
    private function getCssVarsPreview(array $cssVars): array
    {
        if (empty($cssVars)) {
            return [];
        }

        $preview = [];

        foreach ($cssVars as $section => $vars) {
            $preview[] = [
                'section' => $section,
                'count' => count($vars),
                'variables' => array_keys($vars),
            ];
        }

        return $preview;
    }

    /**
     * Get CSS rules preview
     */
    private function getCssRulesPreview(array $css): array
    {
        if (empty($css)) {
            return [];
        }

        $preview = [];

        foreach ($css as $selector => $rules) {
            $preview[] = [
                'selector' => $selector,
                'properties' => count($rules),
            ];
        }

        return $preview;
    }

    /**
     * Format component for display
     */
    public function formatPreview(array $preview): string
    {
        $lines = [];

        $lines[] = '[COMPONENT] ' . $preview['name'] . '@' . $preview['version'];
        $lines[] = 'Type: ' . $preview['type'];
        $lines[] = '';

        if ($preview['title']) {
            $lines[] = 'Title: ' . $preview['title'];
        }

        if ($preview['description']) {
            $lines[] = 'Description: ' . $preview['description'];
        }

        $lines[] = '';

        // Files
        if (!empty($preview['files'])) {
            $lines[] = 'Files (' . count($preview['files']) . '):';
            foreach ($preview['files'] as $file) {
                $lines[] = '  * ' . $file['path'] . ' -> ' . $file['target'] . ' (' . $file['sizeFormatted'] . ')';
            }
            $lines[] = '';
        }

        // CSS Variables
        if (!empty($preview['css_vars'])) {
            $lines[] = 'CSS Variables:';
            foreach ($preview['css_vars'] as $varSection) {
                $lines[] = '  [' . $varSection['section'] . '] ' . $varSection['count'] . ' variables';
                foreach (array_slice($varSection['variables'], 0, 5) as $var) {
                    $lines[] = '    - ' . $var;
                }
                if (count($varSection['variables']) > 5) {
                    $lines[] = '    ... and ' . (count($varSection['variables']) - 5) . ' more';
                }
            }
            $lines[] = '';
        }

        // CSS Rules
        if (!empty($preview['css_rules'])) {
            $lines[] = 'CSS Rules:';
            foreach ($preview['css_rules'] as $rule) {
                $lines[] = '  * ' . $rule['selector'] . ' (' . $rule['properties'] . ' properties)';
            }
            $lines[] = '';
        }

        // Dependencies
        $deps = array_merge(
            $preview['dependencies']['composer'] ?? [],
            $preview['dependencies']['node'] ?? []
        );
        if (!empty($deps)) {
            $lines[] = 'Dependencies:';
            foreach ($deps as $dep) {
                $lines[] = '  * ' . $dep;
            }
            $lines[] = '';
        }

        // Registry Dependencies
        if (!empty($preview['registry_dependencies'])) {
            $lines[] = 'Registry Dependencies:';
            foreach ($preview['registry_dependencies'] as $dep) {
                $lines[] = '  * ' . $dep;
            }
            $lines[] = '';
        }

        return implode("\n", array_filter($lines));
    }

    /**
     * Truncate content for preview
     */
    public function truncatePreview(string $content, int $lines = 10): string
    {
        $contentLines = explode("\n", $content);

        if (count($contentLines) <= $lines) {
            return $content;
        }

        return implode("\n", array_slice($contentLines, 0, $lines)) . "\n... truncated";
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Show code preview of a file
     */
    public function showFilePreview(array $file, int $lines = 30): string
    {
        $content = $file['content'] ?? '';
        $path = $file['path'] ?? 'unknown';

        $output = [];
        $output[] = 'File: ' . $path;
        $output[] = str_repeat('-', 60);
        $output[] = $this->truncatePreview($content, $lines);
        $output[] = str_repeat('-', 60);

        return implode("\n", $output);
    }
}
