<?php

use LBHurtado\HyperVerge\Enums\SignatureMode;

test('it has proforma case', function () {
    expect(SignatureMode::PROFORMA->value)->toBe('proforma');
});

test('it has roll case', function () {
    expect(SignatureMode::ROLL->value)->toBe('roll');
});

test('proforma is default', function () {
    expect(SignatureMode::default())->toBe(SignatureMode::PROFORMA);
});

test('proforma is template', function () {
    expect(SignatureMode::PROFORMA->isTemplate())->toBeTrue()
        ->and(SignatureMode::PROFORMA->isRoll())->toBeFalse();
});

test('roll is not template', function () {
    expect(SignatureMode::ROLL->isTemplate())->toBeFalse()
        ->and(SignatureMode::ROLL->isRoll())->toBeTrue();
});

test('it returns correct labels', function () {
    expect(SignatureMode::PROFORMA->label())->toBe('Proforma (Template)')
        ->and(SignatureMode::ROLL->label())->toBe('Roll (Accumulate)');
});

test('it returns correct descriptions', function () {
    expect(SignatureMode::PROFORMA->description())
        ->toContain('fresh copy')
        ->and(SignatureMode::ROLL->description())
        ->toContain('accumulate');
});

test('it can be created from string', function () {
    $proforma = SignatureMode::from('proforma');
    $roll = SignatureMode::from('roll');
    
    expect($proforma)->toBe(SignatureMode::PROFORMA)
        ->and($roll)->toBe(SignatureMode::ROLL);
});
