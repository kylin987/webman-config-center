<?php

namespace app\common\server;

use app\common\model\ClientToken;
use app\common\model\ClientTokenRule;
use InvalidArgumentException;
use support\Db;

class ClientTokenAdminServer
{
    public function create(string $name, array $rules): array
    {
        if (trim($name) === '' || $rules === []) {
            throw new InvalidArgumentException('令牌名称和授权配置不能为空');
        }
        $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $token = Db::transaction(function () use ($name, $rules, $rawToken) {
            $token = ClientToken::query()->create([
                'name' => trim($name),
                'token_hash' => hash('sha256', $rawToken),
                'enabled' => true,
            ]);
            foreach ($rules as $rule) {
                if (empty($rule['namespace']) || empty($rule['group']) || empty($rule['dataId'])) {
                    throw new InvalidArgumentException('授权项必须包含 namespace、group、dataId');
                }
                ClientTokenRule::query()->create([
                    'client_token_id' => $token->id,
                    'namespace' => $rule['namespace'],
                    'config_group' => $rule['group'],
                    'data_id' => $rule['dataId'],
                ]);
            }
            return $token;
        });
        return ['id' => $token->id, 'name' => $token->name, 'token' => $rawToken];
    }
}

