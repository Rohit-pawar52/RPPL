<?php

namespace App\Support;

/**
 * Lightweight, best-effort crawler/automation filter for analytics. It is
 * deliberately a short readable list, not a crawler database: it catches
 * the obvious bots, link-preview fetchers, uptime monitors and scripted
 * HTTP clients, and will miss disguised ones. A missing user agent counts
 * as automated.
 */
final class BotDetector
{
    private const SIGNATURES = [
        'bot', 'crawl', 'spider', 'slurp', 'scrape', 'scrapy',
        'curl', 'wget', 'python-requests', 'python-urllib', 'aiohttp', 'httpx',
        'go-http-client', 'java/', 'okhttp-bot', 'libwww', 'httpclient', 'node-fetch', 'axios',
        'headless', 'phantomjs', 'puppeteer', 'playwright', 'selenium',
        'lighthouse', 'pagespeed', 'gtmetrix', 'pingdom', 'uptime', 'monitor',
        'facebookexternalhit', 'whatsapp', 'telegram', 'twitterbot', 'slackbot', 'discord', 'preview',
    ];

    public static function isBot(?string $userAgent): bool
    {
        $userAgent = strtolower(trim((string) $userAgent));

        if ($userAgent === '') {
            return true;
        }

        foreach (self::SIGNATURES as $signature) {
            if (str_contains($userAgent, $signature)) {
                return true;
            }
        }

        return false;
    }
}
