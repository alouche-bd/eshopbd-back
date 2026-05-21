<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegistrationPassword extends Mailable
{
    use SerializesModels;

    public $password;
    public $email;

    public function __construct($password, $email)
    {
        $this->password = $password;
        $this->email = $email;
    }

    public function build()
    {
        return $this->view("emails.registrationPassword")
            ->with([
                'email' => $this->email,
                'password' => $this->password,
                'logoHead' => base_path('public/assets/LOGO BD/BIOTECH DENTAL HORIZONTAL/BIOTECH DENTAL BLANC/BIOTECH-DENTAL-BLANC_FILAIRE_SMALL.png'),
                'imageEmail' => base_path('public/assets/VISUELS/image_email_small.jpg'),
                'logoFoot' => base_path('public/assets/LOGO BD/BIOTECH DENTAL HORIZONTAL/BIOTECH DENTAL BLANC/logoFooter.png'),
            ])
            ->from('noreply@biotech-dental.com', 'Biotech Dental')
            ->subject("Confirmation d'inscription");
    }
}
