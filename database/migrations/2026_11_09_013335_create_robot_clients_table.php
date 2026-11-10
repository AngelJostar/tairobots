<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('robot_clients', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->json('state');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('robot_clients');
    }
};
