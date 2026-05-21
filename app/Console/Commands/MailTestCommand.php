<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Send a one-off test email through the configured mail driver. Handy for
 * smoke-testing Mailgun / SMTP / Mailjet without writing a tinker script.
 *
 *   php artisan mail:test you@example.com
 *   php artisan mail:test you@example.com --subject="Smoke test"
 *
 * Also useful to verify the MAIL_DISABLED / MAIL_ALLOWLIST switches in
 * AppServiceProvider — recipients that aren't on the allowlist will be
 * logged-and-dropped instead of delivered.
 */
class MailTestCommand extends Command
{
    protected $signature = 'mail:test {to : Recipient email} {--subject=Mailgun test : Subject line} {--body=Test from Lumen : Body text}';
    protected $description = 'Send a single test email through the configured mailer';

    public function handle(): int
    {
        $to      = $this->argument('to');
        $subject = (string) $this->option('subject');
        $body    = (string) $this->option('body');

        $this->info("Sending to {$to} via " . config('mail.driver', env('MAIL_DRIVER', 'unknown')));

        try {
            Mail::raw($body, function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            $this->error('Mail send failed: ' . $e->getMessage());
            return 1;
        }

        $this->info('Done. Check the inbox (or storage/logs/lumen-*.log if MAIL_DISABLED / MAIL_ALLOWLIST is suppressing).');
        return 0;
    }
}
