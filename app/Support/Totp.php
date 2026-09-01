<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * RFC 6238 TOTP (SHA-1, 6 digits, 30s step) — the algorithm Google
 * Authenticator, Authy, 1Password et al. implement. Secrets are RFC 4648
 * base32. No external package so the auth path stays auditable.
 */
class Totp
{
    public const STEP = 30;

    public const DIGITS = 6;

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function code(string $base32Secret, ?int $at = null): string
    {
        $counter = intdiv($at ?? time(), self::STEP);
        $key = self::base32Decode($base32Secret);
        $hash = hash_hmac('sha1', pack('J', $counter), $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function verify(string $base32Secret, string $code, int $window = 1, ?int $at = null): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        if (! preg_match('/^\d{'.self::DIGITS.'}$/', (string) $code)) {
            return false;
        }

        $now = $at ?? time();
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($base32Secret, $now + $i * self::STEP), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function otpauthUri(string $issuer, string $account, string $base32Secret): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($account)
            .'?issuer='.rawurlencode($issuer)
            .'&secret='.$base32Secret
            .'&algorithm=SHA1&digits='.self::DIGITS.'&period='.self::STEP;
    }

    public static function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        $buffer = 0;
        $bits = 0;
        foreach (str_split($bytes) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits += 8;
            while ($bits >= 5) {
                $out .= $alphabet[($buffer >> ($bits - 5)) & 0x1F];
                $bits -= 5;
            }
        }
        if ($bits > 0) {
            $out .= $alphabet[($buffer << (5 - $bits)) & 0x1F];
        }

        return $out;
    }

    public static function base32Decode(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $encoded));
        $out = '';
        $buffer = 0;
        $bits = 0;
        for ($i = 0, $n = Str::length($encoded); $i < $n; $i++) {
            $buffer = ($buffer << 5) | strpos($alphabet, $encoded[$i]);
            $bits += 5;
            if ($bits >= 8) {
                $out .= chr(($buffer >> ($bits - 8)) & 0xFF);
                $bits -= 8;
            }
        }

        return $out;
    }
}
