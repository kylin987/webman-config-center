<?php

namespace app\common\server;

use app\common\library\DateTimeFormatter;
use app\common\model\ClientAccount;
use app\common\model\ConfigItem;
use InvalidArgumentException;

class ClientConfigServer
{
    public function authenticate(string $username, string $password): ClientAccount
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new InvalidArgumentException('缺少客户端账号或密码');
        }
        $account = ClientAccount::query()->where('username', $username)->where('enabled', true)->first();
        if (!$account || !password_verify($password, $account->password_hash)) {
            throw new InvalidArgumentException('客户端账号或密码错误');
        }
        $account->forceFill(['last_used_at' => date('Y-m-d H:i:s')])->save();
        return $account;
    }

    public function read(ClientAccount $account, string $namespace, string $group, string $dataId): array
    {
        $item = ConfigItem::query()->where([
            'namespace' => $namespace,
            'config_group' => $group,
            'data_id' => $dataId,
        ])->first();
        if (!$item) {
            throw new InvalidArgumentException('配置不存在');
        }
        return [
            'namespace' => $item->namespace,
            'group' => $item->config_group,
            'dataId' => $item->data_id,
            'format' => $item->format,
            'content' => $item->content,
            'revision' => (int) $item->revision,
            'md5' => $item->content_md5,
            'updatedAt' => DateTimeFormatter::beijing($item->updated_at),
        ];
    }
}
