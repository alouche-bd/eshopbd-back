<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdvInterOrderMailer extends Mailable
{
    use SerializesModels;

    public Order $order;
    public string $excelPath;

    public function __construct(Order $order, string $excelPath)
    {
        $this->order = $order;
        $this->excelPath = $excelPath;
    }

    public function build()
    {
        // No hardcoded ->from() — the global MAIL_FROM_ADDRESS / MAIL_FROM_NAME
        // is used so the sender matches whatever domain Mailgun has verified
        // for this project. Hardcoding a bare-domain address caused Mailgun
        // to silently drop the message when only the mg.* subdomain was
        // verified.
        return $this->view('emails.advInterOrder')
            ->with(['order' => $this->order])
            ->subject(sprintf('New distributor order — %s', $this->order->customer_reference))
            ->attach($this->excelPath, [
                'as'   => basename($this->excelPath),
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
