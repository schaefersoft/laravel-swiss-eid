<?php

declare(strict_types=1);

it('runs the install command and skips migrations when declined', function (): void {
    $this->artisan('swiss-eid:install')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsOutputToContain('Installing Laravel Swiss eID...')
        ->expectsOutputToContain('Add the following variables to your .env file:')
        ->expectsOutputToContain('Laravel Swiss eID installed successfully.')
        ->assertExitCode(0);
});

it('runs the install command and runs migrations when confirmed', function (): void {
    $this->artisan('swiss-eid:install')
        ->expectsConfirmation('Would you like to run the migrations now?', 'yes')
        ->assertExitCode(0);
});
