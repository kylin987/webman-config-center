<?php

namespace app\admin\controller;

use app\common\server\AdminAuthServer;
use InvalidArgumentException;
use support\Request;
use support\Response;

class AuthController
{
    public function login(Request $request): Response
    {
        try {
            return json(['code' => 0, 'data' => (new AdminAuthServer())->login((string) $request->post('username'), (string) $request->post('password'))]);
        } catch (InvalidArgumentException $exception) {
            return json(['code' => 401, 'message' => $exception->getMessage()])->withStatus(401);
        }
    }

    public function verifyMfa(Request $request): Response
    {
        try {
            return json(['code' => 0, 'data' => (new AdminAuthServer())->verifyMfa((string) $request->post('challengeToken'), (string) $request->post('code'))]);
        } catch (InvalidArgumentException $exception) {
            return json(['code' => 401, 'message' => $exception->getMessage()])->withStatus(401);
        }
    }

    public function logout(Request $request): Response
    {
        $header = (string) $request->header('authorization', '');
        (new AdminAuthServer())->logout(str_starts_with($header, 'Bearer ') ? substr($header, 7) : '');
        return json(['code' => 0]);
    }
}
