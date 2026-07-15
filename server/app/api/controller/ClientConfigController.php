<?php

namespace app\api\controller;

use app\common\server\ClientConfigServer;
use InvalidArgumentException;
use support\Request;
use support\Response;

class ClientConfigController
{
    public function show(Request $request): Response
    {
        try {
            $token = (new ClientConfigServer())->authenticate($this->bearerToken($request));
            $namespace = (string) ($request->get('namespace') ?: config('config-center.default_namespace'));
            $group = (string) $request->get('group');
            $dataId = (string) $request->get('dataId');
            if ($group === '' || $dataId === '') {
                return json(['code' => 400, 'message' => 'group 和 dataId 为必填项'], 400);
            }
            return json(['code' => 0, 'data' => (new ClientConfigServer())->read($token, $namespace, $group, $dataId)])
                ->withHeader('Cache-Control', 'no-store');
        } catch (InvalidArgumentException $exception) {
            $status = str_contains($exception->getMessage(), '令牌') ? 401 : 403;
            return json(['code' => $status, 'message' => $exception->getMessage()], $status)->withHeader('Cache-Control', 'no-store');
        }
    }

    private function bearerToken(Request $request): string
    {
        $header = (string) $request->header('authorization', '');
        return str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';
    }
}

