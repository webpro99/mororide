<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('stripe');
            $table->string('provider_account_id')->nullable()->unique();
            $table->string('country', 2)->nullable();
            $table->string('status')->default('not_started');
            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('details_submitted')->default(false);
            $table->json('capabilities')->nullable();
            $table->json('requirements')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('stripe');
            $table->string('provider_intent_id')->nullable()->unique();
            $table->string('purpose');
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('status')->default('initiating');
            $table->string('idempotency_key')->unique();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'purpose']);
            $table->index(['user_id', 'purpose', 'status']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('stripe');
            $table->string('provider_event_id')->unique();
            $table->string('type');
            $table->boolean('livemode')->default(false);
            $table->string('payload_hash', 64);
            $table->string('status')->default('processing');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('wallet_ledger_entries', function (Blueprint $table) {
            $table->foreignId('payment_intent_id')
                ->nullable()
                ->after('transaction_id')
                ->constrained('payment_intents')
                ->nullOnDelete();
            $table->unique(['payment_intent_id', 'entry_type']);
        });
    }

    public function down(): void
    {
        Schema::table('wallet_ledger_entries', function (Blueprint $table) {
            $table->dropUnique(['payment_intent_id', 'entry_type']);
            $table->dropConstrainedForeignId('payment_intent_id');
        });

        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('payment_accounts');
    }
};
