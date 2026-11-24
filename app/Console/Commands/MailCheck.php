<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Says which ways of sending mail work on this server: one small message through each of them. */
class MailCheck extends Command
{
    protected $signature = 'mail:check {to? : The address to write to (default: the "from" address)}';

    protected $description = 'Try each way of sending mail and say which ones work';

    public function handle(): int
    {
        $to = $this->argument('to') ?: config('mail.from.address');
        $this->line('default mailer: '.config('mail.default').' · from: '.config('mail.from.address'));
        foreach (['sendmail', 'local'] as $mailer) {
            try {
                Mail::mailer($mailer)->raw('A check of the mail of '.config('app.name').'.', fn ($m) => $m->to($to)->subject('Mail check ('.$mailer.')'));
                $this->info($mailer.': accepted');
            } catch (Throwable $e) {
                $this->warn($mailer.': '.mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 300));
            }
        }

        return self::SUCCESS;
    }
}
