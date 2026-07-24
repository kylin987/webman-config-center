<?php

namespace app\admin\controller;

use app\common\model\ClientAccount;
use support\Request;
use support\Response;

class ClientGuideController
{
    public function index(Request $request): Response
    {
        $accounts = ClientAccount::query()
            ->where('enabled', true)
            ->orderByDesc('id')
            ->get(['name', 'username']);

        $redis = (array) config('redis.default', []);

        return json(['code' => 0, 'data' => [
            'namespace' => (string) config('config-center.default_namespace', 'public'),
            'adminPath' => (string) config('config-center.admin_path', '/cc-admin'),
            'eventChannel' => (string) config('config-center.event_channel', 'config-center:changed'),
            'redis' => [
                'host' => (string) ($redis['host'] ?? ''),
                'port' => (int) ($redis['port'] ?? 6379),
                'database' => (int) ($redis['database'] ?? 0),
                'passwordConfigured' => (string) ($redis['password'] ?? '') !== '',
            ],
            'accounts' => $accounts->map(fn (ClientAccount $account) => [
                'name' => $account->name,
                'username' => $account->username,
            ])->values(),
        ]]);
    }
}
