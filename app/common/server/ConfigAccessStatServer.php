<?php

namespace app\common\server;

use app\common\library\DateTimeFormatter;
use app\common\model\ClientAccount;
use app\common\model\ConfigAccessStat;
use support\Db;
use support\Request;
use Throwable;

class ConfigAccessStatServer
{
    public function record(ClientAccount $account, array $config, Request $request): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            $clientName = $this->normalize((string) $request->header('x-config-center-client-name', ''), 128);
            if ($clientName === '') {
                $clientName = $this->normalize((string) $account->name, 128);
            }

            Db::statement(
                'INSERT INTO `cc_config_access_stat`
                (`namespace`, `config_group`, `data_id`, `client_account_id`, `client_username`, `client_name`, `last_ip`, `last_hostname`, `last_revision`, `read_count`, `last_read_at`, `created_at`, `updated_at`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    `client_username` = VALUES(`client_username`),
                    `last_ip` = VALUES(`last_ip`),
                    `last_hostname` = VALUES(`last_hostname`),
                    `last_revision` = VALUES(`last_revision`),
                    `read_count` = `read_count` + 1,
                    `last_read_at` = VALUES(`last_read_at`),
                    `updated_at` = VALUES(`updated_at`)',
                [
                    (string) ($config['namespace'] ?? ''),
                    (string) ($config['group'] ?? ''),
                    (string) ($config['dataId'] ?? ''),
                    (int) $account->id,
                    (string) $account->username,
                    $clientName,
                    $this->normalize((new ClientIpWhitelistServer())->clientIp($request), 45),
                    $this->normalize((string) $request->header('x-config-center-hostname', ''), 128),
                    (int) ($config['revision'] ?? 0),
                    $now,
                    $now,
                    $now,
                ]
            );
        } catch (Throwable) {
            // 访问统计不能影响配置读取主流程。
        }
    }

    public function list(string $namespace, string $group, string $dataId): array
    {
        $rows = ConfigAccessStat::query()
            ->where([
                'namespace' => $namespace,
                'config_group' => $group,
                'data_id' => $dataId,
            ])
            ->orderByDesc('last_read_at')
            ->limit(100)
            ->get();

        return $rows->map(fn (ConfigAccessStat $row) => [
            'clientAccountId' => (int) $row->client_account_id,
            'clientUsername' => $row->client_username,
            'clientName' => $row->client_name,
            'lastIp' => $row->last_ip,
            'lastHostname' => $row->last_hostname,
            'lastRevision' => (int) $row->last_revision,
            'readCount' => (int) $row->read_count,
            'lastReadAt' => DateTimeFormatter::beijing($row->last_read_at),
            'updatedAt' => DateTimeFormatter::beijing($row->updated_at),
        ])->values()->all();
    }

    private function normalize(string $value, int $limit): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit, 'UTF-8');
        }
        return substr($value, 0, $limit);
    }
}
