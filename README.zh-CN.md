# 阿里云对象存储 Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![构建状态](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?branch=master&style=flat-square)](https://github.com/tourze/php-monorepo/actions)

阿里云 OSS（对象存储服务）的 Flysystem 适配器与 Symfony Bundle 集成，参考 huawei-object-storage-bundle 设计与能力对齐。

## 目录

- 功能特性
- 系统要求
- 安装
- 配置
- 快速开始
- 支持的操作
- 高级用法
- 公共 URL 配置
- 安全性
- 注意事项
- 测试
- 贡献与许可证
- 开发需求与 API 参考

## 功能特性

- 完整实现 Flysystem v3 FilesystemAdapter 接口
- 支持基本文件操作（读取、写入、删除、复制、移动）
- 支持目录操作（虚拟目录）
- 文件元数据获取（大小、MIME 类型、最后修改时间）
- 流式读写与大文件分段上传（Multipart）
- 预签名 URL、POST Policy 表单直传签名
- 支持 OSS 追加写（AppendObject）
- 支持桶与对象常用管理能力（ACL、CORS、生命周期、版本、网站托管、日志、加密、跨区域复制、标签等）
- 通过环境变量零配置集成，自动装饰 FileStorageBundle 的 FilesystemFactory

## 系统要求

- PHP 8.1+
- Symfony 6.4+
- league/flysystem 3.10+
- 推荐：symfony/http-client 用于 HTTP 实现（或官方 SDK 作为可替代实现）

## 安装

```bash
composer require tourze/aliyun-object-storage-bundle
```

启用（当使用 Symfony Flex 时通常可省略）：

```php
// config/bundles.php
return [
    // ... 其他 bundles
    Tourze\AliyunObjectStorageBundle\AliyunObjectStorageBundle::class => ['all' => true],
];
```

## 配置

通过环境变量进行配置（运行时读取，不写入配置文件）：

```bash
# 必需
ALIYUN_OSS_ACCESS_KEY_ID=你的AK
ALIYUN_OSS_ACCESS_KEY_SECRET=你的SK
ALIYUN_OSS_BUCKET=你的桶名

# 推荐/可选
ALIYUN_OSS_REGION=cn-hangzhou
ALIYUN_OSS_ENDPOINT=oss-cn-hangzhou.aliyuncs.com
ALIYUN_OSS_PREFIX=uploads

# 公网访问域名策略（用于生成 publicUrl）
ALIYUN_OSS_PUBLIC_DOMAIN=cdn.example.com   # 自定义域名/CDN 域名
ALIYUN_OSS_CNAME_ENABLED=true              # 启用 CNAME 时，生成 URL 不含 bucket 前缀

# 内网或加速（根据部署环境二选一）
ALIYUN_OSS_INTERNAL=false                  # true 使用内网 endpoint（如 ecs 内网）
```

当必需变量齐备时，Bundle 会自动装饰 FileStorageBundle 的 FilesystemFactory，使其返回基于 OSS 的适配器；否则回退到默认实现。

## 快速开始

```php
use Tourze\FileStorageBundle\Factory\FilesystemFactoryInterface;

$filesystem = $container->get(FilesystemFactoryInterface::class)->createFilesystem();

// 写入
$filesystem->write('path/to/file.txt', 'Hello OSS');

// 读取
$content = $filesystem->read('path/to/file.txt');

// 删除
$filesystem->delete('path/to/file.txt');

// 列目录
foreach ($filesystem->listContents('path/to')->toArray() as $item) {
    // ...
}
```

## 支持的操作

- 文件：read/write/delete/copy/move
- 目录：list/create/delete（虚拟目录）
- 元数据：fileSize/lastModified/mimeType
- 流操作：readStream/writeStream
- 分段上传：initiate/uploadPart/complete/abort/listParts
- 追加写：appendObject（Aliyun 专有）
- 预签名：generatePresignedUrl、生成 POST Policy

## 高级用法

- 使用流写入/读取以降低内存占用
- 分段上传适合大文件、弱网与断点续传
- 可选启用 CNAME 与 CDN，自定义公网访问域名
- 支持生成带有效期与内容约束的临时上传策略（POST 表单）

## 公共 URL 配置

详见 docs/PUBLIC_URL_CONFIGURATION.md。

## 安全性

- 使用环境变量管理 AK/SK，不写入代码库
- 使用 RAM 最小权限原则并定期轮换密钥
- 全链路 HTTPS，必要时启用服务端加密（SSE-OSS/SSE-KMS）
- 针对上传策略限制条件（大小、MIME、Key 前缀）

## 注意事项

1. 目录为逻辑概念，通过对象 Key 前缀模拟
2. 使用 CNAME 时需在阿里云 OSS 控制台完成域名绑定与验证
3. 内网 endpoint 仅在云上内网环境生效
4. 若未配置公共域名与 endpoint，将不会生成 publicUrl

## 测试

```bash
./vendor/bin/phpunit packages/aliyun-object-storage-bundle/tests
```

## 贡献与许可证

- 欢迎提交 Issue/PR 以完善功能与文档
- MIT 许可证，详见 LICENSE

## 开发需求与 API 参考

开发者请阅读《开发需求.md》，其中包含：

- 详细功能清单与非功能性要求
- 设计约束与架构方案
- 配置项、服务与接口说明
- 完整 API 参考（桶/对象/分段/签名/高级特性）