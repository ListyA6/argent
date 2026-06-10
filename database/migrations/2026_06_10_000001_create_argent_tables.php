<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 9);
            $table->string('icon', 40);
            $table->string('sound_preset', 40)->default('pop');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('item');
            $table->unsignedBigInteger('amount'); // integer rupiah
            $table->string('note')->nullable();
            $table->dateTime('spent_at')->index();
            $table->timestamps();
        });

        Schema::create('keyword_rules', function (Blueprint $table) {
            $table->id();
            $table->string('keyword', 60)->unique();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('hits')->default(0);
            $table->boolean('is_seed')->default(false);
            $table->timestamps();
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->string('time', 5); // HH:MM, Asia/Jakarta
            $table->string('label')->default('');
            $table->boolean('enabled')->default(true);
            $table->date('last_sent_date')->nullable();
            $table->timestamps();
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique();
            $table->string('p256dh');
            $table->string('auth');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('keyword_rules');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('categories');
    }
};
