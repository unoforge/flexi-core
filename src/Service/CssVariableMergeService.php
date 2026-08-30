<?php

namespace FlexiCore\Service;

use FlexiCore\Core\{CssVarsExtractor, CssVarsMerger};

class CssVariableMergeService
{
    private string $projectRoot;
    private array $cssPath;

    public function __construct(string $projectRoot, array $config = [])
    {
        $this->projectRoot = $projectRoot;
        $this->cssPath = $config;
    }

    public function applyComponentStyles(array $component, string $entryFileName = 'app'): array
    {
        $summary = [];

        if (!CssVarsExtractor::hasCssContent($component)) {
            return $summary;
        }

        $cssFilePath = $this->resolveCssFilePath($entryFileName);

        if (!file_exists($cssFilePath)) {
            $summary['error'] = "CSS file not found: {$cssFilePath}";
            return $summary;
        }

        try {
            $extracted = CssVarsExtractor::extract($component);

            $merger = new CssVarsMerger($cssFilePath);
            $merger->merge($extracted['cssVars'], $extracted['css']);

            $summary['success'] = true;
            $summary['cssFile'] = $cssFilePath;
            $totalVars = 0;
            foreach ($extracted['cssVars'] as $vars) {
                $totalVars += is_array($vars) ? count($vars) : 0;
            }
            $summary['varsCount'] = $totalVars;
            $summary['rulesCount'] = count($extracted['css']);
        } catch (\Exception $e) {
            $summary['error'] = $e->getMessage();
        }

        return $summary;
    }

    public function batchApplyStyles(array $components, string $entryFileName = 'app'): array
    {
        $allSummary = [];

        foreach ($components as $component) {
            $summary = $this->applyComponentStyles($component, $entryFileName);
            if (!empty($summary)) {
                $allSummary[] = $summary;
            }
        }

        return $allSummary;
    }

    private function resolveCssFilePath(string $entryFileName): string
    {
        $cssDir = $this->cssPath['path'] ?? $this->cssPath;

        if (is_array($cssDir)) {
            $cssDir = $cssDir['path'] ?? 'resources/css';
        }

        return rtrim($this->projectRoot, '/') . '/' . rtrim($cssDir, '/') . '/' . $entryFileName . '.css';
    }
}
