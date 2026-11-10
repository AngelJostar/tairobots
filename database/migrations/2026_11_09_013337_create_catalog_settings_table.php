<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->default(1);
            $table->json('categories');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_settings');
    }
};
