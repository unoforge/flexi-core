<?php

namespace FlexiCore\Core;

class GitHubComponentReference
{
    public function __construct(
        public readonly string $owner,
        public readonly string $repo,
        public readonly string $component,
        public readonly ?string $branch = 'main',
    ) {}

    /**
     * Parse GitHub reference from string
     * Formats:
     *   - github:owner/repo/component
     *   - owner/repo/component
     *   - github:owner/repo/component@branch
     */
    public static function parse(string $input): ?self
    {
        $input = trim($input);

        // Remove github: prefix if present
        if (str_starts_with($input, 'github:')) {
            $input = substr($input, 7);
        }

        // Parse owner/repo/component@branch
        $parts = explode('/', $input);

        if (count($parts) < 3) {
            return null;
        }

        $owner = $parts[0];
        $repo = $parts[1];
        $componentWithBranch = implode('/', array_slice($parts, 2));

        // Extract branch from component if present
        $branch = 'main';
        if (str_contains($componentWithBranch, '@')) {
            [$component, $branch] = explode('@', $componentWithBranch, 2);
        } else {
            $component = $componentWithBranch;
        }

        // Validate
        if (!self::isValidOwner($owner) || !self::isValidRepo($repo) || !self::isValidComponent($component)) {
            return null;
        }

        return new self($owner, $repo, $component, $branch);
    }

    /**
     * Validate GitHub owner/org name
     */
    private static function isValidOwner(string $owner): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,38}[a-zA-Z0-9])?$/', $owner);
    }

    /**
     * Validate GitHub repo name
     */
    private static function isValidRepo(string $repo): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9._-]+$/', $repo);
    }

    /**
     * Validate component name
     */
    private static function isValidComponent(string $component): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_\/-]+$/', $component);
    }

    /**
     * Get GitHub raw content URL for registry file
     */
    public function getRegistryUrl(): string
    {
        $path = $this->getRegistryPath();
        return "https://raw.githubusercontent.com/{$this->owner}/{$this->repo}/{$this->branch}/{$path}";
    }

    /**
     * Get GitHub API URL for repo
     */
    public function getApiUrl(): string
    {
        return "https://api.github.com/repos/{$this->owner}/{$this->repo}";
    }

    /**
     * Get GitHub browser URL
     */
    public function getBrowserUrl(): string
    {
        return "https://github.com/{$this->owner}/{$this->repo}";
    }

    /**
     * Get registry file path: public/registries/{component}.json
     */
    private function getRegistryPath(): string
    {
        return "public/registries/{$this->component}.json";
    }

    /**
     * Display format: owner/repo/component@branch
     */
    public function toDisplay(): string
    {
        $branch = $this->branch && $this->branch !== 'main' ? "@{$this->branch}" : '';
        return "{$this->owner}/{$this->repo}/{$this->component}{$branch}";
    }

    /**
     * Display format with github: prefix
     */
    public function toDisplayWithPrefix(): string
    {
        return "github:{$this->toDisplay()}";
    }

    /**
     * Check if this looks like a GitHub reference
     * GitHub refs: owner/repo/component or github:owner/repo/component
     * Vs registry refs: @namespace/component or component
     */
    public static function isGitHubReference(string $input): bool
    {
        // Explicit github: prefix
        if (str_starts_with($input, 'github:')) {
            return true;
        }

        // If starts with @ it's a namespace registry, not GitHub
        if (str_starts_with($input, '@')) {
            return false;
        }

        // If has 2+ slashes and doesn't start with @, it's GitHub
        return substr_count($input, '/') >= 2;
    }
}
