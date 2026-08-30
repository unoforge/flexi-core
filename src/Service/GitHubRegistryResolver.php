<?php

namespace FlexiCore\Service;

use FlexiCore\Core\GitHubComponentReference;

class GitHubRegistryResolver
{
    public function __construct(
        private GitHubSourceFetcher $fetcher = new GitHubSourceFetcher()
    ) {}

    /**
     * Resolve a component from GitHub
     */
    public function resolve(GitHubComponentReference $ref): ?array
    {
        $registry = $this->fetcher->fetch($ref);

        if ($registry === null) {
            return null;
        }

        return [
            'registry' => $registry,
            'resolvedVersion' => $registry['version'] ?? '0.0.0',
            'url' => $ref->getRegistryUrl(),
            'source' => 'github',
            'github_ref' => $ref->toDisplay(),
        ];
    }

    /**
     * List all components from a GitHub repository
     */
    public function listComponents(GitHubComponentReference $ref): array
    {
        $registries = $this->fetcher->listRegistries($ref);
        $components = [];

        foreach ($registries as $name => $item) {
            $itemRef = new GitHubComponentReference(
                $ref->owner,
                $ref->repo,
                $name,
                $ref->branch
            );

            $resolved = $this->resolve($itemRef);
            if ($resolved) {
                $components[] = $resolved['registry'];
            }
        }

        return $components;
    }

    /**
     * Get repository information
     */
    public function getRepoInfo(GitHubComponentReference $ref): ?array
    {
        return $this->fetcher->getRepoInfo($ref);
    }

    /**
     * Check if component exists
     */
    public function exists(GitHubComponentReference $ref): bool
    {
        return $this->fetcher->componentExists($ref);
    }

    /**
     * Set GitHub token for authentication
     */
    public function setToken(string $token): self
    {
        $this->fetcher->setToken($token);
        return $this;
    }

    /**
     * Validate GitHub reference
     */
    public function validate(GitHubComponentReference $ref): array
    {
        $errors = [];

        // Check repo exists
        $repoInfo = $this->getRepoInfo($ref);
        if ($repoInfo === null || isset($repoInfo['message'])) {
            $errors[] = "Repository not found: {$ref->owner}/{$ref->repo}";
            return ['valid' => false, 'errors' => $errors];
        }

        // Check component exists
        if (!$this->exists($ref)) {
            $errors[] = "Component not found: {$ref->component}";
            return ['valid' => false, 'errors' => $errors];
        }

        return ['valid' => true, 'errors' => []];
    }
}
