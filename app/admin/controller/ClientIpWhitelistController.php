<?php

namespace app\admin\controller;

use app\common\server\ClientIpWhitelistServer;
use InvalidArgumentException;
use support\Request;
use support\Response;
use Throwable;

class ClientIpWhitelistController
{
    public function index(Request $request): Response
    {
        try {
            return json(['code' => 0, 'data' => (new ClientIpWhitelistServer())->list()]);
        } catch (Throwable $exception) {
            return json(['code' => 500, 'message' => 'IP 白名单读取失败，请确认已导入 sql/003_client_ip_whitelist.sql'])->withStatus(500);
        }
    }

    public function create(Request $request): Response
    {
        try {
            return json(['code' => 0, 'data' => (new ClientIpWhitelistServer())->create(
                (string) $request->post('name', ''),
                (string) $request->post('cidr', ''),
                (string) $request->post('remark', ''),
                (bool) $request->post('enabled', true),
            )]);
        } catch (InvalidArgumentException $exception) {
            return json(['code' => 400, 'message' => $exception->getMessage()])->withStatus(400);
        } catch (Throwable $exception) {
            return json(['code' => 500, 'message' => 'IP 白名单保存失败，请确认已导入 sql/003_client_ip_whitelist.sql'])->withStatus(500);
        }
    }

    public function update(Request $request): Response
    {
        try {
            return json(['code' => 0, 'data' => (new ClientIpWhitelistServer())->update(
                (int) $request->post('id'),
                (string) $request->post('name', ''),
                (string) $request->post('cidr', ''),
                (string) $request->post('remark', ''),
                (bool) $request->post('enabled', true),
            )]);
        } catch (InvalidArgumentException $exception) {
            return json(['code' => 400, 'message' => $exception->getMessage()])->withStatus(400);
        } catch (Throwable $exception) {
            return json(['code' => 500, 'message' => 'IP 白名单保存失败，请确认已导入 sql/003_client_ip_whitelist.sql'])->withStatus(500);
        }
    }

    public function delete(Request $request): Response
    {
        try {
            (new ClientIpWhitelistServer())->delete((int) $request->post('id'));
            return json(['code' => 0]);
        } catch (InvalidArgumentException $exception) {
            return json(['code' => 400, 'message' => $exception->getMessage()])->withStatus(400);
        } catch (Throwable $exception) {
            return json(['code' => 500, 'message' => 'IP 白名单删除失败，请确认已导入 sql/003_client_ip_whitelist.sql'])->withStatus(500);
        }
    }
}
