<?php

namespace FlexiCore\Core;

class CssVarsExtractor
{
    public static function extract(array $component): array
    {
        return [
            'cssVars' => $component['cssVars'] ?? [],
            'css' => $component['css'] ?? [],
        ];
    }

    public static function hasCssContent(array $component): bool
    {
        return !empty($component['cssVars']) || !empty($component['css']);
    }

    public static function formatCssVars(array $cssVars): string
    {
        $lines = [];

        foreach ($cssVars as $section => $vars) {
            if (empty($vars)) {
                continue;
            }

            $selector = match ($section) {
                'theme' => ':root',
                'light' => ':root',
                'dark' => '.dark',
                default => "/* {$section} */ :root",
            };

            $lines[] = "$selector {";

            foreach ($vars as $varName => $varValue) {
                $prefix = str_starts_with($varName, '--') ? '' : '--';
                $lines[] = "  {$prefix}{$varName}: {$varValue};";
            }

            $lines[] = "}";
            $lines[] = "";
        }

        return implode(PHP_EOL, array_filter($lines));
    }

    public static function formatCssRules(array $css): string
    {
        $output = [];

        foreach ($css as $selector => $rules) {
            if (empty($rules)) {
                continue;
            }

            $output[] = self::formatRule($selector, $rules);
        }

        return implode(PHP_EOL . PHP_EOL, array_filter($output));
    }

    private static function formatRule(string $selector, array|string $rules): string
    {
        if (is_string($rules)) {
            return "$selector { $rules }";
        }

        $properties = [];
        foreach ($rules as $prop => $value) {
            if (is_string($value)) {
                $properties[] = "  $prop: $value;";
            } elseif (is_array($value)) {
                $nested = self::formatNestedRule($prop, $value);
                return "$selector {\n$nested\n}";
            }
        }

        if (empty($properties)) {
            return "";
        }

        return "$selector {\n" . implode(PHP_EOL, $properties) . "\n}";
    }

    private static function formatNestedRule(string $selector, array $rules): string
    {
        $properties = [];
        foreach ($rules as $prop => $value) {
            if (is_string($value)) {
                $properties[] = "    $prop: $value;";
            }
        }
        return "  $selector {\n" . implode(PHP_EOL, $properties) . "\n  }";
    }
}
