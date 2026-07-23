# Webman Config Center

轻量配置中心服务端。

本仓库只包含配置中心服务端：管理后台、客户端读取 API、MySQL 表结构和 K8s 部署模板。

配套客户端 Composer 包仓库：[kylin987/webman-config-center-client](https://github.com/kylin987/webman-config-center-client)

## 功能

- 配置管理：新增、编辑、详情、历史版本、发布历史版本为最新版本。
- 客户端账号：通过账号密码给业务项目读取配置。
- 数据持久化：MySQL 保存配置、历史版本、账号和登录会话。
- 变更通知：发布配置后写入 outbox，并通过 Redis Pub/Sub 广播变更事件。
- 轻量运行：基于 Webman，默认 2 个 HTTP worker + 1 个 outbox 进程。

## 目录

```text
server/                  Webman 服务端项目
sql/                     MySQL 初始化 SQL
k8s/config-center.yaml   K8s 部署模板
docs/                    设计和实现说明
```

## 本地运行

要求：

- PHP >= 8.1
- Composer
- MySQL 5.7+/8.0+
- Redis

进入服务端目录：

```bash
cd server
composer install
cp .env.example .env
```

修改 `server/.env`：

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
APP_DEBUG=0
WEBMAN_HTTP_WORKERS=2
```

初始化数据库：

```bash
mysql -h127.0.0.1 -P3306 -u config_center -p config_center < ../sql/001_config_center.sql
mysql -h127.0.0.1 -P3306 -u config_center -p config_center < ../sql/002_admin_session.sql
```

老版本数据库升级需要额外执行：

```bash
mysql -h127.0.0.1 -P3306 -u config_center -p config_center < ../sql/003_admin_mfa.sql
```

启动服务：

```bash
php start.php start
```

访问：

```text
http://127.0.0.1:8787/
```

健康检查：

```bash
curl http://127.0.0.1:8787/health
```

第一次登录时，如果 `cc_admin_user` 表为空，系统会使用 `.env` 中的 `CONFIG_CENTER_BOOTSTRAP_USERNAME` 和 `CONFIG_CENTER_BOOTSTRAP_PASSWORD` 创建管理员账号。

MFA 二次验证默认不开启。管理员登录后台后，可在“个人中心”手动开启 MFA；开启后，该管理员后续登录需要输入验证器中的 6 位动态验证码。关闭 MFA 时也必须输入当前动态验证码。

如果管理员丢失验证器，需要在可信环境中手动重置 MFA：

```sql
UPDATE cc_admin_user
SET mfa_enabled = 0, mfa_secret = NULL, mfa_enabled_at = NULL
WHERE username = 'admin';

DELETE FROM cc_admin_mfa_challenge
WHERE admin_user_id = (SELECT id FROM cc_admin_user WHERE username = 'admin');
```

重置后，该管理员会回到未开启 MFA 状态，可在个人中心重新开启。

## Docker 运行

```bash
cd server
docker build -t webman-config-center:latest .
docker run -d --name webman-config-center \
  --env-file .env \
  -p 8787:8787 \
  webman-config-center:latest
```

## K8s 部署

1. 构建并推送镜像。
2. 修改 `k8s/config-center.yaml` 里的镜像地址。
3. 创建运行 Secret。

示例：

```bash
kubectl create namespace config-center

kubectl -n config-center create secret generic config-center-runtime \
  --from-literal=MYSQL_HOST='your-mysql-host' \
  --from-literal=MYSQL_PORT='3306' \
  --from-literal=MYSQL_DATABASE='config_center' \
  --from-literal=MYSQL_USERNAME='config_center' \
  --from-literal=MYSQL_PASSWORD='your-password' \
  --from-literal=REDIS_HOST='your-redis-host' \
  --from-literal=REDIS_PORT='6379' \
  --from-literal=REDIS_DATABASE='0' \
  --from-literal=REDIS_PASSWORD='' \
  --from-literal=CONFIG_CENTER_BOOTSTRAP_USERNAME='admin' \
  --from-literal=CONFIG_CENTER_BOOTSTRAP_PASSWORD='replace-with-a-random-secret'

kubectl apply -f k8s/config-center.yaml
```

查看状态：

```bash
kubectl -n config-center get pods
kubectl -n config-center logs deploy/config-center
```

## 客户端读取 API

客户端通过 Basic Auth 读取配置：

```text
GET /api/client/v1/config?namespace=public&group=DEFAULT_GROUP&dataId=app.php
```

客户端账号在管理后台的“客户端账号”菜单中创建。

业务项目推荐使用独立客户端 Composer 包：

```bash
composer require kylin987/webman-config-center-client
```

客户端仓库：[kylin987/webman-config-center-client](https://github.com/kylin987/webman-config-center-client)

## 注意

- 不要提交 `server/.env`。
- Redis 只用于变更通知，配置权威数据在 MySQL。
- 如果 Redis 短暂不可用，配置发布仍会写入 MySQL，outbox 进程会继续重试广播。
- 默认资源占用较低，K8s 可从 `100m CPU / 128Mi memory` request 起步。
