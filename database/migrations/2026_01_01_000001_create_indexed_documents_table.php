<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indexed_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('fingerprint')->unique();
            $table->json('cluster');
            $table->json('data');
            $table->timestamps();

            $table->index('fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indexed_documents');
    }
};
