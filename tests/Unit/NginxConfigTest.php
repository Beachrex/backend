<?php

namespace Tests\Unit;

use App\Http\Middleware\SetLocaleFromHeader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards for deploy/nginx (ADR 2026-10-30 "API performance: layered caching"). nginx itself does not run in CI;
 * these checks keep the files consistent with the PHP side.
 */
class NginxConfigTest extends TestCase
{
    private function file(string $name): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/deploy/nginx/'.$name);
    }

    #[Test]
    public function the_api_configs_of_all_environments_are_the_same_file(): void
    {
        $prod = $this->file('api.prod.conf');
        foreach (['uat', 'dev'] as $env) {
            $this->assertSame(
                str_replace(['api_prod', 'web_prod'], ["api_{$env}", "web_{$env}"], $prod),
                $this->file("api.{$env}.conf"),
                "api.{$env}.conf differs from api.prod.conf beyond the environment name",
            );
        }
    }

    #[Test]
    public function the_language_map_matches_the_supported_locales(): void
    {
        preg_match('/map \$http_accept_language \$beachrex_locale \{(.*?)\n\}/s', $this->file('api.prod.conf'), $m);
        $this->assertNotEmpty($m, 'language map not found');

        $mapped = [];
        preg_match_all('/"~\*\^\\\\s\*(?:\(\?:)?([a-z|]+)\)?\(\?:\[-;,\\\\s\]\|\$\)" ([a-z]+);/', $m[1], $rows, PREG_SET_ORDER);
        foreach ($rows as [, $codes, $locale]) {
            foreach (explode('|', $codes) as $code) {
                $mapped[$code] = $locale;
            }
        }

        $expected = array_combine(SetLocaleFromHeader::SUPPORTED_LOCALES, SetLocaleFromHeader::SUPPORTED_LOCALES);
        $expected['si'] = 'sl';
        $expected['cz'] = 'cs';
        ksort($expected);
        ksort($mapped);
        $this->assertSame($expected, $mapped);
    }

    #[Test]
    public function the_language_regexes_pick_the_first_tag_like_php(): void
    {
        // Mirror of the nginx map: first tag only, case-insensitive, followed by - ; , space or the end.
        $nginx = function (string $header): string {
            foreach (array_merge(SetLocaleFromHeader::SUPPORTED_LOCALES, ['si', 'cz']) as $code) {
                if (preg_match('/^\s*'.$code.'(?:[-;,\s]|$)/i', $header)) {
                    return ['si' => 'sl', 'cz' => 'cs'][$code] ?? $code;
                }
            }

            return '';
        };
        $parse = new \ReflectionMethod(SetLocaleFromHeader::class, 'parseAcceptLanguageHeader');
        $php = function (string $header) use ($parse): string {
            $parsed = $parse->invoke(new SetLocaleFromHeader(), $header);
            $parsed = ['si' => 'sl', 'cz' => 'cs'][$parsed] ?? $parsed;

            return in_array($parsed, SetLocaleFromHeader::SUPPORTED_LOCALES, true) ? $parsed : '';
        };

        foreach (['de-DE,de;q=0.9,en;q=0.8', 'de', 'SL', 'si-SI', 'en_US', 'zh-CN,sl;q=0.9', 'cz', ' hr ;q=1', 'fr-CH, fr;q=0.9', 'english', 'it;q=0.5', '*', ''] as $header) {
            $this->assertSame($php($header), $nginx($header), "Accept-Language: {$header}");
        }
    }

    #[Test]
    public function the_micro_cache_stores_only_opted_in_anonymous_answers(): void
    {
        $conf = $this->file('api.prod.conf');

        $this->assertStringContainsString('fastcgi_cache_key "$request_method|$host|$request_uri|$beachrex_locale";', $conf);
        $this->assertStringContainsString('fastcgi_cache_bypass $http_authorization;', $conf);
        $this->assertStringContainsString('fastcgi_no_cache $http_authorization $beachrex_api_nocache;', $conf);
        $this->assertMatchesRegularExpression('/map \$upstream_http_x_accel_expires \$beachrex_api_nocache \{\s*""\s+1;/', $conf);
        $this->assertStringContainsString('fastcgi_param HTTP_ACCEPT_LANGUAGE $beachrex_locale;', $conf);
        $this->assertStringContainsString('fastcgi_cache_lock on;', $conf);
        $this->assertStringContainsString('fastcgi_cache_use_stale updating', $conf);
        $this->assertStringNotContainsString('fastcgi_cache_valid', $conf, 'a default validity would cache answers that did not opt in');
        $this->assertMatchesRegularExpression('/max_size=\d+m/', $conf);
    }

    #[Test]
    public function every_server_block_can_resolve_the_php_containers(): void
    {
        // Upstreams are variables (api_<env> changes address on deploys), so nginx needs a resolver in every
        // server block that uses them; at http level it covers all (the :8080 status server had none → 502).
        $conf = $this->file('api.prod.conf');
        $beforeFirstServer = substr($conf, 0, strpos($conf, "\nserver {"));
        $this->assertMatchesRegularExpression('/^resolver 127\.0\.0\.11 /m', $beforeFirstServer);
        $this->assertSame(1, preg_match_all('/^\s*resolver /m', $conf));
    }

    #[Test]
    public function health_has_its_own_pool_with_a_fallback_to_the_main_pool(): void
    {
        $conf = $this->file('api.prod.conf');
        $this->assertMatchesRegularExpression('/location = \/api\/v1\/health \{.*?api_prod:9001.*?error_page 502 504 = @health_main_pool;/s', $conf);
        $this->assertMatchesRegularExpression('/location @health_main_pool \{.*?api_prod:9000/s', $conf);

        $dockerfile = file_get_contents(dirname(__DIR__, 2).'/Dockerfile.prod');
        $this->assertStringContainsString('echo "[health]"', $dockerfile);
        $this->assertStringContainsString('echo "listen = 9001"', $dockerfile);
        $this->assertStringContainsString('echo "pm.status_path = /fpm-status"', $dockerfile);
    }

    #[Test]
    public function host_nginx_limits_per_ip_and_per_network_and_exempts_our_servers(): void
    {
        $conf = $this->file('beachrex.conf');

        $this->assertStringContainsString('real_ip_header CF-Connecting-IP;', $conf);
        $this->assertMatchesRegularExpression('/geo \$beachrex_api_unlimited \{[^}]*2\.28\.76\.142\s+1;/s', $conf);
        // Every API/admin proxy location has both the per-IP and the per-network zone.
        $this->assertSame(substr_count($conf, 'limit_req zone=beachrex_api burst'), substr_count($conf, 'limit_req zone=beachrex_api_net burst'));
        $this->assertSame(6, substr_count($conf, 'limit_req zone=beachrex_api_net burst'));
        // Every website (dev, uat, prod) limits pages per IP and per network.
        $this->assertSame(3, substr_count($conf, 'limit_req zone=beachrex_pages burst'));
        $this->assertSame(3, substr_count($conf, 'limit_req zone=beachrex_pages_net burst'));
        // Every HTTPS server logs with timings.
        $this->assertSame(substr_count($conf, 'listen 443 ssl;'), substr_count($conf, 'access_log /var/log/nginx/access.log beachrex_timed;'));
    }
}
