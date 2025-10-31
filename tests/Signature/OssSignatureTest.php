<?php

namespace Tourze\AliyunObjectStorageBundle\Tests\Signature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AliyunObjectStorageBundle\Signature\OssSignature;

/**
 * @internal
 */
#[CoversClass(OssSignature::class)]
class OssSignatureTest extends TestCase
{
    private OssSignature $signature;

    public function testGetAccessKeyId(): void
    {
        $this->assertSame('testAccessKeyId', $this->signature->getAccessKeyId());
    }

    public function testSignRequest(): void
    {
        $headers = [
            'date' => 'Wed, 28 Dec 2022 12:00:00 GMT',
            'content-type' => 'application/octet-stream',
        ];

        $signature = $this->signature->signRequest('PUT', 'test-bucket', 'test-key.txt', $headers);

        $this->assertNotEmpty($signature);
        $this->assertTrue(false !== base64_decode($signature, true));
    }

    public function testSignRequestWithCustomHeaders(): void
    {
        $headers = [
            'date' => 'Wed, 28 Dec 2022 12:00:00 GMT',
            'content-type' => 'application/json',
            'x-oss-meta-user' => 'test-user',
            'x-oss-server-side-encryption' => 'AES256',
        ];

        $signature = $this->signature->signRequest('PUT', 'test-bucket', 'test-key.json', $headers);

        $this->assertNotEmpty($signature);
    }

    public function testGeneratePresignedUrl(): void
    {
        $expires = time() + 3600;
        $queryString = $this->signature->generatePresignedUrl('GET', 'test-bucket', 'test-key.txt', $expires);

        parse_str($queryString, $query);

        $this->assertArrayHasKey('OSSAccessKeyId', $query);
        $this->assertArrayHasKey('Expires', $query);
        $this->assertArrayHasKey('Signature', $query);
        $this->assertSame('testAccessKeyId', $query['OSSAccessKeyId']);
        $this->assertSame((string) $expires, $query['Expires']);
        $signature = $query['Signature'];
        $this->assertIsString($signature);
        $this->assertNotFalse(base64_decode($signature, true));
    }

    public function testGeneratePostPolicy(): void
    {
        $expires = time() + 3600;
        $conditions = [
            ['content-length-range', 0, 10485760],
            ['starts-with', '$Content-Type', 'image/'],
        ];

        $policy = $this->signature->generatePostPolicy('test-bucket', 'uploads/', $expires, $conditions);

        $this->assertArrayHasKey('policy', $policy);
        $this->assertArrayHasKey('signature', $policy);
        $this->assertArrayHasKey('accessKeyId', $policy);
        $this->assertArrayHasKey('expires', $policy);
        $this->assertSame('testAccessKeyId', $policy['accessKeyId']);
        $this->assertSame($expires, $policy['expires']);

        $this->assertTrue(false !== base64_decode($policy['policy'], true));
        $this->assertTrue(false !== base64_decode($policy['signature'], true));

        $decodedPolicy = json_decode(base64_decode($policy['policy'], true), true);
        $this->assertArrayHasKey('expiration', $decodedPolicy);
        $this->assertIsArray($decodedPolicy);
        $this->assertArrayHasKey('conditions', $decodedPolicy);
        $this->assertIsArray($decodedPolicy['conditions']);
    }

    public function testCanonicalizeResourceWithQuery(): void
    {
        $query = [
            'uploadId' => 'test-upload-id',
            'partNumber' => '1',
            'response-content-type' => 'text/plain',
        ];

        $signature = $this->signature->signRequest('PUT', 'test-bucket', 'test-key.txt', ['date' => 'Wed, 28 Dec 2022 12:00:00 GMT'], $query);

        $this->assertNotEmpty($signature);
    }

    public function testSignatureConsistency(): void
    {
        $headers = [
            'date' => 'Wed, 28 Dec 2022 12:00:00 GMT',
            'content-type' => 'text/plain',
        ];

        $signature1 = $this->signature->signRequest('GET', 'test-bucket', 'test-key.txt', $headers);
        $signature2 = $this->signature->signRequest('GET', 'test-bucket', 'test-key.txt', $headers);

        $this->assertSame($signature1, $signature2);
    }

    protected function setUp(): void
    {
        $this->signature = new OssSignature('testAccessKeyId', 'testAccessKeySecret');
    }
}
