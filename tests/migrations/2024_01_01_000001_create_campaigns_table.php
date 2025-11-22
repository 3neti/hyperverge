<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('workflow_id')->default('onboarding');
            $table->boolean('is_active')->default(true);
            
            // Document signing columns
            $table->string('signature_mode')->default('proforma');
            $table->json('used_tiles')->nullable();
            $table->integer('max_tiles')->default(9);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
