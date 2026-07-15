<?php

namespace app\process;

use app\common\model\ConfigOutbox;
use support\Log;
use support\Redis;
use Workerman\Timer;

class OutboxPublisher
{
    public function onWorkerStart(): void
    {
        Timer::add(1, [$this, 'publishPending']);
    }

    public function publishPending(): void
    {
        $events = ConfigOutbox::query()
            ->whereNull('published_at')
            ->where('available_at', '<=', date('Y-m-d H:i:s'))
            ->orderBy('created_at')
            ->limit((int) config('config-center.outbox_batch_size'))
            ->get();

        foreach ($events as $event) {
            try {
                Redis::publish((string) config('config-center.event_channel'), json_encode([
                    'id' => $event->id,
                    'type' => $event->event_type,
                    'data' => json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR),
                ], JSON_THROW_ON_ERROR));
                $event->forceFill(['published_at' => date('Y-m-d H:i:s'), 'last_error' => ''])->save();
            } catch (\Throwable $exception) {
                $event->forceFill([
                    'attempts' => $event->attempts + 1,
                    'available_at' => date('Y-m-d H:i:s', time() + (int) config('config-center.outbox_retry_seconds')),
                    'last_error' => substr($exception->getMessage(), 0, 500),
                ])->save();
                Log::warning('config outbox publish failed', ['event_id' => $event->id, 'message' => $exception->getMessage()]);
            }
        }
    }
}

