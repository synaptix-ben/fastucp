<?php

namespace FastUcp\Models;

use Illuminate\Database\Eloquent\Model;

class UcpCheckoutSession extends Model
{
    protected $table = 'ucp_checkout_sessions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'expires_at' => 'datetime',
    ];
}
