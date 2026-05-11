<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use SwissEid\LaravelSwissEid\Enums\VerificationState;
use SwissEid\LaravelSwissEid\Models\EidVerification;

function reapplyMigration(string $userIdType): void
{
    config()->set('swiss-eid.user_id_type', $userIdType);

    $table = config('swiss-eid.table_name', 'eid_verifications');
    Schema::dropIfExists($table);

    $migration = require __DIR__.'/../../database/migrations/2024_01_01_000000_create_eid_verifications_table.php';
    $migration->up();
}

function userIdSqliteType(): string
{
    $table = config('swiss-eid.table_name', 'eid_verifications');
    $columns = DB::select("PRAGMA table_info({$table})");

    foreach ($columns as $column) {
        if ($column->name === 'user_id') {
            return strtolower($column->type);
        }
    }

    return '';
}

it('creates user_id as integer when user_id_type is int (default)', function (): void {
    reapplyMigration('int');

    expect(userIdSqliteType())->toContain('integer');
});

it('creates user_id as varchar/char when user_id_type is uuid', function (): void {
    reapplyMigration('uuid');

    $type = userIdSqliteType();
    expect($type === 'varchar' || str_contains($type, 'char'))->toBeTrue();
});

it('creates user_id as varchar when user_id_type is string', function (): void {
    reapplyMigration('string');

    expect(userIdSqliteType())->toContain('varchar');
});

it('falls back to integer for unknown user_id_type values', function (): void {
    reapplyMigration('not-a-real-type');

    expect(userIdSqliteType())->toContain('integer');
});

it('persists a UUID user_id when configured for uuid', function (): void {
    reapplyMigration('uuid');

    $userUuid = Str::uuid()->toString();

    $model = EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'uuid-user-test',
        'user_id' => $userUuid,
        'state' => VerificationState::Pending,
        'credential_type' => 'betaid-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    expect($model->fresh()->user_id)->toBe($userUuid);
    expect(EidVerification::forUser($userUuid)->count())->toBe(1);
});

it('persists a string user_id when configured for string', function (): void {
    reapplyMigration('string');

    $model = EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'string-user-test',
        'user_id' => 'user-abc-123',
        'state' => VerificationState::Pending,
        'credential_type' => 'betaid-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    expect($model->fresh()->user_id)->toBe('user-abc-123');
});

it('persists an int user_id when configured for int', function (): void {
    reapplyMigration('int');

    $model = EidVerification::create([
        'id' => Str::uuid()->toString(),
        'verifier_id' => 'int-user-test',
        'user_id' => 42,
        'state' => VerificationState::Pending,
        'credential_type' => 'betaid-sdjwt',
        'requested_fields' => [],
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    expect((int) $model->fresh()->user_id)->toBe(42);
});
