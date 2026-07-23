<?php

namespace app\common\server;

use app\common\model\ConfigItem;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class ConfigBatchServer
{
    private const EXPORT_FILE_PREFIX = 'config_center_export_';

    public function exportZip(string $namespace): array
    {
        $items = ConfigItem::query()
            ->where('namespace', $namespace)
            ->orderBy('config_group')
            ->orderBy('data_id')
            ->get();

        $zipPath = tempnam(sys_get_temp_dir(), 'cc_export_');
        if ($zipPath === false) {
            throw new RuntimeException('创建导出文件失败');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            throw new RuntimeException('创建 zip 文件失败');
        }

        foreach ($items as $item) {
            $path = $this->safeZipPath((string) $item->config_group, (string) $item->data_id);
            $zip->addFromString($path, (string) $item->content);
        }

        $zip->close();

        $filename = self::EXPORT_FILE_PREFIX . date('YmdHis') . '.zip';
        return [$zipPath, $filename, $items->count()];
    }

    public function importZip(string $zipPath, string $namespace, string $operator): array
    {
        if (!is_file($zipPath)) {
            throw new InvalidArgumentException('上传文件不存在');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new InvalidArgumentException('zip 文件无法打开');
        }

        $summary = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'failures' => [],
        ];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = (string) $zip->getNameIndex($index);
            if ($this->shouldSkipEntry($entry)) {
                continue;
            }

            $summary['total']++;
            [$group, $dataId] = $this->parseZipEntry($entry);
            $format = $this->formatFromDataId($dataId);
            $stat = $zip->statIndex($index);
            $maxBytes = (int) config('config-center.max_content_bytes');
            if (($stat['size'] ?? 0) > $maxBytes) {
                $this->addFailure($summary, $entry, '配置内容超过大小限制');
                continue;
            }

            $content = $zip->getFromIndex($index);
            if ($content === false) {
                $this->addFailure($summary, $entry, '读取文件内容失败');
                continue;
            }

            try {
                $existing = ConfigItem::query()->where([
                    'namespace' => $namespace,
                    'config_group' => $group,
                    'data_id' => $dataId,
                ])->first();

                if ($existing && hash_equals((string) $existing->content_md5, md5($content))) {
                    $summary['skipped']++;
                    continue;
                }

                $item = (new ConfigPublishServer())->publish([
                    'namespace' => $namespace,
                    'group' => $group,
                    'dataId' => $dataId,
                    'format' => $format,
                    'content' => $content,
                    'note' => 'batch import zip',
                ], $operator);

                if ($existing) {
                    $summary['updated']++;
                } else {
                    $summary['created']++;
                }
            } catch (\Throwable $throwable) {
                $this->addFailure($summary, $entry, $throwable->getMessage() ?: '导入失败');
            }
        }

        $zip->close();
        return $summary;
    }

    private function shouldSkipEntry(string $entry): bool
    {
        $entry = str_replace('\\', '/', $entry);
        if ($entry === '' || str_ends_with($entry, '/')) {
            return true;
        }
        if (str_starts_with($entry, '__MACOSX/') || str_contains($entry, '/__MACOSX/')) {
            return true;
        }
        foreach (explode('/', $entry) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || str_starts_with($part, '.')) {
                return true;
            }
        }
        return false;
    }

    private function parseZipEntry(string $entry): array
    {
        $entry = trim(str_replace('\\', '/', $entry), '/');
        $parts = explode('/', $entry);

        if (count($parts) === 1) {
            return ['DEFAULT_GROUP', $parts[0]];
        }

        $group = array_shift($parts);
        $dataId = implode('/', $parts);
        if ($group === '' || $dataId === '') {
            throw new InvalidArgumentException('zip 文件路径格式无效');
        }

        return [$group, $dataId];
    }

    private function formatFromDataId(string $dataId): string
    {
        $extension = strtolower(pathinfo($dataId, PATHINFO_EXTENSION));
        return match ($extension) {
            'php' => 'php',
            'json' => 'json',
            'yaml' => 'yaml',
            'yml' => 'yml',
            'ini' => 'ini',
            default => 'txt',
        };
    }

    private function safeZipPath(string $group, string $dataId): string
    {
        $group = trim(str_replace('\\', '/', $group), '/');
        $dataId = trim(str_replace('\\', '/', $dataId), '/');

        foreach (explode('/', $group . '/' . $dataId) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw new InvalidArgumentException('配置路径包含非法片段');
            }
        }

        return $group . '/' . $dataId;
    }

    private function addFailure(array &$summary, string $entry, string $reason): void
    {
        $summary['failed']++;
        $summary['failures'][] = [
            'file' => $entry,
            'reason' => $reason,
        ];
    }
}
