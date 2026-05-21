<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmNoPriceMailer extends Mailable
{
    use SerializesModels;

    public $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function build()
    {
        return $this->view("emails.orderConfirmNoPrice")
            ->with([
                'order' => $this->order,
                'logoHead' => base_path('public/assets/LOGO BD/BIOTECH DENTAL HORIZONTAL/BIOTECH DENTAL BLANC/BIOTECH-DENTAL-BLANC_FILAIRE_SMALL.png'),
                'imageEmail' => base_path('public/assets/VISUELS/image_email_small.jpg'),
                'logoFoot' => base_path('public/assets/LOGO BD/BIOTECH DENTAL HORIZONTAL/BIOTECH DENTAL BLANC/logoFooter.png'),
            ])
            ->from('noreply@biotech-dental.com', 'Biotech Dental')
            ->subject("Récapitulatif de commande");
    }
}
