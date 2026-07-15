<?php

namespace app\common\server;

use app\common\model\ClientToken;
use app\common\model\ClientTokenRule;
use app\common\model\ConfigItem;
use InvalidArgumentException;

class ClientConfigServer
{
    public function authenticate(string $rawToken): ClientToken
    {
        if ($rawToken === '') {
            throw new InvalidArgumentException('缺少客户端令牌');
        }
        $token = ClientToken::query()->where('token_hash', hash('sha256', $rawToken))->where('enabled', true)->first();
        if (!$token) {
            throw new InvalidArgumentException('客户端令牌无效');
        }
        $token->forceFill(['last_used_at' => date('Y-m-d H:i:s')])->save();
        return $token;
    }

    public function read(ClientToken $token, string $namespace, string $group, string $dataId): array
    {
        $this->assertAllowed($token, $namespace, $group, $dataId);
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
            'updatedAt' => $item->updated_at?->toAtomString(),
        ];
    }

    private function assertAllowed(ClientToken $token, string $namespace, string $group, string $dataId): void
    {
        $allowed = ClientTokenRule::query()->where([
            'client_token_id' => $token->id,
            'namespace' => $namespace,
            'config_group' => $group,
            'data_id' => $dataId,
        ])->exists();
        if (!$allowed) {
            throw new InvalidArgumentException('该令牌没有读取此配置的权限');
        }
    }
}
