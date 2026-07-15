# yhs/webman-config-center

面向 Webman 的缓存优先配置客户端。它将远端配置写入项目显式声明的白名单文件，远端异常时保留旧文件，不抛出会结束业务 worker 的异常。

业务项目在 `config/config-center.php` 中声明服务地址、读取令牌、Redis TLS 地址、状态目录和监听项；每项必须声明本地相对路径与期望格式。

`config-center-sync` 用于 initContainer 或启动前同步。`config-center-listen` 和 `config-center-poll` 必须运行在 sidecar 中，不能在业务 worker 内直接运行阻塞订阅。订阅异常会退避重连；轮询负责补偿 Pub/Sub 断线期间遗漏的事件。

业务容器可在独立 Webman process 的定时器中调用 `ApplyAdapter::consume()`。它只接受共享状态目录中带 HMAC 的请求，并且只会执行项目本地监听映射声明的 `reload_command`。
