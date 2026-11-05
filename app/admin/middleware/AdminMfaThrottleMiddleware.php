<?php

namespace app\admin\middleware;

use app\common\server\ClientIpWhitelistServer;
use support\Log;
use support\Redis;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use Throwable;

class AdminMfaThrottleMiddleware implements MiddlewareInterface
{
    public function process(\Webman\Http\Request $request, callable $handler): Response
    {
        $ip = (new ClientIpWhitelistServer())->clientIp($request);
        $key = 'config-center:admin-mfa:ip:' . hash('sha256', $ip);
        try {
            // INCR 与首次 EXPIRE 必须原子执行，进程中断不能留下无过期计数。
            $limit = Redis::eval(<<<'LUA'
local count = redis.call('INCR', KEYS[1])
if count == 1 then redis.call('EXPIRE', KEYS[1], 60) end
return {count, redis.call('TTL', KEYS[1])}
LUA, 1, $key);
        } catch (Throwable $exception) {
            Log::error('admin mfa ip throttle unavailable', ['exception_class' => get_class($exception)]);
            return json(['code' => 503, 'message' => '二次验证暂不可用，请稍后重试'])
                ->withStatus(503)->withHeader('Cache-Control', 'no-store');
        }
        if ((int) $limit[0] > 30) {
            // 每个窗口只记第一次拦截，避免恶意请求刷日志。
            if ((int) $limit[0] === 31) {
                Log::warning('admin mfa ip throttled', ['ip' => $ip]);
            }
            return json(['code' => 429, 'message' => '验证请求过于频繁，请稍后重试'])
                ->withStatus(429)->withHeader('Retry-After', (string) max(1, (int) $limit[1]))
                ->withHeader('Cache-Control', 'no-store');
        }
        return $handler($request);
    }
}
