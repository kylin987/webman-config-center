<?php

namespace app\admin\controller;

use app\common\server\ClientTokenAdminServer;
use InvalidArgumentException;
use support\Request;
use support\Response;

class ClientTokenController
{
    public function create(Request $request): Response
    {
        try {
            return json(['code' => 0, 'data' => (new ClientTokenAdminServer())->create(
                (string) $request->post('name'),
                (array) $request->post('rules', []),
            )]);
        } catch (InvalidArgumentException $exception) {
            return json(['code' => 400, 'message' => $exception->getMessage()], 400);
        }
    }
}

