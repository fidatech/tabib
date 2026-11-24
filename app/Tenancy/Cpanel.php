<?php

namespace App\Tenancy;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * On cPanel hosting a database and an address are not made with SQL: the account asks cPanel
 * for them. With CPANEL_USER and CPANEL_TOKEN in the .env the system does that by itself when a
 * clinic is opened: its database, the rights of the MySQL user on it, and its subdomain.
 */
class Cpanel
{
    public function enabled(): bool
    {
        return filled(config('tenancy.cpanel.user')) && filled(config('tenancy.cpanel.token'));
    }

    /** One call of the cPanel API (UAPI), e.g. call('Mysql', 'create_database', ['name' => 'user_s_demo']). */
    public function call(string $module, string $function, array $params = []): array
    {
        $response = Http::withHeaders(['Authorization' => 'cpanel '.config('tenancy.cpanel.user').':'.config('tenancy.cpanel.token')])
            ->withoutVerifying()   // the panel is asked on this same server, by its local address
            ->timeout(60)->asForm()
            ->post(rtrim((string) config('tenancy.cpanel.url'), '/').'/execute/'.$module.'/'.$function, $params);

        $answer = $response->json();
        if (! $response->successful() || ! is_array($answer) || (int) ($answer['status'] ?? 0) !== 1) {
            $why = is_array($answer) ? implode(' ', (array) ($answer['errors'] ?? [])) : '';

            throw new RuntimeException('cPanel '.$module.'::'.$function.' failed: '.($why !== '' ? $why : 'HTTP '.$response->status()));
        }

        return $answer;
    }

    /** A database for a clinic, with every right on it for the MySQL user of the system. */
    public function createDatabase(string $name, string $user): void
    {
        $this->call('Mysql', 'create_database', ['name' => $name]);
        $this->call('Mysql', 'set_privileges_on_database', ['user' => $user, 'database' => $name, 'privileges' => 'ALL PRIVILEGES']);
    }

    public function dropDatabase(string $name): void
    {
        $this->call('Mysql', 'delete_database', ['name' => $name]);
    }

    /**
     * The address of a clinic under the base domain, served from the same folder as the system.
     * An address that is already there is fine. Any other domain is the owner's to point here.
     */
    public function addAddress(string $domain): bool
    {
        $base = (string) config('tenancy.base_domain');
        if (! str_ends_with($domain, '.'.$base)) {
            return false;
        }
        // with a "*" subdomain and its wildcard certificate the new address works, with https, at once
        if (config('tenancy.cpanel.wildcard')) {
            return true;
        }
        try {
            $this->call('SubDomain', 'addsubdomain', ['domain' => substr($domain, 0, -strlen('.'.$base)), 'rootdomain' => $base, 'dir' => (string) config('tenancy.cpanel.docroot')]);
        } catch (RuntimeException $e) {
            if (! str_contains(strtolower($e->getMessage()), 'already exists')) {
                throw $e;
            }
        }

        $this->askForCertificates();

        return true;
    }

    /** Certificates for addresses that have none yet: the panel is asked to look now, not at its next round. */
    public function askForCertificates(): void
    {
        try {
            $this->call('SSL', 'start_autossl_check');
        } catch (RuntimeException $e) {
            report($e);
        }
    }
}
