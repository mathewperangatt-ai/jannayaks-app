<?php

namespace Tests\Unit;

use App\Support\TrustedProxiesConfig;
use PHPUnit\Framework\TestCase;

class TrustedProxiesConfigTest extends TestCase
{
    public function test_empty_value_falls_back_to_trust_all(): void
    {
        $this->assertSame('*', TrustedProxiesConfig::parse(''));
        $this->assertSame('*', TrustedProxiesConfig::parse('   '));
    }

    public function test_star_means_trust_all(): void
    {
        $this->assertSame('*', TrustedProxiesConfig::parse('*'));
        $this->assertSame('*', TrustedProxiesConfig::parse(' * '));
    }

    public function test_single_ip_parses_to_list(): void
    {
        $this->assertSame(['10.0.0.1'], TrustedProxiesConfig::parse('10.0.0.1'));
    }

    public function test_comma_separated_ips_and_cidrs_parse_with_whitespace_trimmed(): void
    {
        $this->assertSame(
            ['173.245.48.0/20', '103.21.244.0/22', '2400:cb00::/32'],
            TrustedProxiesConfig::parse(' 173.245.48.0/20 , 103.21.244.0/22 , 2400:cb00::/32 ')
        );
    }

    public function test_blank_entries_are_dropped_and_duplicates_removed(): void
    {
        $this->assertSame(
            ['10.0.0.1', '10.0.0.2'],
            TrustedProxiesConfig::parse('10.0.0.1,,10.0.0.2,10.0.0.1')
        );
    }

    public function test_stray_star_anywhere_upgrades_to_trust_all(): void
    {
        $this->assertSame('*', TrustedProxiesConfig::parse('10.0.0.1,*,10.0.0.2'));
    }

    public function test_only_blanks_and_commas_falls_back_to_trust_all(): void
    {
        $this->assertSame('*', TrustedProxiesConfig::parse(',,,'));
    }
}
