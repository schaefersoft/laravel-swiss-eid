<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $userIdType = config('swiss-eid.user_id_type', 'int');

        Schema::create(config('swiss-eid.table_name', 'eid_verifications'), function (Blueprint $table) use ($userIdType): void {
            $table->uuid('id')->primary();
            $table->string('verifier_id')->index();
            match ($userIdType) {
                'uuid' => $table->uuid('user_id')->nullable()->index(),
                'string' => $table->string('user_id')->nullable()->index(),
                default => $table->unsignedBigInteger('user_id')->nullable()->index(),
            };
            $table->string('state', 20)->default('pending')->index();
            $table->string('credential_type', 100);
            $table->json('requested_fields');
            $table->text('credential_data')->nullable(); // encrypted:array
            $table->json('metadata')->nullable();
            $table->text('deeplink')->nullable();
            $table->text('verification_url')->nullable();
            $table->timestamp('webhook_received_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('swiss-eid.table_name', 'eid_verifications'));
    }
};
