<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('swiss-eid.table_name', 'eid_verifications'), function (Blueprint $table): void {
            $table->string('error_code', 100)->nullable()->after('credential_data');
            $table->text('error_description')->nullable()->after('error_code');
        });
    }

    public function down(): void
    {
        $table = (string) config('swiss-eid.table_name', 'eid_verifications');

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            foreach (['error_code', 'error_description'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $blueprint->dropColumn($column);
                }
            }
        });
    }
};
