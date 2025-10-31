<?php

namespace Tourze\AliyunObjectStorageBundle\Tests\Url;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AliyunObjectStorageBundle\Url\PublicUrlGenerator;

/**
 * @internal
 */
#[CoversClass(PublicUrlGenerator::class)]
class PublicUrlGeneratorTest extends TestCase
{
    public function testGenerateUrlWithoutCname(): void
    {
        $generator = new PublicUrlGenerator('oss-cn-hangzhou.aliyuncs.com', 'test-bucket');
        $url = $generator->generateUrl('path/to/file.jpg');

        $this->assertSame('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/path/to/file.jpg', $url);
    }

    public function testGenerateUrlWithPrefix(): void
    {
        $generator = new PublicUrlGenerator('oss-cn-hangzhou.aliyuncs.com', 'test-bucket', 'uploads');
        $url = $generator->generateUrl('path/to/file.jpg');

        $this->assertSame('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/uploads/path/to/file.jpg', $url);
    }

    public function testGenerateUrlWithCname(): void
    {
        $generator = new PublicUrlGenerator(
            'oss-cn-hangzhou.aliyuncs.com',
            'test-bucket',
            '',
            'cdn.example.com',
            true
        );
        $url = $generator->generateUrl('path/to/file.jpg');

        $this->assertSame('https://cdn.example.com/path/to/file.jpg', $url);
    }

    public function testGenerateUrlWithCnameAndPrefix(): void
    {
        $generator = new PublicUrlGenerator(
            'oss-cn-hangzhou.aliyuncs.com',
            'test-bucket',
            'uploads',
            'cdn.example.com',
            true
        );
        $url = $generator->generateUrl('path/to/file.jpg');

        $this->assertSame('https://cdn.example.com/uploads/path/to/file.jpg', $url);
    }

    public function testGenerateUrlWithInternal(): void
    {
        $generator = new PublicUrlGenerator(
            'oss-cn-hangzhou.aliyuncs.com',
            'test-bucket',
            '',
            null,
            false,
            true
        );
        $url = $generator->generateUrl('path/to/file.jpg');

        $this->assertSame('https://test-bucket.oss-cn-hangzhou-internal.aliyuncs.com/path/to/file.jpg', $url);
    }

    public function testGenerateUrlWithAlreadyInternalEndpoint(): void
    {
        $generator = new PublicUrlGenerator(
            'oss-cn-hangzhou-internal.aliyuncs.com',
            'test-bucket',
            '',
            null,
            false,
            true
        );
        $url = $generator->generateUrl('path/to/file.jpg');

        $this->assertSame('https://test-bucket.oss-cn-hangzhou-internal.aliyuncs.com/path/to/file.jpg', $url);
    }

    public function testGenerateSecureUrl(): void
    {
        $generator = new PublicUrlGenerator('oss-cn-hangzhou.aliyuncs.com', 'test-bucket');
        $expires = time() + 3600;
        $url = $generator->generateSecureUrl('path/to/file.jpg', $expires);

        $this->assertSame('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/path/to/file.jpg', $url);
    }

    public function testGetHostWithoutCname(): void
    {
        $generator = new PublicUrlGenerator('oss-cn-hangzhou.aliyuncs.com', 'test-bucket');
        $host = $generator->getHost();

        $this->assertSame('test-bucket.oss-cn-hangzhou.aliyuncs.com', $host);
    }

    public function testGetHostWithCname(): void
    {
        $generator = new PublicUrlGenerator(
            'oss-cn-hangzhou.aliyuncs.com',
            'test-bucket',
            '',
            'cdn.example.com',
            true
        );
        $host = $generator->getHost();

        $this->assertSame('cdn.example.com', $host);
    }

    public function testGetHostWithInternal(): void
    {
        $generator = new PublicUrlGenerator(
            'oss-cn-hangzhou.aliyuncs.com',
            'test-bucket',
            '',
            null,
            false,
            true
        );
        $host = $generator->getHost();

        $this->assertSame('test-bucket.oss-cn-hangzhou-internal.aliyuncs.com', $host);
    }

    public function testHandleEmptyKey(): void
    {
        $generator = new PublicUrlGenerator('oss-cn-hangzhou.aliyuncs.com', 'test-bucket');
        $url = $generator->generateUrl('');

        $this->assertSame('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/', $url);
    }

    public function testHandleKeyWithLeadingSlash(): void
    {
        $generator = new PublicUrlGenerator('oss-cn-hangzhou.aliyuncs.com', 'test-bucket');
        $url = $generator->generateUrl('/path/to/file.jpg');

        $this->assertSame('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/path/to/file.jpg', $url);
    }
}
