# yhs/webman-config-center

面向 Webman 的缓存优先配置客户端。它将远端配置写入项目显式声明的白名单文件，远端异常时保留旧文件，不抛出会结束业务 worker 的异常。

首版提供同步核心和 CLI 同步入口。业务项目在 `config/config-center.php` 中声明服务地址、读取令牌、状态目录和监听项；每项必须声明本地相对路径与期望格式。

`config-center-sync` 只用于 initContainer 或启动前同步。运行时 Redis 订阅 sidecar 会在服务端 API 和部署模板完成后接入，不能在业务 worker 内直接运行阻塞订阅。

