# Webman Config Center

轻量配置中心服务端。

本仓库只包含配置中心服务端：管理后台、客户端读取 API、MySQL 表结构和 Docker 运行文件。

配套客户端 Composer 包仓库：[kylin987/webman-config-center-client](https://github.com/kylin987/webman-config-center-client)

## 功能

- 配置管理：新增、编辑、详情、删除、历史版本、发布历史版本为最新版本。
- 批量迁移：支持批量导出 zip，支持导入本系统 zip 和 Nacos 导出的配置 zip。
- 客户端账号：通过账号密码给业务项目读取配置。
- 客户端 IP 白名单：只限制客户端读取 API，内网网段默认放行，外网 IP 可在后台手动添加。
- 数据持久化：MySQL 保存配置、历史版本、账号和登录会话。
- 变更通知：发布配置后写入 outbox，并通过 Redis Pub/Sub 广播变更事件。
- 轻量运行：基于 Webman，默认 2 个 HTTP worker + 1 个 outbox 进程。

## 目录

```text
app/                     Webman 应用代码
config/                  Webman 配置
public/                  后台静态页面和前端依赖
sql/                     MySQL 初始化 SQL
```

## 本地运行

要求：

- PHP >= 8.1
- Composer
- MySQL 5.7+/8.0+
- Redis

进入项目目录：

```bash
composer install
cp .env.example .env
```

修改 `.env`：

```dotenv
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3306
MYSQL_DATABASE=config_center
MYSQL_USERNAME=config_center
MYSQL_PASSWORD=your-password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DATABASE=0
REDIS_PASSWORD=

CONFIG_CENTER_BOOTSTRAP_USERNAME=admin
CONFIG_CENTER_BOOTSTRAP_PASSWORD=replace-with-a-random-secret
CONFIG_CENTER_ADMIN_PATH=/cc-admin
CONFIG_CENTER_CLIENT_IP_WHITELIST_ENABLE=1
CONFIG_CENTER_CLIENT_IP_WHITELIST_LOG_CHANNEL=default
APP_DEBUG=0
WEBMAN_HTTP_WORKERS=2
```

初始化数据库：

```bash
mysql -h127.0.0.1 -P3306 -u config_center -p config_center < sql/001_config_center.sql
mysql -h127.0.0.1 -P3306 -u config_center -p config_center < sql/002_admin_session.sql
mysql -h127.0.0.1 -P3306 -u config_center -p config_center < sql/003_client_ip_whitelist.sql
```

启动服务：

```bash
php start.php start
```

访问：

```text
http://127.0.0.1:8787/cc-admin/
```

管理后台默认不挂在根目录，默认目录为 `/cc-admin`。如需修改，可调整 `.env`：

```dotenv
CONFIG_CENTER_ADMIN_PATH=/your-admin-path
```

修改后重启服务生效。根目录 `/` 默认返回 404，避免随便访问域名根路径就进入管理后台。

健康检查：

```bash
curl http://127.0.0.1:8787/health
```

第一次登录时，如果 `cc_admin_user` 表为空，系统会使用 `.env` 中的 `CONFIG_CENTER_BOOTSTRAP_USERNAME` 和 `CONFIG_CENTER_BOOTSTRAP_PASSWORD` 创建管理员账号。

MFA 二次验证默认不开启。管理员登录后台后，可在“个人中心”手动开启 MFA；开启时支持扫码添加，也保留手动密钥兜底。开启后，该管理员后续登录需要输入验证器中的 6 位动态验证码。关闭 MFA 时也必须输入当前动态验证码。

如果管理员丢失验证器，需要在可信环境中手动重置 MFA：

```sql
UPDATE cc_admin_user
SET mfa_enabled = 0, mfa_secret = NULL, mfa_enabled_at = NULL
WHERE username = 'admin';

DELETE FROM cc_admin_mfa_challenge
WHERE admin_user_id = (SELECT id FROM cc_admin_user WHERE username = 'admin');
```

重置后，该管理员会回到未开启 MFA 状态，可在个人中心重新开启。

## 批量导入和导出

管理后台的“配置管理”页面支持：

- 批量导出：导出当前 namespace 下所有配置，zip 内路径为 `Group/Data ID`。
- 批量导入：上传 zip 后按文件路径导入配置。
- Nacos 兼容：Nacos 导出的 zip 通常是 `group/dataId` 结构，例如 `yhs/yhs-mysql.php`，系统会自动映射为 `Group = yhs`、`Data ID = yhs-mysql.php`。

导入规则：

- 已存在且内容相同：跳过。
- 已存在但内容不同：发布为新版本，并保留历史版本。
- 不存在：新增配置，初始版本为 `r1`。
- `.php`、`.json`、`.yaml`、`.yml`、`.ini` 会按对应格式校验；其他后缀按 `txt` 保存。

## Docker 运行

```bash
docker build -t webman-config-center:latest .
docker run -d --name webman-config-center \
  --env-file .env \
  -p 8787:8787 \
  webman-config-center:latest
```

## 客户端读取 API

客户端通过 Basic Auth 读取配置：

```text
GET /api/client/v1/config?namespace=public&group=DEFAULT_GROUP&dataId=app.php
```

客户端账号在管理后台的“客户端账号”菜单中创建。

客户端读取 API 会校验 IP 白名单。系统内置放行本机和常见内网网段：

```text
127.0.0.1/32
::1/128
10.0.0.0/8
172.16.0.0/12
192.168.0.0/16
100.64.0.0/10
fc00::/7
fe80::/10
```

ACK 内部、ECS 内网通常无需额外配置；外网业务项目需要在后台“IP 白名单”菜单手动添加公网 IP 或 CIDR。白名单不限制管理后台，避免误配置导致管理员无法登录。

如果服务端前面挂了阿里云 CDN，系统会优先读取请求头 `ali-cdn-real-ip` 作为客户端真实 IP；没有该请求头时再读取 `X-Forwarded-For`、`X-Real-IP` 和连接 IP。

如需临时关闭客户端 IP 白名单，可在 `.env` 中设置：

```dotenv
CONFIG_CENTER_CLIENT_IP_WHITELIST_ENABLE=0
```

关闭后客户端读取 API 不再校验 IP，只校验客户端账号密码。

客户端 IP 被白名单拦截时会写 warning 日志，日志 channel 默认是 `default`。如果线上日志 channel 使用 `star`，可设置：

```dotenv
CONFIG_CENTER_CLIENT_IP_WHITELIST_LOG_CHANNEL=star
```

业务项目推荐使用独立客户端 Composer 包：

```bash
composer require kylin987/webman-config-center-client
```

客户端仓库：[kylin987/webman-config-center-client](https://github.com/kylin987/webman-config-center-client)

## 注意

- 不要提交 `.env`。
- Redis 只用于变更通知，配置权威数据在 MySQL。
- 如果 Redis 短暂不可用，配置发布仍会写入 MySQL，outbox 进程会继续重试广播。
- 默认资源占用较低，生产环境可按实际流量配置较小 CPU/内存规格起步。
