<?php

namespace app\common\server;

use app\common\library\DateTimeFormatter;
use app\common\model\ClientIpWhitelist;
use InvalidArgumentException;
use support\Request;

class ClientIpWhitelistServer
{
    private const BUILTIN_RULES = [
        ['name' => 'localhost', 'cidr' => '127.0.0.1/32', 'remark' => '系统内置：本机 IPv4'],
        ['name' => 'localhost-ipv6', 'cidr' => '::1/128', 'remark' => '系统内置：本机 IPv6'],
        ['name' => 'private-10', 'cidr' => '10.0.0.0/8', 'remark' => '系统内置：内网 IPv4'],
        ['name' => 'private-172', 'cidr' => '172.16.0.0/12', 'remark' => '系统内置：内网 IPv4'],
        ['name' => 'private-192', 'cidr' => '192.168.0.0/16', 'remark' => '系统内置：内网 IPv4'],
        ['name' => 'carrier-grade-nat', 'cidr' => '100.64.0.0/10', 'remark' => '系统内置：云厂商/容器网络常见网段'],
        ['name' => 'unique-local-ipv6', 'cidr' => 'fc00::/7', 'remark' => '系统内置：内网 IPv6'],
        ['name' => 'link-local-ipv6', 'cidr' => 'fe80::/10', 'remark' => '系统内置：链路本地 IPv6'],
    ];

    public function list(): array
    {
        $manual = ClientIpWhitelist::query()->orderByDesc('id')->get();

        return [
            'enabled' => $this->enabled(),
            'builtin' => array_map(fn (array $rule) => [
                'name' => $rule['name'],
                'cidr' => $rule['cidr'],
                'enabled' => true,
                'remark' => $rule['remark'],
            ], self::BUILTIN_RULES),
            'manual' => $manual->map(fn (ClientIpWhitelist $rule) => [
                'id' => (int) $rule->id,
                'name' => $rule->name,
                'cidr' => $rule->cidr,
                'enabled' => (bool) $rule->enabled,
                'remark' => $rule->remark,
                'createdAt' => $this->formatTime($rule->created_at),
                'updatedAt' => $this->formatTime($rule->updated_at),
            ])->values()->all(),
        ];
    }

    public function create(string $name, string $cidr, string $remark, bool $enabled = true): array
    {
        $cidr = $this->normalizeCidr($cidr);
        $this->guardBuiltinDuplicate($cidr);
        if (ClientIpWhitelist::query()->where('cidr', $cidr)->exists()) {
            throw new InvalidArgumentException('白名单 IP/CIDR 已存在');
        }

        $rule = ClientIpWhitelist::query()->create([
            'name' => trim($name),
            'cidr' => $cidr,
            'remark' => trim($remark),
            'enabled' => $enabled,
        ]);

        return ['id' => (int) $rule->id, 'cidr' => $rule->cidr];
    }

    public function update(int $id, string $name, string $cidr, string $remark, bool $enabled): array
    {
        $rule = ClientIpWhitelist::query()->find($id);
        if (!$rule) {
            throw new InvalidArgumentException('白名单不存在');
        }

        $cidr = $this->normalizeCidr($cidr);
        $this->guardBuiltinDuplicate($cidr);
        if (ClientIpWhitelist::query()->where('cidr', $cidr)->where('id', '<>', $id)->exists()) {
            throw new InvalidArgumentException('白名单 IP/CIDR 已存在');
        }

        $rule->forceFill([
            'name' => trim($name),
            'cidr' => $cidr,
            'remark' => trim($remark),
            'enabled' => $enabled,
        ])->save();

        return ['id' => (int) $rule->id, 'cidr' => $rule->cidr, 'enabled' => (bool) $rule->enabled];
    }

    public function delete(int $id): void
    {
        $rule = ClientIpWhitelist::query()->find($id);
        if (!$rule) {
            throw new InvalidArgumentException('白名单不存在');
        }
        $rule->delete();
    }

    public function assertAllowed(Request $request): string
    {
        $ip = $this->clientIp($request);
        if (!$this->enabled()) {
            return $ip;
        }

        if ($ip === '') {
            throw new InvalidArgumentException('无法识别客户端 IP');
        }

        if ($this->isAllowed($ip)) {
            return $ip;
        }

        throw new InvalidArgumentException('客户端 IP 不在白名单内：' . $ip);
    }

    public function clientIp(Request $request): string
    {
        $candidates = [];
        foreach (['ali-cdn-real-ip', 'x-forwarded-for', 'x-real-ip'] as $header) {
            $value = (string) $request->header($header, '');
            foreach (explode(',', $value) as $ip) {
                $candidates[] = trim($ip);
            }
        }

        foreach (['getRealIp', 'getRemoteIp'] as $method) {
            if (method_exists($request, $method)) {
                $candidates[] = (string) $request->{$method}();
            }
        }

        foreach ($candidates as $candidate) {
            if ($this->validIp($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    public function isAllowed(string $ip): bool
    {
        if (!$this->enabled()) {
            return true;
        }

        foreach (self::BUILTIN_RULES as $rule) {
            if ($this->ipInCidr($ip, $rule['cidr'])) {
                return true;
            }
        }

        $rules = ClientIpWhitelist::query()->where('enabled', true)->pluck('cidr')->all();
        foreach ($rules as $cidr) {
            if ($this->ipInCidr($ip, (string) $cidr)) {
                return true;
            }
        }

        return false;
    }

    public function enabled(): bool
    {
        return (bool) config('config-center.client_ip_whitelist_enable', true);
    }

    private function normalizeCidr(string $cidr): string
    {
        $cidr = trim($cidr);
        if ($cidr === '') {
            throw new InvalidArgumentException('IP/CIDR 不能为空');
        }

        if (!str_contains($cidr, '/')) {
            if (!$this->validIp($cidr)) {
                throw new InvalidArgumentException('IP/CIDR 格式不正确');
            }
            return $cidr . (str_contains($cidr, ':') ? '/128' : '/32');
        }

        [$ip, $prefix] = explode('/', $cidr, 2);
        $ip = trim($ip);
        if (!$this->validIp($ip) || !ctype_digit($prefix)) {
            throw new InvalidArgumentException('IP/CIDR 格式不正确');
        }

        $prefixLength = (int) $prefix;
        $max = str_contains($ip, ':') ? 128 : 32;
        if ($prefixLength < 0 || $prefixLength > $max) {
            throw new InvalidArgumentException('CIDR 掩码长度不正确');
        }

        return $ip . '/' . $prefixLength;
    }

    private function guardBuiltinDuplicate(string $cidr): void
    {
        foreach (self::BUILTIN_RULES as $rule) {
            if ($cidr === $rule['cidr']) {
                throw new InvalidArgumentException('该网段已在系统内置白名单中');
            }
        }
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$range, $prefix] = explode('/', $cidr, 2);
        $ipBinary = @inet_pton($ip);
        $rangeBinary = @inet_pton($range);
        if ($ipBinary === false || $rangeBinary === false || strlen($ipBinary) !== strlen($rangeBinary)) {
            return false;
        }

        $prefixLength = (int) $prefix;
        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($rangeBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;
        return (ord($ipBinary[$fullBytes]) & $mask) === (ord($rangeBinary[$fullBytes]) & $mask);
    }

    private function validIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    private function formatTime(mixed $value): ?string
    {
        return $value ? DateTimeFormatter::beijing($value) : null;
    }
}
