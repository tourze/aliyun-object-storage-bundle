<?php

declare(strict_types=1);

namespace Tourze\AliyunObjectStorageBundle\Adapter;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToProvideChecksum;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use Tourze\AliyunObjectStorageBundle\Contract\OssClientInterface;
use Tourze\AliyunObjectStorageBundle\Exception\OssException;

readonly class AliyunOssAdapter implements FilesystemAdapter
{
    private PathPrefixer $pathPrefixer;

    public function __construct(
        private OssClientInterface $client,
        private string $bucket,
        string $prefix = '',
    ) {
        $this->pathPrefixer = new PathPrefixer($prefix);
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->client->headObject($this->bucket, $this->pathPrefixer->prefixPath($path));

            return true;
        } catch (OssException $e) {
            if (404 === $e->getHttpStatusCode()) {
                return false;
            }
            throw UnableToCheckFileExistence::forLocation($path, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $prefix = $this->pathPrefixer->prefixDirectoryPath($path);
            $result = $this->client->listObjects($this->bucket, $prefix, '', 1);

            return ($result['objects'] ?? []) !== [] || ($result['common_prefixes'] ?? []) !== [];
        } catch (OssException $e) {
            throw UnableToCheckDirectoryExistence::forLocation($path, $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, stream_get_contents($contents), $config);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $headers = [];
            $contentType = $config->get('content_type');
            if (null !== $contentType && '' !== $contentType) {
                $headers['content-type'] = (string) $contentType;
            }

            $this->client->putObject($this->bucket, $this->pathPrefixer->prefixPath($path), $contents, $headers);
        } catch (OssException $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        $content = $this->read($path);
        $stream = fopen('php://temp', 'r+');
        if (false === $stream) {
            throw UnableToReadFile::fromLocation($path, 'failed to create stream');
        }

        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    public function read(string $path): string
    {
        try {
            return $this->client->getObject($this->bucket, $this->pathPrefixer->prefixPath($path));
        } catch (OssException $e) {
            if (404 === $e->getHttpStatusCode()) {
                throw UnableToReadFile::fromLocation($path, 'file not found');
            }
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function delete(string $path): void
    {
        try {
            $this->client->deleteObject($this->bucket, $this->pathPrefixer->prefixPath($path));
        } catch (OssException $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $prefix = $this->pathPrefixer->prefixDirectoryPath($path);
            $continuationToken = '';

            do {
                $result = $this->client->listObjects($this->bucket, $prefix, '', 1000, $continuationToken);

                foreach ($result['objects'] as $object) {
                    $this->client->deleteObject($this->bucket, $object['key']);
                }

                $continuationToken = $result['next_continuation_token'] ?? '';
            } while ($result['is_truncated']);
        } catch (OssException $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $dirPath = $this->pathPrefixer->prefixDirectoryPath($path);
            if (!str_ends_with($dirPath, '/')) {
                $dirPath .= '/';
            }
            $this->client->putObject($this->bucket, $dirPath, '', ['content-type' => 'application/x-directory']);
        } catch (OssException $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'OSS does not support visibility');
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            $metadata = $this->client->headObject($this->bucket, $this->pathPrefixer->prefixPath($path));

            return new FileAttributes($path, null, null, null, $metadata['content_type']);
        } catch (OssException $e) {
            if (404 === $e->getHttpStatusCode()) {
                throw UnableToRetrieveMetadata::mimeType($path, 'file not found');
            }
            throw UnableToRetrieveMetadata::mimeType($path, $e->getMessage(), $e);
        }
    }

    public function lastModified(string $path): FileAttributes
    {
        try {
            $metadata = $this->client->headObject($this->bucket, $this->pathPrefixer->prefixPath($path));
            $timestamp = strtotime($metadata['last_modified']);

            return new FileAttributes($path, null, null, $timestamp);
        } catch (OssException $e) {
            if (404 === $e->getHttpStatusCode()) {
                throw UnableToRetrieveMetadata::lastModified($path, 'file not found');
            }
            throw UnableToRetrieveMetadata::lastModified($path, $e->getMessage(), $e);
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        try {
            $metadata = $this->client->headObject($this->bucket, $this->pathPrefixer->prefixPath($path));

            return new FileAttributes($path, $metadata['size']);
        } catch (OssException $e) {
            if (404 === $e->getHttpStatusCode()) {
                throw UnableToRetrieveMetadata::fileSize($path, 'file not found');
            }
            throw UnableToRetrieveMetadata::fileSize($path, $e->getMessage(), $e);
        }
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $prefix = $this->pathPrefixer->prefixDirectoryPath($path);
            $delimiter = $deep ? '' : '/';
            $continuationToken = '';

            do {
                $result = $this->client->listObjects($this->bucket, $prefix, $delimiter, 1000, $continuationToken);

                yield from $this->processObjectList($result['objects'], $path);

                if (!$deep) {
                    yield from $this->processCommonPrefixes($result['common_prefixes'], $path);
                }

                $continuationToken = $result['next_continuation_token'] ?? '';
            } while ($result['is_truncated']);
        } catch (OssException $e) {
            return;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $objects
     * @return iterable<FileAttributes>
     */
    private function processObjectList(array $objects, string $path): iterable
    {
        foreach ($objects as $object) {
            $objectPath = $this->pathPrefixer->stripPrefix($object['key']);
            if ('' !== $objectPath && $objectPath !== $path) {
                yield new FileAttributes(
                    $objectPath,
                    $object['size'],
                    null,
                    strtotime($object['last_modified'])
                );
            }
        }
    }

    /**
     * @param array<int, string> $commonPrefixes
     * @return iterable<DirectoryAttributes>
     */
    private function processCommonPrefixes(array $commonPrefixes, string $path): iterable
    {
        foreach ($commonPrefixes as $commonPrefix) {
            $dirPath = $this->pathPrefixer->stripDirectoryPrefix($commonPrefix);
            if ('' !== $dirPath && $dirPath !== $path) {
                yield new DirectoryAttributes($dirPath);
            }
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $sourcePath = $this->pathPrefixer->prefixPath($source);
            $destinationPath = $this->pathPrefixer->prefixPath($destination);

            $this->client->copyObject($this->bucket, $destinationPath, $this->bucket, $sourcePath);
            $this->client->deleteObject($this->bucket, $sourcePath);
        } catch (OssException $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $sourcePath = $this->pathPrefixer->prefixPath($source);
            $destinationPath = $this->pathPrefixer->prefixPath($destination);

            $this->client->copyObject($this->bucket, $destinationPath, $this->bucket, $sourcePath);
        } catch (OssException $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function checksum(string $path, Config $config): string
    {
        try {
            $metadata = $this->client->headObject($this->bucket, $this->pathPrefixer->prefixPath($path));

            return $metadata['etag'];
        } catch (OssException $e) {
            if (404 === $e->getHttpStatusCode()) {
                throw new UnableToProvideChecksum('file not found', $path);
            }
            throw new UnableToProvideChecksum($e->getMessage(), $path, $e);
        }
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
    {
        $expires = $expiresAt->getTimestamp();

        return $this->client->generatePresignedUrl('GET', $this->bucket, $this->pathPrefixer->prefixPath($path), $expires);
    }
}
