<?php

namespace app\admin\middleware;

use app\common\server\AdminAuthServer;
use InvalidArgumentException;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class AdminAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $header = (string) $request->header('authorization', '');
        $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';
        try {
            $user = (new AdminAuthServer())->userByToken($token);
            if (method_exists($request, 'setAttribute')) {
                $request->setAttribute('admin_user', $user);
            }
            return $handler($request);
        } catch (InvalidArgumentException $exception) {
            return json(['code' => 401, 'message' => $exception->getMessage()])->withStatus(401)->withHeader('Cache-Control', 'no-store');
        }
    }
}
