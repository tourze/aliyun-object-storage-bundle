<?php

namespace Tourze\AliyunObjectStorageBundle\Tests\Adapter;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AliyunObjectStorageBundle\Adapter\AliyunOssAdapter;
use Tourze\AliyunObjectStorageBundle\Contract\OssClientInterface;
use Tourze\AliyunObjectStorageBundle\Exception\OssException;

/**
 * @internal
 */
#[CoversClass(AliyunOssAdapter::class)]
class AliyunOssAdapterTest extends TestCase
{
    private OssClientInterface $client;

    private AliyunOssAdapter $adapter;

    public function testFileExists(): void
    {
        $this->client
            ->expects($this->once())
            ->method('headObject')
            ->with('test-bucket', 'test/file.txt')
            ->willReturn(['size' => 123])
        ;

        $result = $this->adapter->fileExists('test/file.txt');

        $this->assertTrue($result);
    }

    public function testFileExistsReturnsFalseOn404(): void
    {
        $this->client
            ->expects($this->once())
            ->method('headObject')
            ->with('test-bucket', 'test/file.txt')
            ->willThrowException(new OssException('Not found', 404))
        ;

        $result = $this->adapter->fileExists('test/file.txt');

        $this->assertFalse($result);
    }

    public function testWrite(): void
    {
        $this->client
            ->expects($this->once())
            ->method('putObject')
            ->with('test-bucket', 'test/file.txt', 'content', [])
            ->willReturn('etag123')
        ;

        $this->adapter->write('test/file.txt', 'content', new Config());
    }

    public function testWriteWithContentType(): void
    {
        $this->client
            ->expects($this->once())
            ->method('putObject')
            ->with('test-bucket', 'test/file.txt', 'content', ['content-type' => 'text/plain'])
            ->willReturn('etag123')
        ;

        $config = new Config(['content_type' => 'text/plain']);
        $this->adapter->write('test/file.txt', 'content', $config);
    }

    public function testRead(): void
    {
        $this->client
            ->expects($this->once())
            ->method('getObject')
            ->with('test-bucket', 'test/file.txt')
            ->willReturn('file content')
        ;

        $result = $this->adapter->read('test/file.txt');

        $this->assertSame('file content', $result);
    }

    public function testDelete(): void
    {
        $this->client
            ->expects($this->once())
            ->method('deleteObject')
            ->with('test-bucket', 'test/file.txt')
        ;

        $this->adapter->delete('test/file.txt');
    }

    public function testCopy(): void
    {
        $this->client
            ->expects($this->once())
            ->method('copyObject')
            ->with('test-bucket', 'dest/file.txt', 'test-bucket', 'src/file.txt')
            ->willReturn('etag123')
        ;

        $this->adapter->copy('src/file.txt', 'dest/file.txt', new Config());
    }

    public function testMove(): void
    {
        $this->client
            ->expects($this->once())
            ->method('copyObject')
            ->with('test-bucket', 'dest/file.txt', 'test-bucket', 'src/file.txt')
            ->willReturn('etag123')
        ;

        $this->client
            ->expects($this->once())
            ->method('deleteObject')
            ->with('test-bucket', 'src/file.txt')
        ;

        $this->adapter->move('src/file.txt', 'dest/file.txt', new Config());
    }

    public function testFileSize(): void
    {
        $this->client
            ->expects($this->once())
            ->method('headObject')
            ->with('test-bucket', 'test/file.txt')
            ->willReturn(['size' => 1024])
        ;

        $result = $this->adapter->fileSize('test/file.txt');

        $this->assertSame(1024, $result->fileSize());
    }

    public function testLastModified(): void
    {
        $lastModified = 'Wed, 28 Dec 2022 12:00:00 GMT';
        $this->client
            ->expects($this->once())
            ->method('headObject')
            ->with('test-bucket', 'test/file.txt')
            ->willReturn(['last_modified' => $lastModified])
        ;

        $result = $this->adapter->lastModified('test/file.txt');

        $this->assertSame(strtotime($lastModified), $result->lastModified());
    }

    public function testMimeType(): void
    {
        $this->client
            ->expects($this->once())
            ->method('headObject')
            ->with('test-bucket', 'test/file.txt')
            ->willReturn(['content_type' => 'text/plain'])
        ;

        $result = $this->adapter->mimeType('test/file.txt');

        $this->assertSame('text/plain', $result->mimeType());
    }

    public function testChecksum(): void
    {
        $this->client
            ->expects($this->once())
            ->method('headObject')
            ->with('test-bucket', 'test/file.txt')
            ->willReturn(['etag' => 'abc123'])
        ;

        $result = $this->adapter->checksum('test/file.txt', new Config());

        $this->assertSame('abc123', $result);
    }

    public function testTemporaryUrl(): void
    {
        $expiresAt = new \DateTime('+1 hour');
        $this->client
            ->expects($this->once())
            ->method('generatePresignedUrl')
            ->with('GET', 'test-bucket', 'test/file.txt', $expiresAt->getTimestamp())
            ->willReturn('https://signed-url')
        ;

        $result = $this->adapter->temporaryUrl('test/file.txt', $expiresAt, new Config());

        $this->assertSame('https://signed-url', $result);
    }

    public function testCreateDirectory(): void
    {
        $this->client
            ->expects($this->once())
            ->method('putObject')
            ->with('test-bucket', 'test/dir/', '', ['content-type' => 'application/x-directory'])
            ->willReturn('etag123')
        ;

        $this->adapter->createDirectory('test/dir', new Config());
    }

    public function testDeleteDirectory(): void
    {
        $this->client
            ->expects($this->once())
            ->method('listObjects')
            ->with('test-bucket', 'test/dir/', '', 1000, '')
            ->willReturn([
                'objects' => [
                    ['key' => 'test/dir/file1.txt'],
                    ['key' => 'test/dir/file2.txt'],
                ],
                'is_truncated' => false,
            ])
        ;

        $this->client
            ->expects($this->exactly(2))
            ->method('deleteObject')
            ->with(
                $this->equalTo('test-bucket'),
                self::callback(function ($path) {
                    return in_array($path, ['test/dir/file1.txt', 'test/dir/file2.txt'], true);
                })
            )
        ;

        $this->adapter->deleteDirectory('test/dir');
    }

    public function testDirectoryExists(): void
    {
        $this->client
            ->expects($this->once())
            ->method('listObjects')
            ->with('test-bucket', 'test/dir/', '', 1)
            ->willReturn([
                'objects' => [['key' => 'test/dir/file.txt']],
                'common_prefixes' => [],
            ])
        ;

        $result = $this->adapter->directoryExists('test/dir');

        $this->assertTrue($result);
    }

    public function testDirectoryExistsReturnsFalseWhenEmpty(): void
    {
        $this->client
            ->expects($this->once())
            ->method('listObjects')
            ->with('test-bucket', 'test/dir/', '', 1)
            ->willReturn([
                'objects' => [],
                'common_prefixes' => [],
            ])
        ;

        $result = $this->adapter->directoryExists('test/dir');

        $this->assertFalse($result);
    }

    public function testListContentsWithFiles(): void
    {
        $this->client
            ->expects($this->once())
            ->method('listObjects')
            ->with('test-bucket', '', '/', 1000, '')
            ->willReturn([
                'objects' => [
                    [
                        'key' => 'file1.txt',
                        'size' => 1024,
                        'last_modified' => '2022-12-28T12:00:00.000Z',
                    ],
                ],
                'common_prefixes' => [],
                'is_truncated' => false,
            ])
        ;

        $contents = iterator_to_array($this->adapter->listContents('', false));

        $this->assertCount(1, $contents);
        $this->assertSame('file1.txt', $contents[0]->path());
        $this->assertInstanceOf(FileAttributes::class, $contents[0]);
        $this->assertSame(1024, $contents[0]->fileSize());
    }

    public function testListContentsWithDirectories(): void
    {
        $this->client
            ->expects($this->once())
            ->method('listObjects')
            ->with('test-bucket', '', '/', 1000, '')
            ->willReturn([
                'objects' => [],
                'common_prefixes' => ['subdir/'],
                'is_truncated' => false,
            ])
        ;

        $contents = iterator_to_array($this->adapter->listContents('', false));

        $this->assertCount(1, $contents);
        $this->assertSame('subdir', $contents[0]->path());
    }

    public function testListContentsDeep(): void
    {
        $this->client
            ->expects($this->once())
            ->method('listObjects')
            ->with('test-bucket', '', '', 1000, '')
            ->willReturn([
                'objects' => [
                    [
                        'key' => 'file.txt',
                        'size' => 1024,
                        'last_modified' => '2022-12-28T12:00:00.000Z',
                    ],
                ],
                'common_prefixes' => [],
                'is_truncated' => false,
            ])
        ;

        $contents = iterator_to_array($this->adapter->listContents('', true));

        $this->assertCount(1, $contents);
        $this->assertSame('file.txt', $contents[0]->path());
    }

    public function testReadStream(): void
    {
        $this->client
            ->expects($this->once())
            ->method('getObject')
            ->with('test-bucket', 'test/file.txt')
            ->willReturn('file content')
        ;

        $stream = $this->adapter->readStream('test/file.txt');

        $this->assertIsResource($stream);
        $this->assertSame('file content', stream_get_contents($stream));
        fclose($stream);
    }

    public function testWriteStream(): void
    {
        $stream = fopen('php://temp', 'r+');
        $this->assertIsResource($stream);
        fwrite($stream, 'stream content');
        rewind($stream);

        $this->client
            ->expects($this->once())
            ->method('putObject')
            ->with('test-bucket', 'test/file.txt', 'stream content', [])
            ->willReturn('etag123')
        ;

        $this->adapter->writeStream('test/file.txt', $stream, new Config());
        fclose($stream);
    }

    public function testVisibility(): void
    {
        $result = $this->adapter->visibility('test/file.txt');

        $this->assertSame('test/file.txt', $result->path());
        $this->assertSame('public', $result->visibility());
    }

    protected function setUp(): void
    {
        $this->client = $this->createMock(OssClientInterface::class);
        $this->adapter = new AliyunOssAdapter($this->client, 'test-bucket');
    }
}
