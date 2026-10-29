<?php

/*
 * Preview access: e-mail magic links that open the closed dev/uat websites
 * (owner 2026-10-29, ADR "Closed dev/uat websites behind an e-mail magic link").
 *
 * The website (web-client middleware) decides whether a visitor needs access; this API only sends the
 * one-time link and checks it. Off unless PREVIEW_ACCESS_ENABLED=true, and always off when FRONTEND_URL is the
 * production website: then both endpoints answer 404.
 */

$frontendHost = parse_url((string) env('FRONTEND_URL', ''), PHP_URL_HOST) ?: '';

return [
    'enabled' => (bool) env('PREVIEW_ACCESS_ENABLED', false)
        && ! in_array(strtolower($frontendHost), ['beachrex.com', 'www.beachrex.com'], true),

    // Besides the admins of this environment (users.is_admin), these addresses may enter (comma separated).
    'allowed_emails' => array_values(array_filter(array_map(
        fn (string $e) => strtolower(trim($e)),
        explode(',', (string) env('PREVIEW_ALLOWED_EMAILS', ''))
    ))),

    // How long a link in the e-mail works (minutes). One use only.
    'ttl_minutes' => 15,

    // E-mails per address: at most one per minute and this many per hour (silently skipped above that).
    'emails_per_hour' => 5,
];
