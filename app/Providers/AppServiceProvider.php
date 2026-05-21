<?php

namespace App\Providers;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(\Illuminate\Contracts\Routing\ResponseFactory::class, function() {
            return new \Laravel\Lumen\Http\ResponseFactory();
        });
    }

    /**
     * Boot services.
     *
     * Mail allowlist for dev / staging:
     *   MAIL_DISABLED=true              → suppress every outbound email
     *   MAIL_ALLOWLIST=a@x.com,b@y.com  → only emails whose recipients all
     *                                     match one of those addresses go
     *                                     through; everything else is logged
     *                                     and dropped.
     *
     * Use MAIL_ALLOWLIST=${ADV_INTER_EMAIL} to let ADV_INTER mails through
     * while suppressing the rest. Leave both unset in production for
     * normal behavior.
     *
     * Returning false from a MessageSending listener cancels the send
     * (Illuminate\Mail\Mailer::sendNow checks the event response).
     */
    public function boot()
    {
        $this->app['events']->listen(MessageSending::class, function (MessageSending $event) {
            $disabled  = filter_var(env('MAIL_DISABLED', false), FILTER_VALIDATE_BOOLEAN);
            $allowlist = array_filter(array_map('trim', explode(',', (string) env('MAIL_ALLOWLIST', ''))));

            if ($disabled) {
                $this->logSuppressed($event, 'MAIL_DISABLED');
                return false;
            }

            if (empty($allowlist)) {
                return true; // No allowlist → normal behavior.
            }

            $recipients = $this->extractRecipients($event);
            foreach ($recipients as $address) {
                if (!in_array(strtolower($address), array_map('strtolower', $allowlist), true)) {
                    $this->logSuppressed($event, "Not on MAIL_ALLOWLIST: {$address}");
                    return false;
                }
            }
            return true;
        });
    }

    private function extractRecipients(MessageSending $event): array
    {
        // Lumen 8 ships Swiftmailer Mailer; Laravel 9+ uses Symfony Mailer.
        $message    = $event->message;
        $recipients = [];

        if (method_exists($message, 'getTo') && $message->getTo()) {
            // Swift_Message::getTo() returns ['email' => 'name'] map.
            foreach ((array) $message->getTo() as $email => $name) {
                $recipients[] = is_int($email) ? $name : $email;
            }
        } elseif (method_exists($message, 'getEnvelope')) {
            // Symfony Mailer path.
            foreach ($message->getEnvelope()->getRecipients() as $addr) {
                $recipients[] = $addr->getAddress();
            }
        }
        return $recipients;
    }

    private function logSuppressed(MessageSending $event, string $reason): void
    {
        $message = $event->message;
        $subject = method_exists($message, 'getSubject') ? $message->getSubject() : '(no subject)';
        Log::info('Mail suppressed: ' . $reason, [
            'subject'    => $subject,
            'recipients' => $this->extractRecipients($event),
        ]);
    }
}
