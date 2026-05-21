<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AksRegistrationMailer extends Mailable
{
    use SerializesModels;

    public $infos;

    public function __construct($infos)
    {
        $this->infos = $infos;
    }

    public function build()
    {
        return $this->view("emails.askRegistration")
            ->with([
                'infos' => $this->infos,
                'logoHead' => base_path('public/assets/LOGO BD/BIOTECH DENTAL HORIZONTAL/BIOTECH DENTAL BLANC/BIOTECH-DENTAL-BLANC_FILAIRE_SMALL.png'),
                'imageEmail' => base_path('public/assets/VISUELS/image_email_small.jpg'),
                'logoFoot' => base_path('public/assets/LOGO BD/BIOTECH DENTAL HORIZONTAL/BIOTECH DENTAL BLANC/logoFooter.png'),
            ])
            ->from('noreply@biotech-dental.com', 'Biotech Dental')
            ->subject("Demande d'inscription SalesForce");
    }
}
