<?php

declare(strict_types=1);

use SwissEid\LaravelSwissEid\Enums\CredentialField;

it('returns the correct json path for each field', function (CredentialField $field, string $expected): void {
    expect($field->jsonPath())->toBe($expected);
})->with([
    [CredentialField::AgeOver18,    '$.age_over_18'],
    [CredentialField::AgeOver16,    '$.age_over_16'],
    [CredentialField::GivenName,    '$.given_name'],
    [CredentialField::FamilyName,   '$.family_name'],
    [CredentialField::DateOfBirth,  '$.birth_date'],
    [CredentialField::Nationality,  '$.nationality'],
    [CredentialField::PlaceOfBirth, '$.place_of_birth'],
    [CredentialField::Gender,       '$.gender'],
]);
