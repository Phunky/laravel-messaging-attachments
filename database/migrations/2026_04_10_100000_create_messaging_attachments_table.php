<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(messaging_table('attachments'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')
                ->constrained(messaging_table('messages'))
                ->cascadeOnDelete();
            $table->foreignId('conversation_id')
                ->constrained(messaging_table('conversations'))
                ->cascadeOnDelete();
            $table->string('type');
            $table->string('disk')->nullable();
            $table->string('path');
            $table->string('url')->nullable();
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['message_id', 'order', 'id'], 'attachments_message_order_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(messaging_table('attachments'));
    }
};
