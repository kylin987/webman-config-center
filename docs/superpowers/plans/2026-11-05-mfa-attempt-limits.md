# MFA Attempt Limits Implementation Plan

**Goal:** 修复 E-codex-09，限制公开 MFA 登录验证码尝试。
**Architecture:** AdminAuthServer 负责 MySQL 事务、账号与 challenge 计数；独立路由中间件负责 Redis IP 节流。
**Tech Stack:** PHP 8.1 / Webman / Illuminate ORM / MySQL / Redis。
**Spec:** docs/superpowers/specs/2026-11-05-mfa-attempt-limits-design.md

## Constraints
不修改客户端包；不连接业务数据库；不执行线上 SQL、不提交推送。

## Steps
- [x] 新增 sql/006_admin_mfa_attempt_limits.sql：challenge failed_attempts、账号 mfa_failed_attempts/mfa_failure_window_at/mfa_locked_until。
- [x] 修改 AdminAuthServer::verifyMfa，账号锁在前，challenge 重读锁在后；失败事务返回错误，提交后抛出；成功删除挑战并创建 session。
- [x] 修改 createMfaChallenge，使换发与验证采用一致账号锁，锁定期间拒绝发放，计数保留。
- [x] 新增 AdminMfaThrottleMiddleware，仅绑定公开 verify，Redis INCR+EXPIRE Lua 保证原子性，超过 30/60 返回 429。
- [x] 新增 tests/mfa_attempt_limits.php，使用隔离数据库验证阈值与并发；新增 IP 节流测试，README 补充增量 SQL 与运行依赖。
- [x] PHP lint、运行测试、审查 diff，记录验证边界。

## Validation

- `python3 tests/run_mfa_limits.py`: PASS，121 项检查；独立 MySQL 9.6 / Redis，含并发 worker、事务回滚与 Redis 停机返回 503。
- 独立静态审查无阻断问题；未执行线上迁移、未提交推送。
