<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ucp_orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('checkout_id')->index();
            $table->string('permalink_url')->nullable();
            $table->json('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ucp_orders');
    }
};
