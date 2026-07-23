<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('preferred_language')->nullable();
            $table->timestamps();
        });

        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('vehicle_name')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->string('vehicle_type')->default('sedan');
            $table->string('tourism_license_no')->nullable();
            $table->string('approval_state')->default('incomplete');
            $table->boolean('online_status')->default(false);
            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
            $table->string('payment_account_id')->nullable();
            $table->boolean('payout_enabled')->default(false);
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('concierge_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('hotel_name')->nullable();
            $table->string('hotel_ice')->nullable();
            $table->string('hotel_address')->nullable();
            $table->string('hotel_website')->nullable();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('driver_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->timestamp('reported_at');
            $table->timestamps();
        });

        Schema::create('fare_configs', function (Blueprint $table) {
            $table->id();
            $table->decimal('base', 10, 2);
            $table->decimal('per_km', 10, 2);
            $table->decimal('per_min', 10, 2);
            $table->decimal('per_pax', 10, 2);
            $table->decimal('floor', 10, 2);
            $table->decimal('sedan_multiplier', 6, 2)->default(1);
            $table->decimal('minivan_multiplier', 6, 2)->default(1.25);
            $table->decimal('suv_multiplier', 6, 2)->default(1.35);
            $table->decimal('minibus_multiplier', 6, 2)->default(1.75);
            $table->decimal('luxury_multiplier', 6, 2)->default(2);
            $table->decimal('platform_fee_pct', 6, 4)->default(0.15);
            $table->string('currency', 3)->default('MAD');
            $table->boolean('is_active')->default(true);
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('concierge_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('hotel_name')->nullable();
            $table->string('guest_name')->nullable();
            $table->json('languages')->nullable();
            $table->unsignedSmallInteger('pax')->default(1);
            $table->unsignedSmallInteger('luggage')->default(0);
            $table->string('pickup_name')->nullable();
            $table->string('pickup_address');
            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();
            $table->string('dropoff_name')->nullable();
            $table->string('dropoff_address');
            $table->decimal('dropoff_lat', 10, 7)->nullable();
            $table->decimal('dropoff_lng', 10, 7)->nullable();
            $table->decimal('distance_km', 8, 2);
            $table->unsignedSmallInteger('eta_min');
            $table->decimal('offered_fare', 10, 2);
            $table->decimal('final_fare', 10, 2)->nullable();
            $table->string('payment_method')->default('cash');
            $table->string('status')->default('searching');
            $table->foreignId('assigned_driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('message')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('order_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('points_balance', 12, 2)->default(0);
            $table->decimal('wallet_balance', 12, 2)->default(0);
            $table->unsignedSmallInteger('free_rides_remaining')->default(0);
            $table->string('currency', 3)->default('MAD');
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('source')->nullable();
            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('concierge_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('fare', 10, 2)->default(0);
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('net', 10, 2)->default(0);
            $table->string('currency', 3)->default('MAD');
            $table->string('status')->default('succeeded');
            $table->json('payment_provider_references')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction');
            $table->string('entry_type');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('points_delta', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->decimal('points_after', 12, 2)->default(0);
            $table->string('reason');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('sender_role');
            $table->text('text')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('wallet_ledger_entries');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('order_status_events');
        Schema::dropIfExists('order_offers');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('fare_configs');
        Schema::dropIfExists('driver_locations');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('concierge_profiles');
        Schema::dropIfExists('driver_profiles');
        Schema::dropIfExists('rider_profiles');
    }
};
