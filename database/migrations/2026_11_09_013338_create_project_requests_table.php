<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('request_key')->unique();
            $table->string('payload_hash', 64);
            $table->string('nombre', 100);
            $table->string('empresa', 160);
            $table->string('email');
            $table->string('sector');
            $table->text('objetivo');
            $table->boolean('consentimiento');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_requests');
    }
};
