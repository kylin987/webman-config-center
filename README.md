# Config Center

轻量配置中心：MySQL 保存权威配置，Redis 仅广播变更事件，业务项目通过 Composer 客户端保留本地配置缓存。

目录说明：

- `server/`：独立 Webman 管理服务和客户端读取 API。
- `packages/webman-config-center/`：业务项目安装的 Webman Composer 包。
- `sql/`：MySQL 表结构。
- `docs/`：设计、部署和操作文档。

当前首版先实现服务端发布/读取闭环和客户端同步基础能力。部署前请阅读 `docs/` 中的设计约束，尤其是 PHP 配置仅支持静态 `return` 字面量。

