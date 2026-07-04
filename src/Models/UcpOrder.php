<?php

namespace FastUcp\Models;

use Illuminate\Database\Eloquent\Model;

class UcpOrder extends Model
{
    protected $table = 'ucp_orders';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];
}
