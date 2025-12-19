<?php

declare(strict_types=1);

namespace Tourze\AliyunObjectStorageBundle\Factory;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tourze\AliyunObjectStorageBundle\Adapter\AliyunOssAdapter;
use Tourze\AliyunObjectStorageBundle\Client\OssClient;
use Tourze\AliyunObjectStorageBundle\Signature\OssSignature;
use Tourze\AliyunObjectStorageBundle\Url\PublicUrlGenerator;

readonly class OssAdapterFactory
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    public function createAdapter(): ?AliyunOssAdapter
    {
        $accessKeyId = $_ENV['ALIYUN_OSS_ACCESS_KEY_ID'] ?? '';
        $accessKeySecret = $_ENV['ALIYUN_OSS_ACCESS_KEY_SECRET'] ?? '';
        $bucket = $_ENV['ALIYUN_OSS_BUCKET'] ?? '';

        if ('' === $accessKeyId || '' === $accessKeySecret || '' === $bucket) {
            return null;
        }

        $region = $_ENV['ALIYUN_OSS_REGION'] ?? 'cn-hangzhou';
        $endpoint = $_ENV['ALIYUN_OSS_ENDPOINT'] ?? sprintf('oss-%s.aliyuncs.com', $region);
        $prefix = $_ENV['ALIYUN_OSS_PREFIX'] ?? '';
        $publicDomain = $_ENV['ALIYUN_OSS_PUBLIC_DOMAIN'] ?? null;
        $cnameEnabled = filter_var($_ENV['ALIYUN_OSS_CNAME_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $internal = filter_var($_ENV['ALIYUN_OSS_INTERNAL'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $signature = new OssSignature($accessKeyId, $accessKeySecret);
        $client = new OssClient($this->httpClient, $signature, $endpoint, $this->logger);

        return new AliyunOssAdapter($client, $bucket, $prefix);
    }

    public function createUrlGenerator(): ?PublicUrlGenerator
    {
        $bucket = $_ENV['ALIYUN_OSS_BUCKET'] ?? '';
        if ('' === $bucket) {
            return null;
        }

        $region = $_ENV['ALIYUN_OSS_REGION'] ?? 'cn-hangzhou';
        $endpoint = $_ENV['ALIYUN_OSS_ENDPOINT'] ?? sprintf('oss-%s.aliyuncs.com', $region);
        $prefix = $_ENV['ALIYUN_OSS_PREFIX'] ?? '';
        $publicDomain = $_ENV['ALIYUN_OSS_PUBLIC_DOMAIN'] ?? null;
        $cnameEnabled = filter_var($_ENV['ALIYUN_OSS_CNAME_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $internal = filter_var($_ENV['ALIYUN_OSS_INTERNAL'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return new PublicUrlGenerator($endpoint, $bucket, $prefix, $publicDomain, $cnameEnabled, $internal);
    }

    public function createClient(): ?OssClient
    {
        $accessKeyId = $_ENV['ALIYUN_OSS_ACCESS_KEY_ID'] ?? '';
        $accessKeySecret = $_ENV['ALIYUN_OSS_ACCESS_KEY_SECRET'] ?? '';

        if ('' === $accessKeyId || '' === $accessKeySecret) {
            return null;
        }

        $region = $_ENV['ALIYUN_OSS_REGION'] ?? 'cn-hangzhou';
        $endpoint = $_ENV['ALIYUN_OSS_ENDPOINT'] ?? sprintf('oss-%s.aliyuncs.com', $region);

        $signature = new OssSignature($accessKeyId, $accessKeySecret);

        return new OssClient($this->httpClient, $signature, $endpoint, $this->logger);
    }
}
