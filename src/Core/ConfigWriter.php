<?php

namespace FlexiCore\Core;


class ConfigWriter
{
    public static function createFlexiwindYaml(array $answers): void
    {
        $yaml = "framework: {$answers['framework']}\n";
        if ($answers['framework'] === 'laravel') {
            $livewireValue = $answers['livewire'] ? 'true' : 'false';
            $alpineValue = $answers['alpine'] ? 'true' : 'false';
            $yaml .= "livewire: {$livewireValue}\n";
            $yaml .= "alpine: {$alpineValue}\n";
        }

        if ($answers['framework'] === 'symfony') {
            $yaml .= "stimulus: {$answers['stimilus']}\n";
        }

        $yaml .= "theme: {$answers['theme']}\n";
        $yaml .= "themeMode: {$answers['themingMode']}\n";
        $yaml .= "cssFramework: {$answers['cssFramework']}\n";
        if (isset($answers['iconLibrary']) && !empty($answers['iconLibrary'])) {
            $yaml .= "iconLibrary: {$answers['iconLibrary']}\n";
        }
        $yaml .= "js_folder: {$answers['js']}\n";
        $yaml .= "css_folder: {$answers['css']}\n";
        
        // Add default registry configuration
        $yaml .= "defaultSource: " . Constants::DEFAULT_REGISTRY . "\n";
        $yaml .= "registries:\n";
        $yaml .= "  '" . Constants::FLEXIWIND_NAMESPACE . "': " . Constants::DEFAULT_REGISTRY . "\n";

        file_put_contents('flexiwind.yaml', $yaml);
    }
}
