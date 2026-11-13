# Client Authentication Cache Implementation Plan

**Goal:** 减少客户端轮询重复密码校验 CPU，同时保持账号变更即时生效。
**Architecture:** 在 ClientConfigServer 中添加私有成功密码校验缓存；认证仍每次查询 MySQL 并保存使用时间。
**Tech Stack:** PHP 8.1、Webman、Illuminate ORM、PHP HMAC、单调时钟。
**Spec:** docs/superpowers/specs/2026-11-13-client-auth-cache-design.md

## Constraints
仅修改服务端认证热点，不改客户端协议、管理员认证、数据库结构；不推送部署。

## Steps
- [x] 新增 tests/client_auth_cache.php：内存 SQLite 账号表，代理真实 password_verify 计数；验证重复请求只校验一次、错误密码不命中、改密/禁用/删除/改名即时生效、到期及容量淘汰。
- [x] ClientConfigServer 增加固定 60 秒、上限 1024 个账号的私有 static 缓存；HMAC 摘要绑定当前密码哈希与提交密码。
- [x] 同一测试脚本比较原认证与命中路径的 40 次耗时；两者均包含相同 ORM 查询及 save。
- [x] 更新 README，运行回归、PHP lint、diff 检查及独立代码审查。

## Validation

- `php tests/client_auth_cache.php`：25 项检查通过；PHP 8.1.34、内存 SQLite、真实 bcrypt。
- 40 次重复认证（bcrypt cost=10）：原路径 CPU 2172.77ms / wall 2173.34ms，缓存命中 CPU 2.19ms / wall 2.19ms；包含相同 ORM 查询和 last_used_at 保存。
- 两个变更 PHP 文件 lint、git diff --check 通过；独立代码审查通过。
- 缓存只规定有效期，不承诺过期摘要立即从内存擦除；不是进程内存泄露防护。线上 CPU 降幅需部署后测量。
- 无 SQL 迁移、未提交/推送/部署。
