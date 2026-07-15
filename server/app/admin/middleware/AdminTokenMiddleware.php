<?php

namespace app\admin\middleware;

use support\Request;
use support\Response;
use Webman\MiddlewareInterface;

class AdminTokenMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $configured = (string) getenv('CONFIG_CENTER_ADMIN_API_TOKEN');
        $provided = (string) $request->header('x-admin-token', '');
        if ($configured === '' || $provided === '' || !hash_equals($configured, $provided)) {
            return json(['code' => 401, 'message' => '管理端认证失败'], 401)->withHeader('Cache-Control', 'no-store');
        }
        return $handler($request);
    }
}

