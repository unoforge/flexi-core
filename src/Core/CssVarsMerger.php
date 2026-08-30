<?php

namespace FlexiCore\Core;

class CssVarsMerger
{
    private string $cssFilePath;
    private string $currentContent;

    public function __construct(string $cssFilePath)
    {
        $this->cssFilePath = $cssFilePath;

        if (!file_exists($cssFilePath)) {
            throw new \RuntimeException("CSS file not found: {$cssFilePath}");
        }

        $this->currentContent = file_get_contents($cssFilePath);
    }

    public function merge(array $cssVars, array $css): void
    {
        $varsContent = CssVarsExtractor::formatCssVars($cssVars);
        $rulesContent = CssVarsExtractor::formatCssRules($css);

        $newContent = $this->currentContent;

        if (!empty($varsContent)) {
            $newContent = $this->mergeVariables($newContent, $varsContent);
        }

        if (!empty($rulesContent)) {
            $newContent = $this->appendRules($newContent, $rulesContent);
        }

        file_put_contents($this->cssFilePath, $newContent);
    }

    private function mergeVariables(string $content, string $varsContent): string
    {
        $rootPattern = '/:root\s*\{[^}]*\}/';
        $darkPattern = '/\.dark\s*\{[^}]*\}/';

        $rootMatches = [];
        preg_match_all($rootPattern, $content, $rootMatches);

        $darkMatches = [];
        preg_match_all($darkPattern, $content, $darkMatches);

        $newVarsLines = explode(PHP_EOL, trim($varsContent));
        $rootVars = [];
        $darkVars = [];

        foreach ($newVarsLines as $line) {
            if (str_contains($line, ':root')) {
                $inRoot = true;
                continue;
            }
            if (str_contains($line, '.dark')) {
                $inRoot = false;
                continue;
            }

            if (preg_match('/--([a-zA-Z0-9-]+):\s*(.+);/', $line, $m)) {
                $inRoot ? $rootVars[$m[1]] = $m[2] : $darkVars[$m[1]] = $m[2];
            }
        }

        if (!empty($rootMatches[0])) {
            $content = str_replace($rootMatches[0][0], $this->mergeRootBlock($rootMatches[0][0], $rootVars), $content);
        } elseif (!empty($rootVars)) {
            $rootBlock = ":root {\n";
            foreach ($rootVars as $name => $value) {
                $prefix = str_starts_with($name, '--') ? '' : '--';
                $rootBlock .= "  {$prefix}{$name}: {$value};\n";
            }
            $rootBlock .= "}";
            $content = $this->insertVariablesBlock($content, $rootBlock);
        }

        if (!empty($darkMatches[0]) && !empty($darkVars)) {
            $content = str_replace($darkMatches[0][0], $this->mergeDarkBlock($darkMatches[0][0], $darkVars), $content);
        } elseif (!empty($darkVars)) {
            $darkBlock = ".dark {\n";
            foreach ($darkVars as $name => $value) {
                $prefix = str_starts_with($name, '--') ? '' : '--';
                $darkBlock .= "  {$prefix}{$name}: {$value};\n";
            }
            $darkBlock .= "}";
            $content = $this->insertVariablesBlock($content, $darkBlock);
        }

        return $content;
    }

    private function updateVariableBlock(string $existingBlock, string $newVarsContent): string
    {
        $existingVars = $this->parseVariablesFromBlock($existingBlock);
        $newVars = $this->parseVariablesFromContent($newVarsContent);

        $merged = array_merge($existingVars, $newVars);

        return $this->reconstructVariableBlock($existingBlock, $merged);
    }

    private function parseVariablesFromBlock(string $block): array
    {
        $vars = [];

        if (preg_match_all('/--([a-zA-Z0-9-]+):\s*([^;]+);/', $block, $matches)) {
            foreach ($matches[1] as $key => $name) {
                $vars[$name] = trim($matches[2][$key]);
            }
        }

        return $vars;
    }

    private function parseVariablesFromContent(string $content): array
    {
        $vars = [];

        if (preg_match_all('/--([a-zA-Z0-9-]+):\s*([^;]+);/', $content, $matches)) {
            foreach ($matches[1] as $key => $name) {
                $vars[$name] = trim($matches[2][$key]);
            }
        }

        return $vars;
    }

    private function reconstructVariableBlock(string $existingBlock, array $allVars): string
    {
        preg_match('/^(\s*:root\s*\{)|(\s*\.dark\s*\{)/', $existingBlock, $selectorMatch);

        $selector = trim(substr($selectorMatch[0] ?? ':root {', 0, -1));

        $varLines = [];
        foreach ($allVars as $name => $value) {
            $prefix = str_starts_with($name, '--') ? '' : '--';
            $varLines[] = "  {$prefix}{$name}: {$value};";
        }

        return "{$selector} {\n" . implode(PHP_EOL, $varLines) . "\n}";
    }

    private function appendRules(string $content, string $rulesContent): string
    {
        return $content . PHP_EOL . PHP_EOL . "/* ====== Registry CSS Rules ====== */" . PHP_EOL . PHP_EOL . $rulesContent;
    }

    private function mergeRootBlock(string $existingBlock, array $newVars): string
    {
        $vars = $this->parseVariablesFromBlock($existingBlock);
        $merged = array_merge($vars, $newVars);

        $varLines = [];
        foreach ($merged as $name => $value) {
            $prefix = str_starts_with($name, '--') ? '' : '--';
            $varLines[] = "  {$prefix}{$name}: {$value};";
        }

        return ":root {\n" . implode(PHP_EOL, $varLines) . "\n}";
    }

    private function mergeDarkBlock(string $existingBlock, array $newVars): string
    {
        $vars = $this->parseVariablesFromBlock($existingBlock);
        $merged = array_merge($vars, $newVars);

        $varLines = [];
        foreach ($merged as $name => $value) {
            $prefix = str_starts_with($name, '--') ? '' : '--';
            $varLines[] = "  {$prefix}{$name}: {$value};";
        }

        return ".dark {\n" . implode(PHP_EOL, $varLines) . "\n}";
    }

    private function insertVariablesBlock(string $content, string $block): string
    {
        $lines = explode(PHP_EOL, $content);
        $insertIndex = 0;

        for ($i = 0; $i < count($lines); $i++) {
            if (str_contains($lines[$i], '@import') || str_contains($lines[$i], '@layer')) {
                $insertIndex = $i + 1;
            } elseif (trim($lines[$i]) !== '' && !str_contains($lines[$i], '@')) {
                break;
            }
        }

        array_splice($lines, $insertIndex, 0, [PHP_EOL . $block . PHP_EOL]);
        return implode(PHP_EOL, $lines);
    }

    public function getCurrentContent(): string
    {
        return $this->currentContent;
    }
}
