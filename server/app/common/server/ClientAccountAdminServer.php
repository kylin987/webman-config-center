<?php

namespace app\common\server;

use app\common\library\DateTimeFormatter;
use app\common\model\ClientAccount;
use InvalidArgumentException;

class ClientAccountAdminServer
{
    public function list(): array
    {
        $accounts = ClientAccount::query()->orderByDesc('id')->get();
        return $accounts->map(fn (ClientAccount $account) => [
            'id' => (int) $account->id,
            'name' => $account->name,
            'username' => $account->username,
            'enabled' => (bool) $account->enabled,
            'lastUsedAt' => $this->formatTime($account->last_used_at),
            'createdAt' => $this->formatTime($account->created_at),
        ])->values()->all();
    }

    public function create(string $name, string $username, string $password): array
    {
        $name = trim($name);
        $username = trim($username);
        if ($name === '' || $username === '' || $password === '') {
            throw new InvalidArgumentException('名称、账号和密码不能为空');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('客户端密码至少 8 位');
        }
        if (ClientAccount::query()->where('username', $username)->exists()) {
            throw new InvalidArgumentException('客户端账号已存在');
        }
        if (ClientAccount::query()->where('name', $name)->exists()) {
            throw new InvalidArgumentException('客户端名称已存在');
        }

        $account = ClientAccount::query()->create([
            'name' => $name,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'enabled' => true,
        ]);

        return [
            'id' => (int) $account->id,
            'name' => $account->name,
            'username' => $account->username,
        ];
    }

    public function update(int $id, string $name, string $username, string $password, bool $enabled): array
    {
        $account = ClientAccount::query()->find($id);
        if (!$account) {
            throw new InvalidArgumentException('客户端账号不存在');
        }

        $name = trim($name);
        $username = trim($username);
        if ($name === '' || $username === '') {
            throw new InvalidArgumentException('名称和账号不能为空');
        }
        if ($password !== '' && strlen($password) < 8) {
            throw new InvalidArgumentException('客户端密码至少 8 位');
        }
        if (ClientAccount::query()->where('username', $username)->where('id', '<>', $id)->exists()) {
            throw new InvalidArgumentException('客户端账号已存在');
        }
        if (ClientAccount::query()->where('name', $name)->where('id', '<>', $id)->exists()) {
            throw new InvalidArgumentException('客户端名称已存在');
        }

        $attributes = [
            'name' => $name,
            'username' => $username,
            'enabled' => $enabled,
        ];
        if ($password !== '') {
            $attributes['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $account->forceFill($attributes)->save();

        return [
            'id' => (int) $account->id,
            'name' => $account->name,
            'username' => $account->username,
            'enabled' => (bool) $account->enabled,
        ];
    }

    public function disable(int $id): void
    {
        $account = ClientAccount::query()->find($id);
        if (!$account) {
            throw new InvalidArgumentException('客户端账号不存在');
        }
        $account->forceFill(['enabled' => false])->save();
    }

    private function formatTime(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }
        return DateTimeFormatter::beijing($value);
    }
}
