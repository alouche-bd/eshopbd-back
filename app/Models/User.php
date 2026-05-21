<?php

namespace App\Models;

use App\Constants\UserType;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordInterface;
use Illuminate\Notifications\Notifiable;

class User extends Model implements AuthenticatableContract, AuthorizableContract, JWTSubject, CanResetPasswordInterface
{
    use Authenticatable, Authorizable, HasFactory, CanResetPasswordTrait, Notifiable;

    protected $fillable = [
        'email',
        'user_type',
        'billing_country_code',
        'sage_client_code',
        'representative_code',
        'representative_name',
        'currency',
        'sage_facturation_address',
        'sage_livraison_address',
        'sage_synced_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'sage_facturation_address' => 'array',
        'sage_livraison_address'   => 'array',
        'sage_synced_at'           => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function whishlist()
    {
        return $this->hasMany(Wishlist::class, 'user_id');
    }

    public function order()
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    public function isDistributor(): bool
    {
        return $this->user_type === UserType::DISTRIBUTEUR;
    }

    public function isAdvInter(): bool
    {
        return $this->user_type === UserType::ADV_INTER;
    }
}
