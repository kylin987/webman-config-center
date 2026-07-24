<?php

namespace app\api\controller;

use app\common\server\ClientConfigServer;
use app\common\server\ClientIpWhitelistServer;
use InvalidArgumentException;
use support\Request;
use support\Response;

class ClientConfigController
{
    public function show(Request $request): Response
    {
        try {
            (new ClientIpWhitelistServer())->assertAllowed($request);
            [$username, $password] = $this->clientCredentials($request);
            $account = (new ClientConfigServer())->authenticate($username, $password);
            $namespace = (string) ($request->get('namespace') ?: config('config-center.default_namespace'));
            $group = (string) $request->get('group');
            $dataId = (string) $request->get('dataId');
            if ($group === '' || $dataId === '') {
                return json(['code' => 400, 'message' => 'group 和 dataId 为必填项'])->withStatus(400);
            }
            return json(['code' => 0, 'data' => (new ClientConfigServer())->read($account, $namespace, $group, $dataId)])
                ->withHeader('Cache-Control', 'no-store');
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $status = str_contains($message, '缺少客户端账号') || str_contains($message, '客户端账号或密码错误') ? 401 : (str_contains($message, '不存在') ? 404 : 403);
            return json(['code' => $status, 'message' => $exception->getMessage()])->withStatus($status)->withHeader('Cache-Control', 'no-store');
        }
    }

    private function clientCredentials(Request $request): array
    {
        $header = (string) $request->header('authorization', '');
        if (str_starts_with($header, 'Basic ')) {
            $decoded = base64_decode(substr($header, 6), true);
            if (is_string($decoded) && str_contains($decoded, ':')) {
                return explode(':', $decoded, 2);
            }
        }
        return [
            (string) $request->header('x-config-center-username', ''),
            (string) $request->header('x-config-center-password', ''),
        ];
    }
}
