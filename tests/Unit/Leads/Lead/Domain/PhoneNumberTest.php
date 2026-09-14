<?php

use Reformas\Leads\Lead\Domain\InvalidPhoneNumberError;
use Reformas\Leads\Lead\Domain\PhoneNumber;

it('normalizes Spanish numbers to international format', function (string $raw) {
    expect((new PhoneNumber($raw))->value)->toBe('+34600111222');
})->with([
    'nine digits' => '600111222',
    'with spaces' => '600 111 222',
    'with dashes' => '600-111-222',
    'with prefix' => '+34 600 111 222',
    'with 00 prefix' => '0034600111222',
    'with brackets and dots' => '(+34) 600.111.222',
]);

it('keeps foreign numbers with their own prefix', function () {
    expect((new PhoneNumber('+44 7700 900123'))->value)->toBe('+447700900123');
});

it('rejects values that are not phone numbers', function (string $raw) {
    new PhoneNumber($raw);
})->with([
    'empty' => '',
    'too short' => '12345',
    'letters' => 'abc600111222',
    'nine digits not Spanish' => '500111222',
    'too long' => '+34600111222000000',
])->throws(InvalidPhoneNumberError::class);
