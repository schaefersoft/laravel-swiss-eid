<?php

declare(strict_types=1);

use SwissEid\LaravelSwissEid\Enums\VerificationState;

it('marks success, failed and expired as terminal', function (): void {
    expect(VerificationState::Success->isTerminal())->toBeTrue();
    expect(VerificationState::Failed->isTerminal())->toBeTrue();
    expect(VerificationState::Expired->isTerminal())->toBeTrue();
});

it('marks pending as non-terminal', function (): void {
    expect(VerificationState::Pending->isTerminal())->toBeFalse();
});

it('returns german labels', function (): void {
    expect(VerificationState::Pending->label())->toBe('Ausstehend');
    expect(VerificationState::Success->label())->toBe('Erfolgreich');
    expect(VerificationState::Failed->label())->toBe('Fehlgeschlagen');
    expect(VerificationState::Expired->label())->toBe('Abgelaufen');
});

it('can be created from string value', function (): void {
    expect(VerificationState::from('pending'))->toBe(VerificationState::Pending);
    expect(VerificationState::from('success'))->toBe(VerificationState::Success);
});
