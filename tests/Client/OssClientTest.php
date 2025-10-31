<?php

namespace Tourze\AliyunObjectStorageBundle\Tests\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tourze\AliyunObjectStorageBundle\Client\OssClient;
use Tourze\AliyunObjectStorageBundle\Exception\OssException;
use Tourze\AliyunObjectStorageBundle\Signature\OssSignature;

/**
 * @internal
 */
#[CoversClass(OssClient::class)]
class OssClientTest extends TestCase
{
    private OssClient $client;

    private MockHttpClient $httpClient;

    private OssSignature $signature;

    public function testPutObjectSuccess(): void
    {
        $this->httpClient->setResponseFactory([
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => [
                    'etag' => ['"abc123"'],
                    'x-oss-request-id' => ['request-id-123'],
                ],
            ]),
        ]);

        $etag = $this->client->putObject('test-bucket', 'test-key', 'test content');

        $this->assertSame('abc123', $etag);
    }

    public function testPutObjectFailure(): void
    {
        $this->httpClient->setResponseFactory([
            new MockResponse('Error response', [
                'http_code' => 400,
            ]),
        ]);

        $this->expectException(OssException::class);
        $this->expectExceptionMessage('Failed to put object');

        $this->client->putObject('test-bucket', 'test-key', 'test content');
    }

    public function testGetObjectSuccess(): void
    {
        $this->httpClient->setResponseFactory([
            new MockResponse('file content', [
                'http_code' => 200,
                'response_headers' => [
                    'x-oss-request-id' => ['request-id-123'],
                ],
            ]),
        ]);

        $content = $this->client->getObject('test-bucket', 'test-key');

        $this->assertSame('file content', $content);
    }

    public function testGetObjectNotFound(): void
    {
        $this->httpClient->setResponseFactory([
            new MockResponse('Not found', [
                'http_code' => 404,
            ]),
        ]);

        $this->expectException(OssException::class);
        $this->expectExceptionMessage('Object not found');

        $this->client->getObject('test-bucket', 'test-key');
    }

    public function testHeadObjectSuccess(): void
    {
        $this->httpClient->setResponseFactory([
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => [
                    'content-length' => ['1024'],
                    'etag' => ['"abc123"'],
                    'last-modified' => ['Wed, 28 Dec 2022 12:00:00 GMT'],
                    'content-type' => ['text/plain'],
                    'x-oss-meta-user' => ['test-user'],
                    'x-oss-request-id' => ['request-id-123'],
                ],
            ]),
        ]);

        $metadata = $this->client->headObject('test-bucket', 'test-key');

        $this->assertSame(1024, $metadata['size']);
        $this->assertSame('abc123', $metadata['etag']);
        $this->assertSame('Wed, 28 Dec 2022 12:00:00 GMT', $metadata['last_modified']);
        $this->assertSame('text/plain', $metadata['content_type']);
        $this->assertArrayHasKey('user_metadata', $metadata);
        $this->assertSame('test-user', $metadata['user_metadata']['user']);
    }

    public function testDeleteObjectSuccess(): void
    {
        $this->httpClient->setResponseFactory([
            new MockResponse('', [
                'http_code' => 204,
                'response_headers' => [
                    'x-oss-request-id' => ['request-id-123'],
                ],
            ]),
        ]);

        // Should not throw exception
        $this->client->deleteObject('test-bucket', 'test-key');

        // Verify HTTP request was made by checking request count
        $this->assertSame(1, $this->httpClient->getRequestsCount());
    }

    public function testGeneratePresignedUrl(): void
    {
        $expires = time() + 3600;
        $url = $this->client->generatePresignedUrl('GET', 'test-bucket', 'test-key', $expires);

        $this->assertStringContainsString('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/test-key', $url);
        $this->assertStringContainsString('OSSAccessKeyId=testAccessKeyId', $url);
        $this->assertStringContainsString('Expires=' . $expires, $url);
        $this->assertStringContainsString('Signature=', $url);
    }

    public function testGeneratePostPolicy(): void
    {
        $expires = time() + 3600;
        $conditions = [
            ['content-length-range', 0, 10485760],
        ];

        $policy = $this->client->generatePostPolicy('test-bucket', 'uploads/', $expires, $conditions);

        $this->assertArrayHasKey('policy', $policy);
        $this->assertArrayHasKey('signature', $policy);
        $this->assertArrayHasKey('accessKeyId', $policy);
        $this->assertArrayHasKey('host', $policy);
        $this->assertSame('testAccessKeyId', $policy['accessKeyId']);
        $this->assertSame('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/', $policy['host']);
    }

    public function testListObjectsSuccess(): void
    {
        $xmlResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <ListBucketResult>
            <IsTruncated>false</IsTruncated>
            <Contents>
                <Key>test1.txt</Key>
                <LastModified>2022-12-28T12:00:00.000Z</LastModified>
                <ETag>"abc123"</ETag>
                <Size>1024</Size>
                <StorageClass>Standard</StorageClass>
            </Contents>
            <CommonPrefixes>
                <Prefix>uploads/</Prefix>
            </CommonPrefixes>
        </ListBucketResult>';

        $this->httpClient->setResponseFactory([
            new MockResponse($xmlResponse, [
                'http_code' => 200,
                'response_headers' => [
                    'x-oss-request-id' => ['request-id-123'],
                ],
            ]),
        ]);

        $result = $this->client->listObjects('test-bucket', '', '/', 1000, '');

        $this->assertArrayHasKey('objects', $result);
        $this->assertArrayHasKey('common_prefixes', $result);
        $this->assertArrayHasKey('is_truncated', $result);
        $this->assertCount(1, $result['objects']);
        $this->assertSame('test1.txt', $result['objects'][0]['key']);
        $this->assertSame(1024, $result['objects'][0]['size']);
        $this->assertCount(1, $result['common_prefixes']);
        $this->assertSame('uploads/', $result['common_prefixes'][0]);
        $this->assertFalse($result['is_truncated']);
    }

    public function testCopyObjectSuccess(): void
    {
        $xmlResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <CopyObjectResult>
            <ETag>"abc123"</ETag>
            <LastModified>2022-12-28T12:00:00.000Z</LastModified>
        </CopyObjectResult>';

        $this->httpClient->setResponseFactory([
            new MockResponse($xmlResponse, [
                'http_code' => 200,
                'response_headers' => [
                    'x-oss-request-id' => ['request-id-123'],
                ],
            ]),
        ]);

        $etag = $this->client->copyObject('dest-bucket', 'dest-key', 'src-bucket', 'src-key');

        $this->assertSame('abc123', $etag);
    }

    public function testInitiateMultipartUploadSuccess(): void
    {
        $xmlResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <InitiateMultipartUploadResult>
            <Bucket>test-bucket</Bucket>
            <Key>test-key</Key>
            <UploadId>upload123</UploadId>
        </InitiateMultipartUploadResult>';

        $this->httpClient->setResponseFactory([
            new MockResponse($xmlResponse, [
                'http_code' => 200,
                'response_headers' => [
                    'x-oss-request-id' => ['request-id-123'],
                ],
            ]),
        ]);

        $uploadId = $this->client->initiateMultipartUpload('test-bucket', 'test-key');

        $this->assertSame('upload123', $uploadId);
    }

    public function testUploadPartSuccess(): void
    {
        $this->httpClient->setResponseFactory([
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => [
                    'etag' => ['"part-etag"'],
                    'x-oss-request-id' => ['request-id-123'],
                ],
            ]),
        ]);

        $etag = $this->client->uploadPart('test-bucket', 'test-key', 'upload123', 1, 'part content');

        $this->assertSame('part-etag', $etag);
    }

    public function testCompleteMultipartUploadSuccess(): void
    {
        $xmlResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <CompleteMultipartUploadResult>
            <Location>https://test-bucket.oss-cn-hangzhou.aliyuncs.com/test-key</Location>
            <Bucket>test-bucket</Bucket>
            <Key>test-key</Key>
            <ETag>"final-etag"</ETag>
        </CompleteMultipartUploadResult>';

        $this->httpClient->setResponseFactory([
            new MockResponse($xmlResponse, [
                'http_code' => 200,
                'response_headers' => [
                    'x-oss-request-id' => ['request-id-123'],
                ],
            ]),
        ]);

        $parts = [
            ['part_number' => 1, 'etag' => 'etag1'],
            ['part_number' => 2, 'etag' => 'etag2'],
        ];

        $etag = $this->client->completeMultipartUpload('test-bucket', 'test-key', 'upload123', $parts);

        $this->assertSame('final-etag', $etag);
    }

    public function testAbortMultipartUploadSuccess(): void
    {
        $this->httpClient->setResponseFactory([
            new MockResponse('', [
                'http_code' => 204,
                'response_headers' => [
                    'x-oss-request-id' => ['request-id-123'],
                ],
            ]),
        ]);

        // Should not throw exception
        $this->client->abortMultipartUpload('test-bucket', 'test-key', 'upload123');

        // Verify HTTP request was made by checking request count
        $this->assertSame(1, $this->httpClient->getRequestsCount());
    }

    public function testListPartsSuccess(): void
    {
        $xmlResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <ListPartsResult>
            <Bucket>test-bucket</Bucket>
            <Key>test-key</Key>
            <UploadId>upload123</UploadId>
            <PartNumberMarker>0</PartNumberMarker>
            <NextPartNumberMarker>2</NextPartNumberMarker>
            <MaxParts>1000</MaxParts>
            <IsTruncated>false</IsTruncated>
            <Part>
                <PartNumber>1</PartNumber>
                <LastModified>2022-12-28T12:00:00.000Z</LastModified>
                <ETag>"etag1"</ETag>
                <Size>1024</Size>
            </Part>
            <Part>
                <PartNumber>2</PartNumber>
                <LastModified>2022-12-28T12:01:00.000Z</LastModified>
                <ETag>"etag2"</ETag>
                <Size>2048</Size>
            </Part>
        </ListPartsResult>';

        $this->httpClient->setResponseFactory([
            new MockResponse($xmlResponse, [
                'http_code' => 200,
                'response_headers' => [
                    'x-oss-request-id' => ['request-id-123'],
                ],
            ]),
        ]);

        $parts = $this->client->listParts('test-bucket', 'test-key', 'upload123');

        $this->assertCount(2, $parts);
        $this->assertSame(1, $parts[0]['part_number']);
        $this->assertSame('etag1', $parts[0]['etag']);
        $this->assertSame(1024, $parts[0]['size']);
        $this->assertSame(2, $parts[1]['part_number']);
        $this->assertSame('etag2', $parts[1]['etag']);
        $this->assertSame(2048, $parts[1]['size']);
    }

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->signature = new OssSignature('testAccessKeyId', 'testAccessKeySecret');
        $this->client = new OssClient($this->httpClient, $this->signature, 'oss-cn-hangzhou.aliyuncs.com', new NullLogger());
    }
}
