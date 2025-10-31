<?php

namespace Tourze\AliyunObjectStorageBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\AliyunObjectStorageBundle\Exception\OssException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(OssException::class)]
class OssExceptionTest extends AbstractExceptionTestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $exception = new OssException('Test message', 404, 'Response body', null);

        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame(404, $exception->getCode());
        $this->assertSame(404, $exception->getHttpStatusCode());
        $this->assertSame('Response body', $exception->getResponseBody());
    }

    public function testConstructorWithDefaults(): void
    {
        $exception = new OssException('Test message');

        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame(0, $exception->getHttpStatusCode());
        $this->assertSame('', $exception->getResponseBody());
    }

    public function testConstructorWithPreviousException(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new OssException('Test message', 500, 'Error response', $previous);

        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame(500, $exception->getCode());
        $this->assertSame(500, $exception->getHttpStatusCode());
        $this->assertSame('Error response', $exception->getResponseBody());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
