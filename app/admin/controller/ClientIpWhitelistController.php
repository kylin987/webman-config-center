<?php

namespace app\admin\controller;

use app\common\server\ClientIpWhitelistServer;
use InvalidArgumentException;
use support\Request;
use support\Response;

class ClientIpWhitelistController
{
    public function index(Request $request): Response
    {
        return json(['code' => 0, 'data' => (new ClientIpWhitelistServer())->list()]);
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
        }
    }

    public function delete(Request $request): Response
    {
        try {
            (new ClientIpWhitelistServer())->delete((int) $request->post('id'));
            return json(['code' => 0]);
        } catch (InvalidArgumentException $exception) {
            return json(['code' => 400, 'message' => $exception->getMessage()])->withStatus(400);
        }
    }
}
