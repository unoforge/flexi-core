<?php

namespace FlexiCore\Core;

use function Laravel\Prompts\{note, info, warning, spin, confirm};

class RegistryBuilder
{
    public function build(string $schemaPath, string $outputDir, string $overrideMode = 'auto'): void
    {
        if (!file_exists($schemaPath)) {
            throw new \RuntimeException("Schema file not found: $schemaPath");
        }

        $schema = json_decode(file_get_contents($schemaPath), true);
        if (!$schema || !isset($schema['components'])) {
            throw new \RuntimeException("Invalid schema file");
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $baseDir = dirname(realpath($schemaPath));

        foreach ($schema['components'] as $component) {
            $files = [];
            $componentVersion = $component['version'] ?? Constants::DEFAULT_COMPONENT_VERSION;
            $outputFile = rtrim($outputDir, '/') . '/' . $component['name'] . '.json';

            // Check if version has changed
            $isOverride = false;
            $versionChanged = $this->hasVersionChanged($outputFile, $componentVersion);
            
            if ($versionChanged) {
                note("Building component: " . $component['name']);
            } else {
                if ($overrideMode === 'force') {
                    $isOverride = true;
                    note("Building component: " . $component['name'] . " (forced override)");
                } elseif ($overrideMode === 'never') {
                    info("⏭️ Skipping component: " . $component['name'] . " (override disabled)");
                    continue;
                } else {
                    // auto mode - interactive
                    $override = confirm(
                        "Component '{$component['name']}' version {$componentVersion} is unchanged. Override anyway?",
                        false
                    );
                    if (!$override) {
                        info("⏭️ Skipping component: " . $component['name'] . " (user declined override)");
                        continue;
                    }
                    $isOverride = true;
                    note("Building component: " . $component['name'] . " (overriding unchanged version)");
                }
            }

            $registry = [
                '$schema'     => Constants::SCHEMA_REFERENCE,
                'version'     => $component['version'] ?? Constants::DEFAULT_COMPONENT_VERSION,
                'name'        => $component['name'],
                'type'        => $component['type'] ?? Constants::DEFAULT_COMPONENT_TYPE,
                'title'       => $component['title'] ?? '',
                'description' => $component['description'] ?? '',
            ];

            if (isset($component['message'])) {
                $normalizedMessage = $this->normalizeMessageField($component['message'], $component['name']);
                if ($normalizedMessage !== null) {
                    $registry['message'] = $normalizedMessage;
                }
            }

            $componentFiles = $component['files'] ?? [];
            if (!empty($componentFiles)) {
                spin(message: "Building files", callback: function () use ($componentFiles, &$files, $baseDir) {
                    foreach ($componentFiles as $fileItem) {
                        $filePath = $fileItem['path'];
                        $fullSourcePath = $baseDir . DIRECTORY_SEPARATOR . $filePath;

                        if (!file_exists($fullSourcePath)) {
                            warning("⚠️ File not found: {$fullSourcePath} — skipping.");
                            continue;
                        }

                        $content = file_get_contents($fullSourcePath);

                        $fileData = [
                            'path'    => $filePath,
                            'type'    => $fileItem['type'] ?? 'registry:component',
                            'target'  => $fileItem['target'] ?? $filePath,
                        ];

                        if (isset($fileItem['replace']) && is_array($fileItem['replace'])) {
                            foreach ($fileItem['replace'] as $search => $replace) {
                                $content = str_replace($search, $replace, $content);
                            }
                        }

                        $fileData['content'] = $content;
                        $files[] = $fileData;
                    }
                });
            }

            if (isset($component['registryDependencies'])) {
                if (!is_array($component['registryDependencies'])) {
                    warning("⚠️ `registryDependencies` must be an array for component `{$component['name']}`.");
                } else {
                    $registry['registryDependencies'] = $component['registryDependencies'];
                }
            }

            if (isset($component['dependencies'])) {
                if (!is_array($component['dependencies'])) {
                    warning("⚠️ `dependencies` must be an object for component `{$component['name']}`.");
                } else {
                    $registry['dependencies'] = $this->normalizeDependencyStructure($component['dependencies'], $component['name'], 'dependencies');
                }
            }

            if (isset($component['devDependencies'])) {
                if (!is_array($component['devDependencies'])) {
                    warning("⚠️ `devDependencies` must be an object for component `{$component['name']}`.");
                } else {
                    $registry['devDependencies'] = $this->normalizeDependencyStructure($component['devDependencies'], $component['name'], 'devDependencies');
                }
            }

            if (!empty($files)) {
                $registry['files'] = $files;
            }

            if (isset($component['cssVars'])) {
                $registry['cssVars'] = $component['cssVars'];
            }

            if (isset($component['css'])) {
                $registry['css'] = $component['css'];
            }

            if (isset($component['patch'])) {
                if (!is_array($component['patch'])) {
                    warning("⚠️ `patch` must be an object (file => modifications[]) for component `{$component['name']}`.");
                } else {
                    foreach ($component['patch'] as $file => $modifications) {
                        if (!is_string($file) || !is_array($modifications)) {
                            warning("⚠️ Invalid patch format for file `{$file}` in component `{$component['name']}`.");
                        }
                    }
                    $registry['patch'] = $component['patch'];
                }
            }

            $hasCssContent = !empty($component['cssVars']) || !empty($component['css']);
            if (empty($files) && !$hasCssContent) {
                warning("⚠️ No valid files or CSS for component: " . $component['name']);
                continue;
            }

            $outputFile = rtrim($outputDir, '/') . '/' . $component['name'] . '.json';
            if (!$isOverride) {
                $this->archiveExistingRegistryVersion($outputFile, $component['name']);
            }
            file_put_contents(
                $outputFile,
                json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            info("✔ " . $component['name'] . " built successfully");
        }
    }

    private function normalizeDependencyStructure(mixed $deps, string $componentName, string $section): array
    {
        $normalized = [
            'composer' => [],
            'node' => []
        ];

        if (is_array($deps)) {
            // Check if it's already in the new format
            if (isset($deps['composer']) || isset($deps['node'])) {
                // New format: { "composer": [...], "node": [...] }
                if (isset($deps['composer']) && is_array($deps['composer'])) {
                    $normalized['composer'] = $deps['composer'];
                }
                if (isset($deps['node']) && is_array($deps['node'])) {
                    $normalized['node'] = $deps['node'];
                }
            } else {
                // Legacy format - try to auto-detect package types
                foreach ($deps as $key => $value) {
                    if (is_string($key)) {
                        // Object format: { "package": "version" }
                        $packageWithVersion = "{$key}@{$value}";
                        if ($this->isComposerPackage($key)) {
                            $normalized['composer'][] = $packageWithVersion;
                        } else {
                            $normalized['node'][] = $packageWithVersion;
                        }
                    } elseif (is_string($value)) {
                        // Array format: ["package@version"]
                        $packageName = explode('@', $value)[0];
                        if ($this->isComposerPackage($packageName)) {
                            $normalized['composer'][] = $value;
                        } else {
                            $normalized['node'][] = $value;
                        }
                    }
                }
            }
        } else {
            warning("⚠️ `$section` must be an object for component `{$componentName}`.");
        }

        return $normalized;
    }

    private function archiveExistingRegistryVersion(string $outputFile, string $componentName): void
    {
        if (!file_exists($outputFile)) {
            return;
        }

        $existingContent = file_get_contents($outputFile);
        if ($existingContent === false) {
            warning("⚠️ Unable to read existing registry file for `{$componentName}`. Skipping version archive.");
            return;
        }

        $existingRegistry = json_decode($existingContent, true);
        if (!is_array($existingRegistry)) {
            warning("⚠️ Existing registry file for `{$componentName}` is invalid JSON. Skipping version archive.");
            return;
        }

        $existingVersion = $existingRegistry['version'] ?? null;
        if (!is_string($existingVersion) || trim($existingVersion) === '') {
            warning("⚠️ Existing registry file for `{$componentName}` has no version. Skipping version archive.");
            return;
        }

        $componentVersionDir = rtrim(dirname($outputFile), '/\\') . DIRECTORY_SEPARATOR . $componentName;
        if (!is_dir($componentVersionDir)) {
            mkdir($componentVersionDir, 0777, true);
        }

        $archivedFilePath = $componentVersionDir . DIRECTORY_SEPARATOR . $existingVersion . '.json';
        if (!file_exists($archivedFilePath)) {
            file_put_contents($archivedFilePath, $existingContent);
            info("Archived previous version for {$componentName}: {$existingVersion}");
        }
    }

    private function normalizeMessageField(mixed $message, string $componentName): string|array|null
    {
        if (is_string($message)) {
            $trimmed = trim($message);
            return $trimmed === '' ? null : $trimmed;
        }

        if (!is_array($message)) {
            warning("⚠️ `message` must be a string or string[] for component `{$componentName}`.");
            return null;
        }

        $messages = [];
        foreach ($message as $entry) {
            if (!is_string($entry)) {
                warning("⚠️ `message` entries must be strings for component `{$componentName}`.");
                continue;
            }

            $trimmed = trim($entry);
            if ($trimmed !== '') {
                $messages[] = $trimmed;
            }
        }

        if (empty($messages)) {
            return null;
        }

        return array_values(array_unique($messages));
    }

    private function hasVersionChanged(string $outputFile, string $newVersion): bool
    {
        if (!file_exists($outputFile)) {
            return true; // No existing file, so we need to build
        }

        $existingContent = file_get_contents($outputFile);
        if ($existingContent === false) {
            return true; // Can't read existing file, rebuild to be safe
        }

        $existingRegistry = json_decode($existingContent, true);
        if (!is_array($existingRegistry) || !isset($existingRegistry['version'])) {
            return true; // Invalid existing file, rebuild
        }

        $existingVersion = $existingRegistry['version'];
        return $existingVersion !== $newVersion;
    }
}
