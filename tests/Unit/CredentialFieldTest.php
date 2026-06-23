<?php

declare(strict_types=1);

use SwissEid\LaravelSwissEid\Enums\CredentialField;

it('returns the DCQL claim path for each field', function (CredentialField $field, array $expected): void {
    expect($field->path())->toBe($expected);
})->with([
    [CredentialField::AgeOver18,    ['age_over_18']],
    [CredentialField::AgeOver16,    ['age_over_16']],
    [CredentialField::GivenName,    ['given_name']],
    [CredentialField::FamilyName,   ['family_name']],
    [CredentialField::DateOfBirth,  ['birth_date']],
    [CredentialField::Nationality,  ['nationality']],
    [CredentialField::PlaceOfBirth, ['birth_place']],
    [CredentialField::Gender,       ['sex']],
]);

it('still exposes the legacy JSON path for backwards compatibility', function (): void {
    expect(CredentialField::GivenName->jsonPath())->toBe('$.given_name');
});
