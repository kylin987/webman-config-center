<?php

namespace app\common\library;

use DateTimeInterface;

class DateTimeFormatter
{
    public static function beijing(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return (clone $value)->setTimezone(new \DateTimeZone('Asia/Shanghai'))->format('Y-m-d H:i:s');
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return (string) $value;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
