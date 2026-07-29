<?php

namespace app\api\controller;

use app\common\server\ClientConfigServer;
use app\common\server\ClientIpWhitelistServer;
use app\common\server\ConfigAccessStatServer;
use app\common\server\ConfigPublishServer;
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
            $data = (new ClientConfigServer())->read($account, $namespace, $group, $dataId);
            (new ConfigAccessStatServer())->record($account, $data, $request);
            return json(['code' => 0, 'data' => $data])
                ->withHeader('Cache-Control', 'no-store');
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $status = str_contains($message, '缺少客户端账号') || str_contains($message, '客户端账号或密码错误') ? 401 : (str_contains($message, '不存在') ? 404 : 403);
            return json(['code' => $status, 'message' => $exception->getMessage()])->withStatus($status)->withHeader('Cache-Control', 'no-store');
        }
    }

    public function publish(Request $request): Response
    {
        try {
            (new ClientIpWhitelistServer())->assertAllowed($request);
            [$username, $password] = $this->clientCredentials($request);
            $account = (new ClientConfigServer())->authenticate($username, $password);

            $item = (new ConfigPublishServer())->publish($request->post(), 'client:' . $account->username);
            return json(['code' => 0, 'data' => [
                'namespace' => $item->namespace,
                'group' => $item->config_group,
                'dataId' => $item->data_id,
                'format' => $item->format,
                'revision' => (int) $item->revision,
                'md5' => $item->content_md5,
            ]])->withHeader('Cache-Control', 'no-store');
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $status = str_contains($message, '缺少客户端账号') || str_contains($message, '客户端账号或密码错误') ? 401 : 400;
            if (str_contains($message, '客户端 IP 不在白名单')) {
                $status = 403;
            }
            return json(['code' => $status, 'message' => $message])->withStatus($status)->withHeader('Cache-Control', 'no-store');
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
