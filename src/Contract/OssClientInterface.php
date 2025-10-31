<?php

declare(strict_types=1);

namespace Tourze\AliyunObjectStorageBundle\Contract;

interface OssClientInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function putObject(string $bucket, string $key, string $content, array $headers = []): string;

    public function getObject(string $bucket, string $key): string;

    /**
     * @return array<string, mixed>
     */
    public function headObject(string $bucket, string $key): array;

    public function deleteObject(string $bucket, string $key): void;

    /**
     * @param array<string, string> $headers
     */
    public function copyObject(string $bucket, string $destKey, string $srcBucket, string $srcKey, array $headers = []): string;

    /**
     * @return array<string, mixed>
     */
    public function listObjects(string $bucket, string $prefix = '', string $delimiter = '', int $maxKeys = 1000, string $continuationToken = ''): array;

    /**
     * @param array<string, string> $headers
     */
    public function initiateMultipartUpload(string $bucket, string $key, array $headers = []): string;

    public function uploadPart(string $bucket, string $key, string $uploadId, int $partNumber, string $content): string;

    /**
     * @param array<int, array<string, mixed>> $parts
     */
    public function completeMultipartUpload(string $bucket, string $key, string $uploadId, array $parts): string;

    public function abortMultipartUpload(string $bucket, string $key, string $uploadId): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listParts(string $bucket, string $key, string $uploadId): array;

    /**
     * @param array<string, string> $headers
     */
    public function generatePresignedUrl(string $method, string $bucket, string $key, int $expires, array $headers = []): string;

    /**
     * @param array<int, mixed> $conditions
     * @return array<string, mixed>
     */
    public function generatePostPolicy(string $bucket, string $keyPrefix, int $expires, array $conditions = []): array;
}
