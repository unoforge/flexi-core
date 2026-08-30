<?php

namespace FlexiCore\Service;

use FlexiCore\Installer\PackageInstaller;

use function Laravel\Prompts\{text, spin, confirm, select, warning, info, error};

// TODO: Improve this later
class ProjectCreator
{
    public function createLaravel(): array
    {
        $app = $this->runComposerInit('Laravel', true);
        $projectPath = $app['projectPath'];
        $fromStarter = $app['fromStarter'];
        $livewire = $alpine = false;

        if (!$fromStarter) {
            $livewire = $this->askLivewire();
            if (!$livewire) $alpine = $this->askAlpine();
        }
        return compact('livewire', 'alpine', 'projectPath', 'fromStarter');
    }

    public function createSymfony(): array
    {
        $app = $this->runComposerInit('Symfony');
        $fromStarter = $app['fromStarter'];
        $projectPath = $app['projectPath'];

        $stimilus = !$fromStarter ? $this->askStimulus() : false;;
        return compact('stimilus', 'projectPath', 'fromStarter');
    }

    private function runComposerInit(string $label, bool $isLaravel = false)
    {
        info("======= Setup a new {$label} project. =======");
        $name = text(
            label: 'What is the name of your project?',
            default: 'my-app'
        );

        while (is_dir($name)) {
            $name = text(
                label: "The directory '{$name}' already exists. Please enter a different name for your project:",
                default: 'my-app'
            );
        }

        $useStarter = confirm('Do you want to use a starter project?', false);

        if ($useStarter) {
            $starterRepo = $isLaravel ? $this->askLaravelStarters() : $this->askSymfonyStarters();

            if ($starterRepo) {
                $this->cloneStarter($starterRepo, $name);
            } else {
                if (!is_dir($name)) {
                    mkdir($name);
                }
            }
        } else {
            $createCommand = $isLaravel ? "laravel new $name --no-interaction" : "composer create-project symfony/skeleton $name";
            spin(
                callback: fn() => exec($createCommand, $commandOutput, $returnCode),
                message: "Creating a new empty $label project"
            );
            info("{$label} project created.");
        }

        if (!is_dir($name)) {
            throw new \Exception("Failed to create project directory: $name");
        }

        chdir($name);
        return [
            'projectPath' => $name,
            'fromStarter' => $useStarter
        ];
    }

    public function askLivewire()
    {
        if (PackageInstaller::composer()->isInstalled('livewire/livewire')) {
            return true;
        }

        return confirm('Do you want to install livewire?');
    }

    public function askAlpine()
    {
        return confirm('Do you want to install AlpineJS?');
    }


    public function askStimulus()
    {
        return confirm('Do you want to install Stimulus?');
    }

    private function askLaravelStarters(): ?string
    {
        $starter = select(
            label: 'Which starter kit do you want to use?',
            options: [
                'livewire' => 'Livewire 4',
                'livewire-team' => 'Livewire 4 (with Teams)',
            ],
            default: 'livewire',
        );

        return match ($starter) {
            'livewire' => 'https://github.com/uno-forge-hub/livewire-starter.git',
            'livewire-team' => 'https://github.com/uno-forge-hub/livewire-tail-starter-kit.git',
            default => null,
        };
    }

    private function askSymfonyStarters(): ?string
    {
        $starter = select(
            label: 'Which starter kit do you want to use?',
            options: [
                'stimulus_ux' => 'Symfony UX + Stimulus',
                'stimulus_ux_tailwind' => 'Symfony UX + Stimulus + TailwindCSS',
                'twig_tailwind' => 'Twig + TailwindCSS',
                'twig_alpine' => 'Twig + AlpineJS',
            ],
            default: 'stimulus_ux_tailwind',
        );

        warning('Symfony starters are coming soon.');
        return null;
    }

    private function cloneStarter(string $repoUrl, string $name): void
    {
        $success = false;

        spin(
            callback: function () use ($repoUrl, $name, &$success) {
                exec("git clone {$repoUrl} {$name} 2>&1", $output, $returnCode);
                if ($returnCode !== 0) {
                    return;
                }

                $gitDir = $name . DIRECTORY_SEPARATOR . '.git';
                if (is_dir($gitDir)) {
                    $this->removeDirectory($gitDir);
                }

                $success = true;
            },
            message: "Cloning starter kit into {$name}..."
        );

        if (!$success) {
            error("Failed to clone starter from {$repoUrl}");
            return;
        }

        info("Starter kit cloned into {$name}");

        $installDeps = confirm('Install dependencies now?', true);

        if ($installDeps) {
            spin(
                callback: function () use ($name) {
                    exec("cd {$name} && composer install 2>&1", $output, $returnCode);
                },
                message: 'Installing Composer dependencies...'
            );

            $packageManager = 'npm';
            if (file_exists($name . '/pnpm-lock.yaml')) {
                $packageManager = 'pnpm';
            } elseif (file_exists($name . '/yarn.lock')) {
                $packageManager = 'yarn';
            }

            spin(
                callback: function () use ($name, $packageManager) {
                    exec("cd {$name} && {$packageManager} install 2>&1", $output, $returnCode);
                },
                message: "Installing Node dependencies ({$packageManager})..."
            );

            info('Dependencies installed.');
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
