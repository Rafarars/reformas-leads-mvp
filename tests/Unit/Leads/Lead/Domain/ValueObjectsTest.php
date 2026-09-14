<?php

use Reformas\Leads\Lead\Domain\Budget;
use Reformas\Leads\Lead\Domain\EmailAddress;
use Reformas\Leads\Lead\Domain\InvalidBudgetError;
use Reformas\Leads\Lead\Domain\InvalidEmailAddressError;
use Reformas\Leads\Lead\Domain\InvalidLeadDescriptionError;
use Reformas\Leads\Lead\Domain\InvalidLeadNameError;
use Reformas\Leads\Lead\Domain\InvalidLeadSourceError;
use Reformas\Leads\Lead\Domain\InvalidRenovationTypeError;
use Reformas\Leads\Lead\Domain\LeadDescription;
use Reformas\Leads\Lead\Domain\LeadId;
use Reformas\Leads\Lead\Domain\LeadName;
use Reformas\Leads\Lead\Domain\LeadSource;
use Reformas\Leads\Lead\Domain\RenovationType;
use Reformas\Shared\Domain\InvalidUuidError;

it('normalizes email case and surrounding spaces', function () {
    expect((new EmailAddress('  Ana.Garcia@Mail.COM '))->value)->toBe('ana.garcia@mail.com');
});

it('rejects malformed emails', function (string $raw) {
    new EmailAddress($raw);
})->with(['', 'ana', 'ana@', '@mail.com', 'ana garcia@mail.com'])->throws(InvalidEmailAddressError::class);

it('collapses repeated spaces in a name', function () {
    expect((new LeadName("  Ana   María\tGarcía "))->value)->toBe('Ana María García');
});

it('rejects names that are too short or too long', function (string $raw) {
    new LeadName($raw);
})->with(['', 'A', str_repeat('a', 121)])->throws(InvalidLeadNameError::class);

it('accepts a budget in euros within range', function () {
    expect((new Budget(25_000))->euros)->toBe(25_000);
});

it('rejects budgets out of range', function (int $euros) {
    new Budget($euros);
})->with([0, -1, 10_000_001])->throws(InvalidBudgetError::class);

it('lists the allowed renovation types when one is unknown', function () {
    try {
        RenovationType::fromValue('pool');
    } catch (InvalidRenovationTypeError $error) {
        expect($error->publicMessage())->toContain('kitchen')->toContain('full_home');

        return;
    }

    throw new RuntimeException('Se esperaba InvalidRenovationTypeError');
});

it('rejects an empty description', function () {
    new LeadDescription('   ');
})->throws(InvalidLeadDescriptionError::class);

it('rejects sources that are not slugs', function (string $raw) {
    new LeadSource($raw);
})->with(['', 'Web Form', 'web_form', '-web'])->throws(InvalidLeadSourceError::class);

it('rejects malformed identifiers', function () {
    new LeadId('not-a-uuid');
})->throws(InvalidUuidError::class);
