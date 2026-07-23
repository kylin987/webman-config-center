<?php

namespace app\common\server;

use app\common\library\ConfigContentValidator;
use app\common\model\ConfigHistory;
use app\common\model\ConfigItem;
use app\common\model\ConfigOutbox;
use InvalidArgumentException;
use support\Db;

class ConfigPublishServer
{
    public function publish(array $params, string $operator): ConfigItem
    {
        $namespace = $params['namespace'] ?: config('config-center.default_namespace');
        $group = trim((string) ($params['group'] ?? ''));
        $dataId = trim((string) ($params['dataId'] ?? ''));
        $format = strtolower(trim((string) ($params['format'] ?? '')));
        $content = (string) ($params['content'] ?? '');
        $expectedRevision = $params['expectedRevision'] ?? null;

        if ($group === '' || $dataId === '' || $format === '') {
            throw new InvalidArgumentException('namespace、group、dataId 和 format 为必填项');
        }

        (new ConfigContentValidator())->validate($format, $content);
        return Db::transaction(function () use ($namespace, $group, $dataId, $format, $content, $expectedRevision, $operator, $params) {
            $item = ConfigItem::query()->where([
                'namespace' => $namespace,
                'config_group' => $group,
                'data_id' => $dataId,
            ])->lockForUpdate()->first();

            if ($item && $expectedRevision !== null && (int) $expectedRevision !== (int) $item->revision) {
                throw new InvalidArgumentException('配置已被其他发布覆盖，请刷新后重试');
            }

            $revision = $item ? $item->revision + 1 : 1;
            $attributes = [
                'namespace' => $namespace,
                'config_group' => $group,
                'data_id' => $dataId,
                'format' => $format,
                'content' => $content,
                'content_md5' => md5($content),
                'revision' => $revision,
                'updated_by' => $operator,
            ];
            $item = $item ?: new ConfigItem();
            $item->fill($attributes);
            $item->save();

            ConfigHistory::query()->create($attributes + [
                'config_item_id' => $item->id,
                'operator' => $operator,
                'note' => (string) ($params['note'] ?? ''),
            ]);

            ConfigOutbox::query()->create([
                'id' => $this->uuid(),
                'event_type' => 'config.changed',
                'payload' => json_encode([
                    'namespace' => $namespace,
                    'group' => $group,
                    'dataId' => $dataId,
                    'format' => $format,
                    'revision' => $revision,
                    'md5' => md5($content),
                ], JSON_THROW_ON_ERROR),
                'available_at' => date('Y-m-d H:i:s'),
            ]);
            return $item;
        });
    }

    public function delete(array $params, string $operator): void
    {
        $namespace = $params['namespace'] ?: config('config-center.default_namespace');
        $group = trim((string) ($params['group'] ?? ''));
        $dataId = trim((string) ($params['dataId'] ?? ''));
        $expectedRevision = $params['expectedRevision'] ?? null;

        if ($group === '' || $dataId === '') {
            throw new InvalidArgumentException('group 和 dataId 为必填项');
        }

        Db::transaction(function () use ($namespace, $group, $dataId, $expectedRevision) {
            $item = ConfigItem::query()->where([
                'namespace' => $namespace,
                'config_group' => $group,
                'data_id' => $dataId,
            ])->lockForUpdate()->first();

            if (!$item) {
                throw new InvalidArgumentException('配置不存在或已被删除');
            }

            if ($expectedRevision !== null && (int) $expectedRevision !== (int) $item->revision) {
                throw new InvalidArgumentException('配置已被其他发布覆盖，请刷新后重试');
            }

            $item->delete();
        });
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
