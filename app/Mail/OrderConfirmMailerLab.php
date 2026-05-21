<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmMailerLab extends Mailable
{
    use SerializesModels;

    public $order;

    public $lab;

    public function __construct($order, $lab)
    {
        $this->order = $order;
        $this->lab = $lab;
    }

    public function build()
    {
        return $this->view("emails.orderConfirmLab")
            ->with([
                'order' => $this->order,
                'lab' => $this->lab,
                'logoHead' => base_path('public/assets/LOGO BD/BIOTECH DENTAL HORIZONTAL/BIOTECH DENTAL BLANC/BIOTECH-DENTAL-BLANC_FILAIRE_SMALL.png'),
                'imageEmail' => base_path('public/assets/VISUELS/image_email_small.jpg'),
                'logoFoot' => base_path('public/assets/LOGO BD/BIOTECH DENTAL HORIZONTAL/BIOTECH DENTAL BLANC/logoFooter.png'),
            ])
            ->from('noreply@biotech-dental.com', 'Biotech Dental')
            ->subject("Récapitulatif de commande");
    }
}
