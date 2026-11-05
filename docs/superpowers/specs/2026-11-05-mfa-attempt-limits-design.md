# E-codex-09 MFA 限制

用户已确认修复。保留现有登录接口和 600 秒 challenge 有效期。

- 每个登录 challenge 最多失败 5 次，达到后删除。
- 每账号 600 秒窗口内累计失败 10 次，从达到阈值开始锁定 MFA 验证 600 秒。换发 challenge 不重置计数；成功验证清零。
- MySQL 保存 challenge/账号计数；先锁账号，再重新读取并锁定 challenge；挑战消费、会话创建同事务。验证错误作为事务返回值，在提交计数后抛出。
- 公开 MFA verify 按来源 IP 使用 Redis 原子计数，每 60 秒最多 30 次，返回 429；Redis 故障不放行。沿用现有 CDN 真实 IP 识别，源站必须限制可信回源。
- 审计仅记录账号 ID、challenge ID、来源 IP、事件、次数，不记录 OTP、密钥、原始 token。
- 提供增量 SQL；不执行线上迁移、不提交或推送；保留 packages/ 和 server/。
- 使用隔离数据库测试阈值、窗口恢复、换发不清零、过期、成功/重复使用和并发；SQLite 不作为 MySQL 行锁证明。
