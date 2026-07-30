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
            $ipWhitelist = new ClientIpWhitelistServer();
            $clientIp = $this->normalize($ipWhitelist->clientIp($request), 45);
            $hostname = $this->normalize((string) $request->header('x-config-center-hostname', ''), 128);
            if ($hostname === '') {
                $hostname = $clientIp;
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
                    $clientIp,
                    $hostname,
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
            ->limit(500)
            ->get();

        $ipWhitelist = new ClientIpWhitelistServer();
        $groups = [];

        foreach ($rows as $row) {
            $whitelist = $this->explainIp($ipWhitelist, (string) $row->last_ip);
            $groupKey = implode('|', [
                (int) $row->client_account_id,
                (string) $row->client_username,
                (string) $row->client_name,
                (string) ($whitelist['type'] ?? ''),
                (string) ($whitelist['name'] ?? ''),
                (string) ($whitelist['cidr'] ?? ''),
                (string) (int) (($whitelist['matched'] ?? false) === true),
            ]);
            $instance = [
                'clientAccountId' => (int) $row->client_account_id,
                'clientUsername' => $row->client_username,
                'clientName' => $row->client_name,
                'lastIp' => $row->last_ip,
                'lastIpWhitelist' => $whitelist,
                'lastHostname' => $row->last_hostname,
                'lastRevision' => (int) $row->last_revision,
                'readCount' => (int) $row->read_count,
                'lastReadAt' => DateTimeFormatter::beijing($row->last_read_at),
                'updatedAt' => DateTimeFormatter::beijing($row->updated_at),
            ];

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'key' => hash('sha256', $groupKey),
                    'clientAccountId' => (int) $row->client_account_id,
                    'clientUsername' => $row->client_username,
                    'clientName' => $row->client_name,
                    'sourceWhitelist' => $whitelist,
                    'lastIp' => $row->last_ip,
                    'lastRevision' => (int) $row->last_revision,
                    'readCount' => 0,
                    'instanceCount' => 0,
                    'lastReadAt' => DateTimeFormatter::beijing($row->last_read_at),
                    'updatedAt' => DateTimeFormatter::beijing($row->updated_at),
                    'instances' => [],
                    '_lastReadTs' => strtotime((string) $row->last_read_at) ?: 0,
                ];
            }

            $groups[$groupKey]['readCount'] += (int) $row->read_count;
            $groups[$groupKey]['instanceCount']++;
            $groups[$groupKey]['instances'][] = $instance;

            $lastReadTs = strtotime((string) $row->last_read_at) ?: 0;
            if ($lastReadTs > $groups[$groupKey]['_lastReadTs']) {
                $groups[$groupKey]['_lastReadTs'] = $lastReadTs;
                $groups[$groupKey]['lastIp'] = $row->last_ip;
                $groups[$groupKey]['lastRevision'] = (int) $row->last_revision;
                $groups[$groupKey]['lastReadAt'] = DateTimeFormatter::beijing($row->last_read_at);
                $groups[$groupKey]['updatedAt'] = DateTimeFormatter::beijing($row->updated_at);
            }
        }

        usort($groups, fn (array $a, array $b) => ($b['_lastReadTs'] ?? 0) <=> ($a['_lastReadTs'] ?? 0));

        return array_map(function (array $group) {
            unset($group['_lastReadTs']);
            return $group;
        }, $groups);
    }

    private function explainIp(ClientIpWhitelistServer $ipWhitelist, string $ip): array
    {
        try {
            return $ipWhitelist->explain($ip);
        } catch (Throwable) {
            return [
                'matched' => false,
                'type' => 'unknown',
                'name' => '',
                'cidr' => '',
                'remark' => '白名单解释不可用',
            ];
        }
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
