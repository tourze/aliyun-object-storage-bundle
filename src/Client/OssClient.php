<?php

declare(strict_types=1);

namespace Tourze\AliyunObjectStorageBundle\Client;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tourze\AliyunObjectStorageBundle\Contract\OssClientInterface;
use Tourze\AliyunObjectStorageBundle\Exception\OssException;
use Tourze\AliyunObjectStorageBundle\Signature\OssSignature;

class OssClient implements OssClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly OssSignature $signature,
        private readonly string $endpoint,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function putObject(string $bucket, string $key, string $content, array $headers = []): string
    {
        $headers['content-type'] ??= 'application/octet-stream';
        $headers['content-length'] = (string) strlen($content);
        $headers['date'] = gmdate('D, d M Y H:i:s \G\M\T');

        $authHeader = $this->signature->signRequest('PUT', $bucket, $key, $headers);
        $headers['authorization'] = 'OSS ' . $this->signature->getAccessKeyId() . ':' . $authHeader;

        $url = $this->buildUrl($bucket, $key);

        $startTime = microtime(true);
        try {
            $response = $this->httpClient->request('PUT', $url, [
                'headers' => $headers,
                'body' => $content,
            ]);

            $statusCode = $response->getStatusCode();
            if (200 !== $statusCode) {
                throw new OssException('Failed to put object', $statusCode, $response->getContent(false));
            }

            $etag = $response->getHeaders()['etag'][0] ?? '';
            $requestId = $response->getHeaders()['x-oss-request-id'][0] ?? '';

            $this->logger->info('OSS putObject succeeded', [
                'bucket' => $bucket,
                'key' => $key,
                'etag' => $etag,
                'request_id' => $requestId,
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return trim($etag, '"');
        } catch (\Exception $e) {
            $this->logger->error('OSS putObject failed', [
                'bucket' => $bucket,
                'key' => $key,
                'error' => $e->getMessage(),
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            throw $e;
        }
    }

    private function buildUrl(string $bucket, string $key): string
    {
        return sprintf('https://%s.%s/%s', $bucket, $this->endpoint, ltrim($key, '/'));
    }

    public function getObject(string $bucket, string $key): string
    {
        $headers = [
            'date' => gmdate('D, d M Y H:i:s \G\M\T'),
        ];

        $authHeader = $this->signature->signRequest('GET', $bucket, $key, $headers);
        $headers['authorization'] = 'OSS ' . $this->signature->getAccessKeyId() . ':' . $authHeader;

        $url = $this->buildUrl($bucket, $key);

        $startTime = microtime(true);
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
            ]);

            $statusCode = $response->getStatusCode();
            if (404 === $statusCode) {
                throw new OssException('Object not found', 404);
            }

            if (200 !== $statusCode) {
                throw new OssException('Failed to get object', $statusCode, $response->getContent(false));
            }

            $content = $response->getContent();
            $requestId = $response->getHeaders()['x-oss-request-id'][0] ?? '';

            $this->logger->info('OSS getObject succeeded', [
                'bucket' => $bucket,
                'key' => $key,
                'size' => strlen($content),
                'request_id' => $requestId,
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $content;
        } catch (\Exception $e) {
            $this->logger->error('OSS getObject failed', [
                'bucket' => $bucket,
                'key' => $key,
                'error' => $e->getMessage(),
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function headObject(string $bucket, string $key): array
    {
        $headers = [
            'date' => gmdate('D, d M Y H:i:s \G\M\T'),
        ];

        $authHeader = $this->signature->signRequest('HEAD', $bucket, $key, $headers);
        $headers['authorization'] = 'OSS ' . $this->signature->getAccessKeyId() . ':' . $authHeader;

        $url = $this->buildUrl($bucket, $key);

        $startTime = microtime(true);
        try {
            $response = $this->httpClient->request('HEAD', $url, [
                'headers' => $headers,
            ]);

            $statusCode = $response->getStatusCode();
            if (404 === $statusCode) {
                throw new OssException('Object not found', 404);
            }

            if (200 !== $statusCode) {
                throw new OssException('Failed to head object', $statusCode);
            }

            $responseHeaders = $response->getHeaders();
            $requestId = $responseHeaders['x-oss-request-id'][0] ?? '';

            $metadata = [
                'size' => (int) ($responseHeaders['content-length'][0] ?? 0),
                'etag' => trim($responseHeaders['etag'][0] ?? '', '"'),
                'last_modified' => $responseHeaders['last-modified'][0] ?? '',
                'content_type' => $responseHeaders['content-type'][0] ?? '',
            ];

            foreach ($responseHeaders as $name => $values) {
                $lowerName = strtolower($name);
                if (str_starts_with($lowerName, 'x-oss-meta-')) {
                    if (!isset($metadata['user_metadata'])) {
                        $metadata['user_metadata'] = [];
                    }
                    $metadata['user_metadata'][substr($lowerName, 11)] = $values[0];
                }
            }

            $this->logger->info('OSS headObject succeeded', [
                'bucket' => $bucket,
                'key' => $key,
                'size' => $metadata['size'],
                'request_id' => $requestId,
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $metadata;
        } catch (\Exception $e) {
            $this->logger->error('OSS headObject failed', [
                'bucket' => $bucket,
                'key' => $key,
                'error' => $e->getMessage(),
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            throw $e;
        }
    }

    public function deleteObject(string $bucket, string $key): void
    {
        $headers = [
            'date' => gmdate('D, d M Y H:i:s \G\M\T'),
        ];

        $authHeader = $this->signature->signRequest('DELETE', $bucket, $key, $headers);
        $headers['authorization'] = 'OSS ' . $this->signature->getAccessKeyId() . ':' . $authHeader;

        $url = $this->buildUrl($bucket, $key);

        $startTime = microtime(true);
        try {
            $response = $this->httpClient->request('DELETE', $url, [
                'headers' => $headers,
            ]);

            $statusCode = $response->getStatusCode();
            if (204 !== $statusCode && 404 !== $statusCode) {
                throw new OssException('Failed to delete object', $statusCode, $response->getContent(false));
            }

            $requestId = $response->getHeaders()['x-oss-request-id'][0] ?? '';

            $this->logger->info('OSS deleteObject succeeded', [
                'bucket' => $bucket,
                'key' => $key,
                'request_id' => $requestId,
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('OSS deleteObject failed', [
                'bucket' => $bucket,
                'key' => $key,
                'error' => $e->getMessage(),
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            throw $e;
        }
    }

    /**
     * @param array<string, string> $headers
     */
    public function copyObject(string $bucket, string $destKey, string $srcBucket, string $srcKey, array $headers = []): string
    {
        $headers['x-oss-copy-source'] = "/{$srcBucket}/{$srcKey}";
        $headers['date'] = gmdate('D, d M Y H:i:s \G\M\T');

        $authHeader = $this->signature->signRequest('PUT', $bucket, $destKey, $headers);
        $headers['authorization'] = 'OSS ' . $this->signature->getAccessKeyId() . ':' . $authHeader;

        $url = $this->buildUrl($bucket, $destKey);

        $startTime = microtime(true);
        try {
            $response = $this->httpClient->request('PUT', $url, [
                'headers' => $headers,
            ]);

            $statusCode = $response->getStatusCode();
            if (200 !== $statusCode) {
                throw new OssException('Failed to copy object', $statusCode, $response->getContent(false));
            }

            $content = $response->getContent();
            $xml = simplexml_load_string($content);
            $etag = trim($this->getXmlProperty($xml, 'ETag'), '"');
            $requestId = $response->getHeaders()['x-oss-request-id'][0] ?? '';

            $this->logger->info('OSS copyObject succeeded', [
                'src_bucket' => $srcBucket,
                'src_key' => $srcKey,
                'dest_bucket' => $bucket,
                'dest_key' => $destKey,
                'etag' => $etag,
                'request_id' => $requestId,
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $etag;
        } catch (\Exception $e) {
            $this->logger->error('OSS copyObject failed', [
                'src_bucket' => $srcBucket,
                'src_key' => $srcKey,
                'dest_bucket' => $bucket,
                'dest_key' => $destKey,
                'error' => $e->getMessage(),
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            throw $e;
        }
    }

    /**
     * @param \SimpleXMLElement|false $xml
     */
    private function getXmlProperty($xml, string $property): string
    {
        if (false === $xml) {
            throw new OssException('XML parsing failed');
        }

        return match ($property) {
            'ETag' => (string) $xml->ETag,
            'IsTruncated' => (string) $xml->IsTruncated,
            'NextContinuationToken' => (string) $xml->NextContinuationToken,
            'UploadId' => (string) $xml->UploadId,
            default => throw new OssException("Unsupported XML property: {$property}"),
        };
    }

    /**
     * @return array<string, string>
     */
    private function buildListObjectsQuery(string $prefix, string $delimiter, int $maxKeys, string $continuationToken): array
    {
        $query = [
            'list-type' => '2',
            'max-keys' => (string) $maxKeys,
        ];

        if ('' !== $prefix) {
            $query['prefix'] = $prefix;
        }

        if ('' !== $delimiter) {
            $query['delimiter'] = $delimiter;
        }

        if ('' !== $continuationToken) {
            $query['continuation-token'] = $continuationToken;
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseListObjectsResponse(string $content): array
    {
        $xml = simplexml_load_string($content);
        if (false === $xml) {
            throw new OssException('XML parsing failed');
        }

        $result = [
            'objects' => $this->extractObjectsFromXml($xml),
            'common_prefixes' => $this->extractCommonPrefixesFromXml($xml),
            'is_truncated' => 'true' === $this->getXmlProperty($xml, 'IsTruncated'),
        ];

        if (isset($xml->NextContinuationToken)) {
            $result['next_continuation_token'] = $this->getXmlProperty($xml, 'NextContinuationToken');
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractObjectsFromXml(\SimpleXMLElement $xml): array
    {
        if (!isset($xml->Contents)) {
            return [];
        }

        $objects = [];
        foreach ($xml->Contents as $xmlContent) {
            $objects[] = [
                'key' => (string) $xmlContent->Key,
                'last_modified' => (string) $xmlContent->LastModified,
                'etag' => trim((string) $xmlContent->ETag, '"'),
                'size' => (int) $xmlContent->Size,
                'storage_class' => (string) $xmlContent->StorageClass,
            ];
        }

        return $objects;
    }

    /**
     * @return array<int, string>
     */
    private function extractCommonPrefixesFromXml(\SimpleXMLElement $xml): array
    {
        if (!isset($xml->CommonPrefixes)) {
            return [];
        }

        $commonPrefixes = [];
        foreach ($xml->CommonPrefixes as $commonPrefix) {
            $commonPrefixes[] = (string) $commonPrefix->Prefix;
        }

        return $commonPrefixes;
    }

    /**
     * @return array<string, mixed>
     */
    public function listObjects(string $bucket, string $prefix = '', string $delimiter = '', int $maxKeys = 1000, string $continuationToken = ''): array
    {
        $query = $this->buildListObjectsQuery($prefix, $delimiter, $maxKeys, $continuationToken);
        $headers = ['date' => gmdate('D, d M Y H:i:s \G\M\T')];

        $authHeader = $this->signature->signRequest('GET', $bucket, '', $headers, $query);
        $headers['authorization'] = 'OSS ' . $this->signature->getAccessKeyId() . ':' . $authHeader;

        $url = $this->buildUrl($bucket, '') . '?' . http_build_query($query);

        $startTime = microtime(true);
        try {
            $response = $this->httpClient->request('GET', $url, ['headers' => $headers]);

            $statusCode = $response->getStatusCode();
            if (200 !== $statusCode) {
                throw new OssException('Failed to list objects', $statusCode, $response->getContent(false));
            }

            $result = $this->parseListObjectsResponse($response->getContent());
            $requestId = $response->getHeaders()['x-oss-request-id'][0] ?? '';

            $this->logger->info('OSS listObjects succeeded', [
                'bucket' => $bucket,
                'prefix' => $prefix,
                'object_count' => count($result['objects']),
                'request_id' => $requestId,
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('OSS listObjects failed', [
                'bucket' => $bucket,
                'prefix' => $prefix,
                'error' => $e->getMessage(),
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            throw $e;
        }
    }

    /**
     * @param array<string, string> $headers
     */
    public function initiateMultipartUpload(string $bucket, string $key, array $headers = []): string
    {
        $query = ['uploads' => ''];

        $headers['date'] = gmdate('D, d M Y H:i:s \G\M\T');

        $authHeader = $this->signature->signRequest('POST', $bucket, $key, $headers, $query);
        $headers['authorization'] = 'OSS ' . $this->signature->getAccessKeyId() . ':' . $authHeader;

        $url = $this->buildUrl($bucket, $key) . '?uploads';

        $startTime = microtime(true);
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
            ]);

            $statusCode = $response->getStatusCode();
            if (200 !== $statusCode) {
                throw new OssException('Failed to initiate multipart upload', $statusCode, $response->getContent(false));
            }

            $content = $response->getContent();
            $xml = simplexml_load_string($content);
            $uploadId = $this->getXmlProperty($xml, 'UploadId');
            $requestId = $response->getHeaders()['x-oss-request-id'][0] ?? '';

            $this->logger->info('OSS initiateMultipartUpload succeeded', [
                'bucket' => $bucket,
                'key' => $key,
                'upload_id' => $uploadId,
                'request_id' => $requestId,
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $uploadId;
        } catch (\Exception $e) {
            $this->logger->error('OSS initiateMultipartUpload failed', [
                'bucket' => $bucket,
                'key' => $key,
                'error' => $e->getMessage(),
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            throw $e;
        }
    }

    public function uploadPart(string $bucket, string $key, string $uploadId, int $partNumber, string $content): string
    {
        $query = [
            'partNumber' => $partNumber,
            'uploadId' => $uploadId,
        ];

        $headers = [
            'content-length' => (string) strlen($content),
            'date' => gmdate('D, d M Y H:i:s \G\M\T'),
        ];

        $authHeader = $this->signature->signRequest('PUT', $bucket, $key, $headers, $query);
        $headers['authorization'] = 'OSS ' . $this->signature->getAccessKeyId() . ':' . $authHeader;

        $url = $this->buildUrl($bucket, $key) . '?' . http_build_query($query);

        $startTime = microtime(true);
        try {
            $response = $this->httpClient->request('PUT', $url, [
                'headers' => $headers,
                'body' => $content,
            ]);

            $statusCode = $response->getStatusCode();
            if (200 !== $statusCode) {
                throw new OssException('Failed to upload part', $statusCode, $response->getContent(false));
            }

            $etag = trim($response->getHeaders()['etag'][0] ?? '', '"');
            $requestId = $response->getHeaders()['x-oss-request-id'][0] ?? '';

            $this->logger->info('OSS uploadPart succeeded', [
                'bucket' => $bucket,
                'key' => $key,
                'upload_id' => $uploadId,
                'part_number' => $partNumber,
                'etag' => $etag,
                'size' => strlen($content),
                'request_id' => $requestId,
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $etag;
        } catch (\Exception $e) {
            $this->logger->error('OSS uploadPart failed', [
                'bucket' => $bucket,
                'key' => $key,
                'upload_id' => $uploadId,
                'part_number' => $partNumber,
                'error' => $e->getMessage(),
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            throw $e;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $parts
     */
    public function completeMultipartUpload(string $bucket, string $key, string $uploadId, array $parts): string
    {
        $query = ['uploadId' => $uploadId];

        $partsXml = '';
        foreach ($parts as $part) {
            $partsXml .= sprintf(
                '<Part><PartNumber>%d</PartNumber><ETag>"%s"</ETag></Part>',
                $part['part_number'],
                $part['etag']
            );
        }

        $body = "<CompleteMultipartUpload>{$partsXml}</CompleteMultipartUpload>";

        $headers = [
            'content-type' => 'application/xml',
            'content-length' => (string) strlen($body),
            'date' => gmdate('D, d M Y H:i:s \G\M\T'),
        ];

        $authHeader = $this->signature->signRequest('POST', $bucket, $key, $headers, $query);
        $headers['authorization'] = 'OSS ' . $this->signature->getAccessKeyId() . ':' . $authHeader;

        $url = $this->buildUrl($bucket, $key) . '?' . http_build_query($query);

        $startTime = microtime(true);
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            if (200 !== $statusCode) {
                throw new OssException('Failed to complete multipart upload', $statusCode, $response->getContent(false));
            }

            $content = $response->getContent();
            $xml = simplexml_load_string($content);
            $etag = trim($this->getXmlProperty($xml, 'ETag'), '"');
            $requestId = $response->getHeaders()['x-oss-request-id'][0] ?? '';

            $this->logger->info('OSS completeMultipartUpload succeeded', [
                'bucket' => $bucket,
                'key' => $key,
                'upload_id' => $uploadId,
                'part_count' => count($parts),
                'etag' => $etag,
                'request_id' => $requestId,
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $etag;
        } catch (\Exception $e) {
            $this->logger->error('OSS completeMultipartUpload failed', [
                'bucket' => $bucket,
                'key' => $key,
                'upload_id' => $uploadId,
                'part_count' => count($parts),
                'error' => $e->getMessage(),
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            throw $e;
        }
    }

    public function abortMultipartUpload(string $bucket, string $key, string $uploadId): void
    {
        $query = ['uploadId' => $uploadId];

        $headers = [
            'date' => gmdate('D, d M Y H:i:s \G\M\T'),
        ];

        $authHeader = $this->signature->signRequest('DELETE', $bucket, $key, $headers, $query);
        $headers['authorization'] = 'OSS ' . $this->signature->getAccessKeyId() . ':' . $authHeader;

        $url = $this->buildUrl($bucket, $key) . '?' . http_build_query($query);

        $startTime = microtime(true);
        try {
            $response = $this->httpClient->request('DELETE', $url, [
                'headers' => $headers,
            ]);

            $statusCode = $response->getStatusCode();
            if (204 !== $statusCode) {
                throw new OssException('Failed to abort multipart upload', $statusCode, $response->getContent(false));
            }

            $requestId = $response->getHeaders()['x-oss-request-id'][0] ?? '';

            $this->logger->info('OSS abortMultipartUpload succeeded', [
                'bucket' => $bucket,
                'key' => $key,
                'upload_id' => $uploadId,
                'request_id' => $requestId,
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('OSS abortMultipartUpload failed', [
                'bucket' => $bucket,
                'key' => $key,
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            throw $e;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listParts(string $bucket, string $key, string $uploadId): array
    {
        $query = ['uploadId' => $uploadId];

        $headers = [
            'date' => gmdate('D, d M Y H:i:s \G\M\T'),
        ];

        $authHeader = $this->signature->signRequest('GET', $bucket, $key, $headers, $query);
        $headers['authorization'] = 'OSS ' . $this->signature->getAccessKeyId() . ':' . $authHeader;

        $url = $this->buildUrl($bucket, $key) . '?' . http_build_query($query);

        $startTime = microtime(true);
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
            ]);

            $statusCode = $response->getStatusCode();
            if (200 !== $statusCode) {
                throw new OssException('Failed to list parts', $statusCode, $response->getContent(false));
            }

            $content = $response->getContent();
            $xml = simplexml_load_string($content);

            $parts = [];
            if (isset($xml->Part)) {
                foreach ($xml->Part as $part) {
                    $parts[] = [
                        'part_number' => (int) $part->PartNumber,
                        'etag' => trim((string) $part->ETag, '"'),
                        'size' => (int) $part->Size,
                        'last_modified' => (string) $part->LastModified,
                    ];
                }
            }

            $requestId = $response->getHeaders()['x-oss-request-id'][0] ?? '';

            $this->logger->info('OSS listParts succeeded', [
                'bucket' => $bucket,
                'key' => $key,
                'upload_id' => $uploadId,
                'part_count' => count($parts),
                'request_id' => $requestId,
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $parts;
        } catch (\Exception $e) {
            $this->logger->error('OSS listParts failed', [
                'bucket' => $bucket,
                'key' => $key,
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            throw $e;
        }
    }

    /**
     * @param array<string, string> $headers
     */
    public function generatePresignedUrl(string $method, string $bucket, string $key, int $expires, array $headers = []): string
    {
        $queryString = $this->signature->generatePresignedUrl($method, $bucket, $key, $expires, $headers);

        return $this->buildUrl($bucket, $key) . '?' . $queryString;
    }

    /**
     * @param array<int, mixed> $conditions
     * @return array<string, mixed>
     */
    public function generatePostPolicy(string $bucket, string $keyPrefix, int $expires, array $conditions = []): array
    {
        $policy = $this->signature->generatePostPolicy($bucket, $keyPrefix, $expires, $conditions);
        $policy['host'] = $this->buildUrl($bucket, '');

        return $policy;
    }
}
