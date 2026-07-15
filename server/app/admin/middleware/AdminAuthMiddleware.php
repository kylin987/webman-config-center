<?php

namespace app\admin\middleware;

use app\common\server\AdminAuthServer;
use InvalidArgumentException;
use support\Request;
use support\Response;
use Webman\MiddlewareInterface;

class AdminAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $header = (string) $request->header('authorization', '');
        $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';
        try {
            $user = (new AdminAuthServer())->userByToken($token);
            return $handler($request->withAttribute('admin_user', $user));
        } catch (InvalidArgumentException $exception) {
            return json(['code' => 401, 'message' => $exception->getMessage()], 401)->withHeader('Cache-Control', 'no-store');
        }
    }
}

