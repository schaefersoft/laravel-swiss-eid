<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

it('reports connection successful when the verifier responds with 200', function (): void {
    Http::fake([
        'localhost:8083/*' => Http::response([
            'state' => 'PENDING',
        ], 200),
    ]);

    $this->artisan('swiss-eid:test-connection')
        ->expectsOutputToContain('Connection successful.')
        ->assertExitCode(0);
});

it('reports connection successful when the verifier responds with 404 (reachable)', function (): void {
    Http::fake([
        'localhost:8083/*' => Http::response(['error' => 'not found'], 404),
    ]);

    $this->artisan('swiss-eid:test-connection')
        ->expectsOutputToContain('Connection successful (verifier is reachable).')
        ->assertExitCode(0);
});

it('reports an error when the verifier returns a non-4xx server error', function (): void {
    Http::fake([
        'localhost:8083/*' => Http::response(['error' => 'internal'], 500),
    ]);

    $this->artisan('swiss-eid:test-connection')
        ->expectsOutputToContain('Verifier returned an error:')
        ->assertExitCode(1);
});
