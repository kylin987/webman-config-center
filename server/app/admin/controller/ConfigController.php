<?php

namespace app\admin\controller;

use app\common\model\ConfigHistory;
use app\common\model\ConfigItem;
use app\common\server\ConfigPublishServer;
use InvalidArgumentException;
use support\Request;
use support\Response;

class ConfigController
{
    public function index(Request $request): Response
    {
        $namespace = (string) ($request->get('namespace') ?: config('config-center.default_namespace'));
        $items = ConfigItem::query()->where('namespace', $namespace)->orderBy('config_group')->orderBy('data_id')->get();
        return json(['code' => 0, 'data' => $items->map(fn (ConfigItem $item) => [
            'namespace' => $item->namespace,
            'group' => $item->config_group,
            'dataId' => $item->data_id,
            'format' => $item->format,
            'revision' => (int) $item->revision,
            'updatedAt' => $item->updated_at?->toAtomString(),
        ])->values()]);
    }

    public function history(Request $request): Response
    {
        $namespace = (string) ($request->get('namespace') ?: config('config-center.default_namespace'));
        $group = (string) $request->get('group');
        $dataId = (string) $request->get('dataId');
        if ($group === '' || $dataId === '') return json(['code' => 400, 'message' => 'group 和 dataId 为必填项'], 400);
        $history = ConfigHistory::query()->where([
            'namespace' => $namespace,
            'config_group' => $group,
            'data_id' => $dataId,
        ])->orderByDesc('revision')->get();
        return json(['code' => 0, 'data' => $history]);
    }

    public function show(Request $request): Response
    {
        $namespace = (string) ($request->get('namespace') ?: config('config-center.default_namespace'));
        $item = ConfigItem::query()->where(['namespace' => $namespace, 'config_group' => (string) $request->get('group'), 'data_id' => (string) $request->get('dataId')])->first();
        if (!$item) return json(['code' => 404, 'message' => '配置不存在'], 404);
        return json(['code' => 0, 'data' => ['namespace' => $item->namespace, 'group' => $item->config_group, 'dataId' => $item->data_id, 'format' => $item->format, 'content' => $item->content, 'revision' => (int) $item->revision]]);
    }

    public function publish(Request $request): Response
    {
        try {
            $operator = $request->attribute('admin_user')->username;
            $item = (new ConfigPublishServer())->publish($request->post(), $operator);
            return json(['code' => 0, 'data' => [
                'namespace' => $item->namespace,
                'group' => $item->config_group,
                'dataId' => $item->data_id,
                'revision' => (int) $item->revision,
                'md5' => $item->content_md5,
            ]]);
        } catch (InvalidArgumentException $exception) {
            return json(['code' => 400, 'message' => $exception->getMessage()], 400);
        }
    }

    public function rollback(Request $request): Response
    {
        $history = ConfigHistory::query()->find((int) $request->post('historyId'));
        if (!$history) return json(['code' => 404, 'message' => '历史版本不存在'], 404);
        try {
            $item = (new ConfigPublishServer())->publish([
                'namespace' => $history->namespace,
                'group' => $history->config_group,
                'dataId' => $history->data_id,
                'format' => $history->format,
                'content' => $history->content,
                'expectedRevision' => $request->post('expectedRevision'),
                'note' => 'rollback from history ' . $history->id,
            ], $request->attribute('admin_user')->username);
            return json(['code' => 0, 'data' => ['revision' => (int) $item->revision]]);
        } catch (InvalidArgumentException $exception) {
            return json(['code' => 400, 'message' => $exception->getMessage()], 400);
        }
    }
}
