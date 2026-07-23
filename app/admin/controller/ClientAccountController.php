<?php

namespace app\admin\controller;

use app\common\server\ClientAccountAdminServer;
use InvalidArgumentException;
use support\Request;
use support\Response;

class ClientAccountController
{
    public function index(Request $request): Response
    {
        return json(['code' => 0, 'data' => (new ClientAccountAdminServer())->list()]);
    }

    public function create(Request $request): Response
    {
        try {
            return json(['code' => 0, 'data' => (new ClientAccountAdminServer())->create(
                (string) $request->post('name'),
                (string) $request->post('username'),
                (string) $request->post('password'),
            )]);
        } catch (InvalidArgumentException $exception) {
            return json(['code' => 400, 'message' => $exception->getMessage()])->withStatus(400);
        }
    }

    public function update(Request $request): Response
    {
        try {
            return json(['code' => 0, 'data' => (new ClientAccountAdminServer())->update(
                (int) $request->post('id'),
                (string) $request->post('name'),
                (string) $request->post('username'),
                (string) $request->post('password', ''),
                (bool) $request->post('enabled', true),
            )]);
        } catch (InvalidArgumentException $exception) {
            return json(['code' => 400, 'message' => $exception->getMessage()])->withStatus(400);
        }
    }

    public function disable(Request $request): Response
    {
        try {
            (new ClientAccountAdminServer())->disable((int) $request->post('id'));
            return json(['code' => 0]);
        } catch (InvalidArgumentException $exception) {
            return json(['code' => 400, 'message' => $exception->getMessage()])->withStatus(400);
        }
    }
}
