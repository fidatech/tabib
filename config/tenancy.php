<?php

/*
| One database per clinic.
|
| central_domains  hosts that open the system administrator panel (Fida Technologies)
| base_domain      a new clinic gets <slug>.<base_domain> unless a domain of its own is written
| database_prefix  a clinic's database is <prefix><slug>
| sqlite_path      where the database files are kept when the system runs on SQLite (tests)
| cpanel           on cPanel hosting: the account and its API token, so databases and subdomains are made through the panel
*/

return [
    'central_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env('CENTRAL_DOMAINS', 'localhost,127.0.0.1,admin.tabib.test'))))),
    'base_domain' => env('TENANT_BASE_DOMAIN', 'tabib.test'),
    'database_prefix' => env('TENANT_DATABASE_PREFIX', 'tabib_'),
    'sqlite_path' => storage_path('tenants'),
    'cpanel' => [
        'user' => env('CPANEL_USER'),
        'token' => env('CPANEL_TOKEN'),
        'url' => env('CPANEL_URL', 'https://127.0.0.1:2083'),
        'docroot' => env('CPANEL_DOCROOT', 'public_html'),
        // every name under the base domain already reaches the system with a valid certificate
        // (a "*" subdomain with a wildcard certificate): no subdomain is made for a new clinic
        'wildcard' => (bool) env('CPANEL_WILDCARD', false),
    ],
    'reserved_slugs' => ['admin', 'www', 'api', 'app', 'mail', 'central', 'book', 'reseller', 'support'],
];
