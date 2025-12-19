<?php

declare(strict_types=1);

namespace Tourze\AliyunObjectStorageBundle\Url;

readonly class PublicUrlGenerator
{
    public function __construct(
        private string $endpoint,
        private string $bucket,
        private string $prefix = '',
        private ?string $publicDomain = null,
        private bool $cnameEnabled = false,
        private bool $internal = false,
    ) {
    }

    public function generateUrl(string $key): string
    {
        $cleanKey = ltrim($key, '/');
        $fullKey = '' !== $this->prefix ? $this->prefix . '/' . $cleanKey : $cleanKey;

        if ($this->cnameEnabled && null !== $this->publicDomain) {
            return sprintf('https://%s/%s', $this->publicDomain, $fullKey);
        }

        $endpoint = $this->internal ? $this->convertToInternalEndpoint($this->endpoint) : $this->endpoint;

        return sprintf('https://%s.%s/%s', $this->bucket, $endpoint, $fullKey);
    }

    private function convertToInternalEndpoint(string $endpoint): string
    {
        if (str_contains($endpoint, '-internal.')) {
            return $endpoint;
        }

        return str_replace('.aliyuncs.com', '-internal.aliyuncs.com', $endpoint);
    }

    public function generateSecureUrl(string $key, int $expires): string
    {
        $fullKey = '' !== $this->prefix ? ltrim($this->prefix . '/' . ltrim($key, '/'), '/') : $key;

        $endpoint = $this->internal ? $this->convertToInternalEndpoint($this->endpoint) : $this->endpoint;

        return sprintf('https://%s.%s/%s', $this->bucket, $endpoint, $fullKey);
    }

    public function getHost(): string
    {
        if ($this->cnameEnabled && null !== $this->publicDomain) {
            return $this->publicDomain;
        }

        $endpoint = $this->internal ? $this->convertToInternalEndpoint($this->endpoint) : $this->endpoint;

        return sprintf('%s.%s', $this->bucket, $endpoint);
    }
}
