# Aliyun Object Storage Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?branch=master&style=flat-square)](https://github.com/tourze/php-monorepo/actions)

A Flysystem adapter and Symfony Bundle integration for Aliyun OSS (Object Storage Service), designed with reference to the huawei-object-storage-bundle for capability alignment.

## Table of Contents

- Features
- Requirements
- Installation
- Configuration
- Quick Start
- Supported Operations
- Advanced Usage
- Public URL Configuration
- Security
- Notes
- Testing
- Contributing & License
- Development Requirements & API Reference

## Features

- Full implementation of Flysystem v3 FilesystemAdapter interface
- Support for basic file operations (read, write, delete, copy, move)
- Support for directory operations (virtual directories)
- File metadata retrieval (size, MIME type, last modified time)
- Stream-based read/write and large file multipart upload
- Presigned URLs and POST Policy form upload signatures
- Support for OSS AppendObject operation
- Support for common bucket and object management capabilities (ACL, CORS, lifecycle, versioning, website hosting, logging, encryption, cross-region replication, tagging, etc.)
- Zero-configuration integration through environment variables, automatically decorates FileStorageBundle's FilesystemFactory

## Requirements

- PHP 8.1+
- Symfony 6.4+
- league/flysystem 3.10+
- Recommended: symfony/http-client for HTTP implementation (or official SDK as alternative)

## Installation

```bash
composer require tourze/aliyun-object-storage-bundle
```

Enable (usually can be omitted when using Symfony Flex):

```php
// config/bundles.php
return [
    // ... other bundles
    Tourze\AliyunObjectStorageBundle\AliyunObjectStorageBundle::class => ['all' => true],
];
```

## Configuration

Configure through environment variables (read at runtime, not written to config files):

```bash
# Required
ALIYUN_OSS_ACCESS_KEY_ID=your-access-key-id
ALIYUN_OSS_ACCESS_KEY_SECRET=your-access-key-secret
ALIYUN_OSS_BUCKET=your-bucket-name

# Recommended/Optional
ALIYUN_OSS_REGION=cn-hangzhou
ALIYUN_OSS_ENDPOINT=oss-cn-hangzhou.aliyuncs.com
ALIYUN_OSS_PREFIX=uploads

# Public domain access policy (for generating publicUrl)
ALIYUN_OSS_PUBLIC_DOMAIN=cdn.example.com   # Custom domain/CDN domain
ALIYUN_OSS_CNAME_ENABLED=true              # When CNAME is enabled, generated URL doesn't include bucket prefix

# Internal network or acceleration (choose one based on deployment environment)
ALIYUN_OSS_INTERNAL=false                  # true to use internal endpoint (like ECS internal network)
```

When required variables are available, the Bundle automatically decorates FileStorageBundle's FilesystemFactory to return an OSS-based adapter; otherwise, it falls back to the default implementation.

## Quick Start

```php
use Tourze\FlysystemBundle\Factory\FilesystemFactoryInterface;

$filesystem = $container->get(FilesystemFactoryInterface::class)->createFilesystem();

// Write
$filesystem->write('path/to/file.txt', 'Hello OSS');

// Read
$content = $filesystem->read('path/to/file.txt');

// Delete
$filesystem->delete('path/to/file.txt');

// List directory
foreach ($filesystem->listContents('path/to')->toArray() as $item) {
    // ...
}
```

## Supported Operations

- Files: read/write/delete/copy/move
- Directories: list/create/delete (virtual directories)
- Metadata: fileSize/lastModified/mimeType
- Stream operations: readStream/writeStream
- Multipart upload: initiate/uploadPart/complete/abort/listParts
- Append write: appendObject (Aliyun-specific)
- Presigning: generatePresignedUrl, generate POST Policy

## Advanced Usage

- Use stream write/read to reduce memory usage
- Multipart upload is suitable for large files, weak networks, and resumable uploads
- Optionally enable CNAME and CDN, customize public access domain
- Support generating temporary upload policies with validity and content constraints (POST forms)

## Public URL Configuration

See docs/PUBLIC_URL_CONFIGURATION.md for details.

## Security

- Use environment variables to manage AK/SK, do not store in code repository
- Follow RAM principle of least privilege and rotate keys regularly
- Use HTTPS throughout the chain, enable server-side encryption when necessary (SSE-OSS/SSE-KMS)
- Limit upload policy conditions (size, MIME, Key prefix)

## Notes

1. Directories are logical concepts, simulated through object Key prefixes
2. When using CNAME, domain binding and verification must be completed in Aliyun OSS console
3. Internal endpoint only works in cloud internal network environment
4. If public domain and endpoint are not configured, publicUrl will not be generated

## Testing

```bash
./vendor/bin/phpunit packages/aliyun-object-storage-bundle/tests
```

## Contributing & License

- Issues and PRs are welcome to improve features and documentation
- MIT License, see LICENSE for details

## Development Requirements & API Reference

Developers should read the "开发需求.md" file, which includes:

- Detailed feature list and non-functional requirements
- Design constraints and architecture solutions
- Configuration items, services, and interface descriptions
- Complete API reference (bucket/object/multipart/signature/advanced features)