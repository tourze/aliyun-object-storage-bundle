<?php

declare(strict_types=1);

namespace Tourze\AliyunObjectStorageBundle\Signature;

use Tourze\AliyunObjectStorageBundle\Exception\OssException;

class OssSignature
{
    public function __construct(
        private readonly string $accessKeyId,
        private readonly string $accessKeySecret,
    ) {
    }

    public function getAccessKeyId(): string
    {
        return $this->accessKeyId;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string|int> $query
     */
    public function signRequest(
        string $method,
        string $bucket,
        string $key,
        array $headers = [],
        array $query = [],
    ): string {
        $canonicalizedResource = $this->canonicalizeResource($bucket, $key, $query);
        $canonicalizedHeaders = $this->canonicalizeHeaders($headers);

        $stringToSign = implode("\n", [
            strtoupper($method),
            $headers['content-md5'] ?? '',
            $headers['content-type'] ?? '',
            $headers['date'] ?? '',
            $canonicalizedHeaders . $canonicalizedResource,
        ]);

        return base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));
    }

    /**
     * @param array<string, string|int> $query
     */
    private function canonicalizeResource(string $bucket, string $key, array $query = []): string
    {
        $resource = '/' . $bucket . '/' . $key;

        if ([] === $query) {
            return $resource;
        }

        $ossParams = [];
        $ossParamNames = [
            'acl', 'uploads', 'location', 'cors', 'logging', 'website', 'referer',
            'lifecycle', 'delete', 'append', 'tagging', 'objectMeta',
            'uploadId', 'partNumber', 'security-token', 'position',
            'img', 'style', 'styleName', 'replication', 'replicationProgress',
            'replicationLocation', 'cname', 'bucketInfo', 'comp', 'qos',
            'live', 'status', 'vod', 'startTime', 'endTime', 'symlink',
            'x-oss-process', 'response-content-type', 'response-content-language',
            'response-expires', 'response-cache-control', 'response-content-disposition',
            'response-content-encoding',
        ];

        foreach ($query as $queryKey => $value) {
            if (in_array(strtolower($queryKey), $ossParamNames, true)) {
                if ('' === $value || null === $value) {
                    $ossParams[] = $queryKey;
                } else {
                    $ossParams[] = $queryKey . '=' . $value;
                }
            }
        }

        if ([] !== $ossParams) {
            sort($ossParams);
            $resource .= '?' . implode('&', $ossParams);
        }

        return $resource;
    }

    /**
     * @param array<string, string> $headers
     */
    private function canonicalizeHeaders(array $headers): string
    {
        $canonicalizedHeaders = [];

        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if (str_starts_with($lowerKey, 'x-oss-')) {
                $canonicalizedHeaders[$lowerKey] = trim($value);
            }
        }

        if ([] === $canonicalizedHeaders) {
            return '';
        }

        ksort($canonicalizedHeaders);

        $headerStrings = [];
        foreach ($canonicalizedHeaders as $key => $value) {
            $headerStrings[] = $key . ':' . $value;
        }

        return implode("\n", $headerStrings) . "\n";
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string|int> $query
     */
    public function generatePresignedUrl(
        string $method,
        string $bucket,
        string $key,
        int $expires,
        array $headers = [],
        array $query = [],
    ): string {
        $query['OSSAccessKeyId'] = $this->accessKeyId;
        $query['Expires'] = $expires;

        $canonicalizedResource = $this->canonicalizeResource($bucket, $key, $query);
        $canonicalizedHeaders = $this->canonicalizeHeaders($headers);

        $stringToSign = implode("\n", [
            strtoupper($method),
            $headers['content-md5'] ?? '',
            $headers['content-type'] ?? '',
            (string) $expires,
            $canonicalizedHeaders . $canonicalizedResource,
        ]);

        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));
        $query['Signature'] = $signature;

        return http_build_query($query);
    }

    /**
     * @param array<int, mixed> $conditions
     * @return array<string, mixed>
     */
    public function generatePostPolicy(
        string $bucket,
        string $keyPrefix,
        int $expires,
        array $conditions = [],
    ): array {
        $expiration = gmdate('Y-m-d\TH:i:s.000\Z', $expires);

        $policyConditions = [
            ['bucket' => $bucket],
            ['starts-with', '$key', $keyPrefix],
        ];

        foreach ($conditions as $condition) {
            $policyConditions[] = $condition;
        }

        $policy = [
            'expiration' => $expiration,
            'conditions' => $policyConditions,
        ];

        $policyJson = json_encode($policy);
        if (false === $policyJson) {
            throw new OssException('Failed to encode policy as JSON');
        }
        $policyBase64 = base64_encode($policyJson);
        $signature = base64_encode(hash_hmac('sha1', $policyBase64, $this->accessKeySecret, true));

        return [
            'policy' => $policyBase64,
            'signature' => $signature,
            'accessKeyId' => $this->accessKeyId,
            'expires' => $expires,
        ];
    }
}
