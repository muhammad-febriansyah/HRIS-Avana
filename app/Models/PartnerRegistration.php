<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerRegistration extends Model
{
    protected $fillable = [
        'full_name',
        'email',
        'whatsapp',
        'partner_type',
        'company_name',
        'network_size',
        'network_focus',
        'network_description',
        'social_link',
        'how_did_you_know',
        'terms_accepted',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'terms_accepted' => 'boolean',
        ];
    }
}
