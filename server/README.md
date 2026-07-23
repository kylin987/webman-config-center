# Config Center Server

这是轻量配置中心的 Webman 服务端项目。

推荐从仓库根目录阅读完整运行说明：

```text
../README.md
```

快速启动：

```bash
composer install
cp .env.example .env
php start.php start
```

启动前请先创建 MySQL 数据库并导入：

```bash
mysql -h127.0.0.1 -P3306 -u config_center -p config_center < ../sql/001_config_center.sql
mysql -h127.0.0.1 -P3306 -u config_center -p config_center < ../sql/002_admin_session.sql
```

访问：

```text
http://127.0.0.1:8787/
```
