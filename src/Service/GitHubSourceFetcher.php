<?php

namespace FlexiCore\Service;

use FlexiCore\Core\GitHubComponentReference;

class GitHubSourceFetcher
{
    private ?string $token = null;
    private int $timeout = 10;

    public function __construct(?string $token = null)
    {
        $this->token = $token ?? $_ENV['GITHUB_TOKEN'] ?? null;
    }

    /**
     * Fetch registry JSON from GitHub
     * Looks in: public/registries/{component}.json
     */
    public function fetch(GitHubComponentReference $ref): ?array
    {
        $url = "https://raw.githubusercontent.com/{$ref->owner}/{$ref->repo}/{$ref->branch}/public/registries/{$ref->component}.json";
        $content = $this->fetchRaw($url);

        if ($content !== null) {
            try {
                return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return null;
            }
        }

        return null;
    }

    /**
     * Fetch raw content from GitHub
     */
    public function fetchRaw(string $url, ?string $token = null): ?string
    {
        $token = $token ?? $this->token;

        $context = stream_context_create($this->getStreamOptions($token));

        $content = @file_get_contents($url, false, $context, 0, 1000000);

        return $content ?: null;
    }

    /**
     * List all registries in a GitHub repository
     * Looks in: public/registries/
     */
    public function listRegistries(GitHubComponentReference $ref): array
    {
        $registries = [];
        $url = "https://api.github.com/repos/{$ref->owner}/{$ref->repo}/contents/public/registries";
        $items = $this->fetchApiContents($url);

        if (is_array($items)) {
            foreach ($items as $item) {
                if (($item['type'] ?? null) === 'file' && str_ends_with($item['name'], '.json')) {
                    $name = str_replace('.json', '', $item['name']);
                    $registries[$name] = $item;
                }
            }
        }

        return $registries;
    }

    /**
     * Fetch GitHub API contents
     */
    private function fetchApiContents(string $url): ?array
    {
        $content = $this->fetchRaw($url);

        if ($content === null) {
            return null;
        }

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * Get repository info from GitHub API
     */
    public function getRepoInfo(GitHubComponentReference $ref): ?array
    {
        $url = "https://api.github.com/repos/{$ref->owner}/{$ref->repo}";
        return $this->fetchApiContents($url);
    }

    /**
     * Check if component exists in repository
     */
    public function componentExists(GitHubComponentReference $ref): bool
    {
        return $this->fetch($ref) !== null;
    }

    /**
     * Get stream context options for HTTP request
     */
    private function getStreamOptions(?string $token = null): array
    {
        $headers = [
            "User-Agent: Flexi-CLI/1.0",
            "Accept: application/vnd.github.v3.raw",
        ];

        if ($token) {
            $headers[] = "Authorization: token {$token}";
        }

        return [
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
            'https' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ];
    }

    /**
     * Set GitHub token
     */
    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    /**
     * Set request timeout
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }
}
