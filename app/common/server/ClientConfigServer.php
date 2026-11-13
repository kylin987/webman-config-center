<?php

namespace app\common\server;

use app\common\library\DateTimeFormatter;
use app\common\model\ClientAccount;
use app\common\model\ConfigItem;
use InvalidArgumentException;

class ClientConfigServer
{
    private const AUTH_CACHE_TTL_NS = 60 * 1_000_000_000;

    private const AUTH_CACHE_MAX_ACCOUNTS = 1024;

    /** @var array<int, array{digest: string, expires_at: int|float}> */
    private static array $verifiedPasswords = [];

    private static ?string $authCacheKey = null;

    public function authenticate(string $username, string $password): ClientAccount
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new InvalidArgumentException('缺少客户端账号或密码');
        }
        $account = ClientAccount::query()->where('username', $username)->where('enabled', true)->first();
        if (!$account || !$this->passwordMatches($account, $password)) {
            throw new InvalidArgumentException('客户端账号或密码错误');
        }
        $account->forceFill(['last_used_at' => date('Y-m-d H:i:s')])->save();
        return $account;
    }

    private function passwordMatches(ClientAccount $account, string $password): bool
    {
        // 每次先查账号状态；摘要绑定数据库当前密码哈希，改密后旧缓存立即不匹配。
        self::$authCacheKey ??= random_bytes(32);
        $digest = hash_hmac('sha256', $account->password_hash . "\0" . $password, self::$authCacheKey);
        $accountId = (int) $account->id;
        $cached = self::$verifiedPasswords[$accountId] ?? null;
        if ($cached && $cached['expires_at'] > hrtime(true) && hash_equals($cached['digest'], $digest)) {
            return true;
        }
        if (!password_verify($password, $account->password_hash)) {
            return false;
        }

        // 只保存成功凭据摘要；固定到期不续期，限制常驻 worker 的内存占用。
        unset(self::$verifiedPasswords[$accountId]);
        if (count(self::$verifiedPasswords) >= self::AUTH_CACHE_MAX_ACCOUNTS) {
            unset(self::$verifiedPasswords[array_key_first(self::$verifiedPasswords)]);
        }
        self::$verifiedPasswords[$accountId] = [
            'digest' => $digest,
            'expires_at' => hrtime(true) + self::AUTH_CACHE_TTL_NS,
        ];
        return true;
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
