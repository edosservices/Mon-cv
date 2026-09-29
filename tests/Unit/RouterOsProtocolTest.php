<?php

namespace Tests\Unit;

use App\Services\Mikrotik\RouterOsProtocol;
use PHPUnit\Framework\TestCase;

class RouterOsProtocolTest extends TestCase
{
    public function test_short_words_are_prefixed_with_their_length(): void
    {
        $this->assertSame(chr(6).'/login', RouterOsProtocol::encodeWord('/login'));
    }

    public function test_sentence_ends_with_a_zero_byte(): void
    {
        $encoded = RouterOsProtocol::encodeSentence(['/login', '=name=demo']);
        $this->assertSame("\x00", substr($encoded, -1));
    }

    public function test_duration_uses_router_os_units(): void
    {
        $this->assertSame('1d', RouterOsProtocol::secondsToRouterTime(86400));
        $this->assertSame('1h', RouterOsProtocol::secondsToRouterTime(3600));
    }
}
