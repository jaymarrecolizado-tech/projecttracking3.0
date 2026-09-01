<?php

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    // RFC 6238 test vectors, SHA-1, secret = ASCII "12345678901234567890"
    // (base32 GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ). The RFC publishes 8-digit
    // codes; the last 6 digits are the 6-digit variant.
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function test_matches_rfc6238_vectors(): void
    {
        $this->assertSame('287082', Totp::code(self::SECRET, 59));
        $this->assertSame('081804', Totp::code(self::SECRET, 1111111109));
        $this->assertSame('050471', Totp::code(self::SECRET, 1111111111));
    }

    public function test_current_code_verifies_and_old_code_outside_window_fails(): void
    {
        $now = 1111111109;
        $code = Totp::code(self::SECRET, $now);

        $this->assertTrue(Totp::verify(self::SECRET, $code, 1, $now));
        // Previous step is inside ±1 window.
        $this->assertTrue(Totp::verify(self::SECRET, Totp::code(self::SECRET, $now - 30), 1, $now));
        // Two steps back is outside ±1 window.
        $this->assertFalse(Totp::verify(self::SECRET, Totp::code(self::SECRET, $now - 60), 1, $now));
    }

    public function test_verify_tolerates_whitespace_and_rejects_garbage(): void
    {
        $code = Totp::code(self::SECRET);

        $this->assertTrue(Totp::verify(self::SECRET, " {$code} "));
        $this->assertFalse(Totp::verify(self::SECRET, 'abcdef'));
        $this->assertFalse(Totp::verify(self::SECRET, '12345'));
        $this->assertFalse(Totp::verify(self::SECRET, ''));
    }

    public function test_base32_roundtrip_and_generated_secret_shape(): void
    {
        $this->assertSame('MFRGGZDFMZTWQ', Totp::base32Encode('abcdefgh'));
        $this->assertSame('abcdefgh', Totp::base32Decode('MFRGGZDFMZTWQ'));

        $secret = Totp::generateSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertSame('12345678901234567890', Totp::base32Decode(self::SECRET));
    }
}
