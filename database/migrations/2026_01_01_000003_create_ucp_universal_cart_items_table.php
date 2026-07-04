<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ucp_universal_cart_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cart_id')->index();
            $table->string('merchant_url')->nullable();
            $table->string('merchant_name')->nullable();
            $table->string('item_id');
            $table->string('title');
            $table->integer('price');
            $table->integer('quantity')->default(1);
            $table->string('image_url')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['cart_id', 'merchant_url']);
            $table->unique(['cart_id', 'merchant_url', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ucp_universal_cart_items');
    }
};
