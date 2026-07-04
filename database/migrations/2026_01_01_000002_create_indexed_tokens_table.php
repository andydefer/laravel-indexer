<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indexed_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained('indexed_documents')
                ->onDelete('cascade');
            $table->string('token_type');
            $table->string('token');
            $table->string('field')->nullable();
            $table->timestamps();

            $table->index(['token', 'field']);
            $table->index(['token_type', 'token']);
            $table->index('token'); // Index pour les recherches LIKE
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indexed_tokens');
    }
};
