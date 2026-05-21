<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordMailer extends Mailable
{
    use SerializesModels;

    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function build()
    {
        return $this->view("emails.forgotPassword")
            ->with([
                'token' => $this->token,
                'logoHead' => base_path('public/assets/LOGO BD/BIOTECH DENTAL HORIZONTAL/BIOTECH DENTAL BLANC/BIOTECH-DENTAL-BLANC_FILAIRE_SMALL.png'),
                'imageEmail' => base_path('public/assets/VISUELS/image_email_small.jpg'),
                'logoFoot' => base_path('public/assets/LOGO BD/BIOTECH DENTAL HORIZONTAL/BIOTECH DENTAL BLANC/logoFooter.png'),
            ])
            ->from('noreply@biotech-dental.com', 'Biotech Dental')
            ->subject("Mot de passe oublié");
    }
}
