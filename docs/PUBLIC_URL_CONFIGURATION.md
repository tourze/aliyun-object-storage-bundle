# 阿里云 OSS 公共 URL 配置指南

## 概述

本文档说明如何配置阿里云 OSS 适配器以支持生成公共访问 URL。支持两种常见场景：
- 使用 OSS 原生域名（包含 bucket）
- 使用 CDN 或自定义域名（CNAME）

当启用本 Bundle 后，FileService（或上层业务）可将上传文件的公共访问 URL 写入实体字段（如 `publicUrl`）。

## 环境变量

```bash
# 必需（用于上传）
ALIYUN_OSS_ACCESS_KEY_ID=your-access-key-id
ALIYUN_OSS_ACCESS_KEY_SECRET=your-secret
ALIYUN_OSS_BUCKET=your-bucket

# 访问域名配置（任选其一或同时配置）
ALIYUN_OSS_ENDPOINT=oss-cn-hangzhou.aliyuncs.com  # 原生域名
ALIYUN_OSS_PUBLIC_DOMAIN=cdn.example.com          # CNAME/自定义域名
ALIYUN_OSS_CNAME_ENABLED=true                     # 使用 CNAME 逻辑生成 URL

# 可选
ALIYUN_OSS_PREFIX=uploads
ALIYUN_OSS_REGION=cn-hangzhou
ALIYUN_OSS_INTERNAL=false                         # true 切换内网 endpoint（如 ecs 内网）
```

## URL 生成规则

### 方式一：使用 CNAME/自定义域名（推荐）

当配置了 `ALIYUN_OSS_PUBLIC_DOMAIN` 且 `ALIYUN_OSS_CNAME_ENABLED=true` 时：

生成格式：
```
https://cdn.example.com/uploads/2025/07/document.pdf
```

说明：
- 需要在 OSS 控制台完成域名绑定与验证
- URL 中不包含 bucket 前缀，由 CNAME 解析至桶
- 结合 CDN 可获得更佳性能与缓存能力

### 方式二：使用 OSS 原生域名

若未启用 CNAME 或未配置 `ALIYUN_OSS_PUBLIC_DOMAIN`，则使用 `ALIYUN_OSS_ENDPOINT`：

生成格式：
```
https://<bucket>.oss-cn-hangzhou.aliyuncs.com/uploads/2025/07/document.pdf
```

说明：
- 需确保 endpoint 与桶所在地域一致
- 若处于云上内网环境且 `ALIYUN_OSS_INTERNAL=true`，可切换为内网域名（如 `oss-cn-hangzhou-internal.aliyuncs.com`）

## 注意事项

1. 访问权限：公共 URL 仅在对象具有公共读权限或使用了带签名的临时 URL 时可访问。请根据业务场景配置桶策略/对象 ACL。
2. URL 编码：仅对路径与文件名进行安全编码，保留 `/` 分隔符。
3. 前缀处理：如配置了 `ALIYUN_OSS_PREFIX`，将自动拼接到所有对象 Key 前。
4. 兜底行为：若既未配置 `ALIYUN_OSS_PUBLIC_DOMAIN` 也未配置 `ALIYUN_OSS_ENDPOINT`，将无法生成公共 URL（字段保持为空）。

## 故障排除

若出现“Unable to generate public url”类日志：
- 检查是否配置了 `ALIYUN_OSS_PUBLIC_DOMAIN` 或 `ALIYUN_OSS_ENDPOINT`
- 校验域名格式（无需包含协议前缀），协议自动按 `https` 生成
- 检查 CNAME 绑定是否在 OSS 控制台已完成且生效
- 结合应用日志定位具体错误

## 最佳实践

- 生产环境优先使用 CDN+CNAME 提升性能与稳定性
- 开发/测试环境可直接使用原生 endpoint，配置更简洁
- 对外可见资源使用带缓存策略的域名；敏感资源使用带签名的临时 URL

