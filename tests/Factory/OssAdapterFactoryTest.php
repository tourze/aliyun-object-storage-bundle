<?php

namespace Tourze\AliyunObjectStorageBundle\Tests\Factory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Tourze\AliyunObjectStorageBundle\Adapter\AliyunOssAdapter;
use Tourze\AliyunObjectStorageBundle\Client\OssClient;
use Tourze\AliyunObjectStorageBundle\Factory\OssAdapterFactory;
use Tourze\AliyunObjectStorageBundle\Url\PublicUrlGenerator;

/**
 * @internal
 */
#[CoversClass(OssAdapterFactory::class)]
class OssAdapterFactoryTest extends TestCase
{
    private OssAdapterFactory $factory;

    /** @var array<string, string> */
    private array $originalEnv = [];

    public function testCreateAdapterWithRequiredEnv(): void
    {
        $_ENV['ALIYUN_OSS_ACCESS_KEY_ID'] = 'test-access-key-id';
        $_ENV['ALIYUN_OSS_ACCESS_KEY_SECRET'] = 'test-access-key-secret';
        $_ENV['ALIYUN_OSS_BUCKET'] = 'test-bucket';

        $adapter = $this->factory->createAdapter();

        $this->assertNotNull($adapter);
        $this->assertInstanceOf(AliyunOssAdapter::class, $adapter);
    }

    public function testCreateAdapterWithMissingAccessKeyId(): void
    {
        $_ENV['ALIYUN_OSS_ACCESS_KEY_SECRET'] = 'test-access-key-secret';
        $_ENV['ALIYUN_OSS_BUCKET'] = 'test-bucket';
        unset($_ENV['ALIYUN_OSS_ACCESS_KEY_ID']);

        $adapter = $this->factory->createAdapter();

        $this->assertNull($adapter);
    }

    public function testCreateAdapterWithMissingAccessKeySecret(): void
    {
        $_ENV['ALIYUN_OSS_ACCESS_KEY_ID'] = 'test-access-key-id';
        $_ENV['ALIYUN_OSS_BUCKET'] = 'test-bucket';
        unset($_ENV['ALIYUN_OSS_ACCESS_KEY_SECRET']);

        $adapter = $this->factory->createAdapter();

        $this->assertNull($adapter);
    }

    public function testCreateAdapterWithMissingBucket(): void
    {
        $_ENV['ALIYUN_OSS_ACCESS_KEY_ID'] = 'test-access-key-id';
        $_ENV['ALIYUN_OSS_ACCESS_KEY_SECRET'] = 'test-access-key-secret';
        unset($_ENV['ALIYUN_OSS_BUCKET']);

        $adapter = $this->factory->createAdapter();

        $this->assertNull($adapter);
    }

    public function testCreateUrlGeneratorWithBucket(): void
    {
        $_ENV['ALIYUN_OSS_BUCKET'] = 'test-bucket';

        $urlGenerator = $this->factory->createUrlGenerator();

        $this->assertNotNull($urlGenerator);
        $this->assertInstanceOf(PublicUrlGenerator::class, $urlGenerator);
    }

    public function testCreateUrlGeneratorWithMissingBucket(): void
    {
        unset($_ENV['ALIYUN_OSS_BUCKET']);

        $urlGenerator = $this->factory->createUrlGenerator();

        $this->assertNull($urlGenerator);
    }

    public function testCreateClientWithRequiredEnv(): void
    {
        $_ENV['ALIYUN_OSS_ACCESS_KEY_ID'] = 'test-access-key-id';
        $_ENV['ALIYUN_OSS_ACCESS_KEY_SECRET'] = 'test-access-key-secret';

        $client = $this->factory->createClient();

        $this->assertNotNull($client);
        $this->assertInstanceOf(OssClient::class, $client);
    }

    public function testCreateClientWithMissingCredentials(): void
    {
        unset($_ENV['ALIYUN_OSS_ACCESS_KEY_ID'], $_ENV['ALIYUN_OSS_ACCESS_KEY_SECRET']);

        $client = $this->factory->createClient();

        $this->assertNull($client);
    }

    public function testCreateAdapterWithCustomEndpoint(): void
    {
        $_ENV['ALIYUN_OSS_ACCESS_KEY_ID'] = 'test-access-key-id';
        $_ENV['ALIYUN_OSS_ACCESS_KEY_SECRET'] = 'test-access-key-secret';
        $_ENV['ALIYUN_OSS_BUCKET'] = 'test-bucket';
        $_ENV['ALIYUN_OSS_ENDPOINT'] = 'custom.endpoint.com';

        $adapter = $this->factory->createAdapter();

        $this->assertNotNull($adapter);
    }

    public function testCreateAdapterWithDefaultRegion(): void
    {
        $_ENV['ALIYUN_OSS_ACCESS_KEY_ID'] = 'test-access-key-id';
        $_ENV['ALIYUN_OSS_ACCESS_KEY_SECRET'] = 'test-access-key-secret';
        $_ENV['ALIYUN_OSS_BUCKET'] = 'test-bucket';
        unset($_ENV['ALIYUN_OSS_REGION'], $_ENV['ALIYUN_OSS_ENDPOINT']);

        $adapter = $this->factory->createAdapter();

        $this->assertNotNull($adapter);
    }

    public function testCreateAdapterWithCustomRegion(): void
    {
        $_ENV['ALIYUN_OSS_ACCESS_KEY_ID'] = 'test-access-key-id';
        $_ENV['ALIYUN_OSS_ACCESS_KEY_SECRET'] = 'test-access-key-secret';
        $_ENV['ALIYUN_OSS_BUCKET'] = 'test-bucket';
        $_ENV['ALIYUN_OSS_REGION'] = 'us-west-1';

        $adapter = $this->factory->createAdapter();

        $this->assertNotNull($adapter);
    }

    protected function setUp(): void
    {
        $this->factory = new OssAdapterFactory(new MockHttpClient(), new NullLogger());

        $this->originalEnv = [
            'ALIYUN_OSS_ACCESS_KEY_ID' => $_ENV['ALIYUN_OSS_ACCESS_KEY_ID'] ?? null,
            'ALIYUN_OSS_ACCESS_KEY_SECRET' => $_ENV['ALIYUN_OSS_ACCESS_KEY_SECRET'] ?? null,
            'ALIYUN_OSS_BUCKET' => $_ENV['ALIYUN_OSS_BUCKET'] ?? null,
            'ALIYUN_OSS_REGION' => $_ENV['ALIYUN_OSS_REGION'] ?? null,
            'ALIYUN_OSS_ENDPOINT' => $_ENV['ALIYUN_OSS_ENDPOINT'] ?? null,
            'ALIYUN_OSS_PREFIX' => $_ENV['ALIYUN_OSS_PREFIX'] ?? null,
            'ALIYUN_OSS_PUBLIC_DOMAIN' => $_ENV['ALIYUN_OSS_PUBLIC_DOMAIN'] ?? null,
            'ALIYUN_OSS_CNAME_ENABLED' => $_ENV['ALIYUN_OSS_CNAME_ENABLED'] ?? null,
            'ALIYUN_OSS_INTERNAL' => $_ENV['ALIYUN_OSS_INTERNAL'] ?? null,
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if (null === $value) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
    }
}
