<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('order_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('type', ['push', 'email', 'whatsapp']);
            $table->string('event');
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
    }
};
