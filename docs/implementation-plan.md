# 实施计划

## 第一阶段：服务端闭环

- MySQL 表：当前配置、历史、客户端令牌范围和事务 outbox。
- 管理发布 API：格式校验、乐观锁、事务写入和 outbox。
- 客户端读取 API：Bearer 令牌与逐配置项授权。
- outbox 独立进程：向 Redis 发布可重复的变更事件。

## 第二阶段：客户端包

- Guzzle 读取、严格格式校验、白名单路径和原子写入。
- 保存每项下载版本，避免旧事件覆盖新文件。
- 启动前同步 CLI，供 initContainer 或非 Kubernetes 启动脚本调用。

## 第三阶段：运行时更新

- 独立 sidecar Redis 订阅器和 60 秒补偿同步。
- 共享卷 apply request 与业务容器应用适配器。
- 仅允许本地声明的 reload/回调，回调失败可重试。

## 第四阶段：管理体验和部署

- 单管理员登录、配置列表、编辑、历史、回滚和令牌管理页面。
- ACK Deployment、Service、Ingress、Secret、initContainer 和 sidecar 模板。
- 导入 Nacos 数据并做逐项校验后，按项目迁移。

