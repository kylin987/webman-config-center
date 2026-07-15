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

    public function publish(Request $request): Response
    {
        try {
            $item = (new ConfigPublishServer())->publish($request->post(), 'admin');
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
}
