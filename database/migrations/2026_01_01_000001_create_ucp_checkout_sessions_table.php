<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ucp_checkout_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('status')->default('incomplete')->index();
            $table->string('currency', 3)->default('USD');
            $table->json('data');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ucp_checkout_sessions');
    }
};
