<?php

namespace app\common\library;

use InvalidArgumentException;

class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function secret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function verify(string $secret, string $code, int $window = 1, int $period = 30): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!is_string($code) || !preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = intdiv(time(), $period);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::code($secret, $counter + $offset), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function otpauthUri(string $secret, string $username, string $issuer = 'Webman Config Center'): string
    {
        $label = rawurlencode($issuer . ':' . $username);
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            $label,
            rawurlencode($secret),
            rawurlencode($issuer)
        );
    }

    private static function code(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        $binaryCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $secret = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $secret .= self::ALPHABET[bindec($chunk)];
        }
        return $secret;
    }

    private static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[\s=]+/', '', $secret));
        if ($secret === '') {
            throw new InvalidArgumentException('MFA secret 不能为空');
        }

        $bits = '';
        foreach (str_split($secret) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                throw new InvalidArgumentException('MFA secret 格式错误');
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                continue;
            }
            $binary .= chr(bindec($chunk));
        }
        return $binary;
    }
}
