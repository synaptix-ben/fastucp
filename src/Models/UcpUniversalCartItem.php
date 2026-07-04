<?php

namespace FastUcp\Models;

use FastUcp\Data\UniversalCartItem;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UcpUniversalCartItem extends Model
{
    use HasUuids;

    protected $table = 'ucp_universal_cart_items';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'price' => 'integer',
        'quantity' => 'integer',
    ];

    public function toData(): UniversalCartItem
    {
        return new UniversalCartItem(
            id: $this->id,
            cartId: $this->cart_id,
            merchantUrl: $this->merchant_url,
            itemId: $this->item_id,
            title: $this->title,
            price: $this->price,
            quantity: $this->quantity,
            imageUrl: $this->image_url,
            currency: $this->currency,
            merchantName: $this->merchant_name,
            metadata: $this->metadata,
        );
    }
}
