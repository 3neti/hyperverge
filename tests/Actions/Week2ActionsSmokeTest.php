<?php

use LBHurtado\HyperVerge\Actions\Results\ProcessKYCData;
use LBHurtado\HyperVerge\Actions\Results\StoreKYCImages;
use LBHurtado\HyperVerge\Actions\Results\ValidateKYCResult;

describe('Week 2 Actions', function () {
    it('can instantiate ProcessKYCData action', function () {
        $action = new ProcessKYCData();
        expect($action)->toBeInstanceOf(ProcessKYCData::class);
    });

    it('can instantiate StoreKYCImages action', function () {
        $action = new StoreKYCImages();
        expect($action)->toBeInstanceOf(StoreKYCImages::class);
    });

    it('can instantiate ValidateKYCResult action', function () {
        $action = new ValidateKYCResult();
        expect($action)->toBeInstanceOf(ValidateKYCResult::class);
    });

    it('ProcessKYCData has correct command signature', function () {
        $action = new ProcessKYCData();
        expect($action->commandSignature)->toContain('hyperverge:process-data');
        expect($action->commandSignature)->toContain('transactionId');
    });

    it('StoreKYCImages has correct command signature', function () {
        $action = new StoreKYCImages();
        expect($action->commandSignature)->toContain('hyperverge:store-images');
        expect($action->commandSignature)->toContain('transactionId');
    });

    it('ValidateKYCResult has correct command signature', function () {
        $action = new ValidateKYCResult();
        expect($action->commandSignature)->toContain('hyperverge:validate');
        expect($action->commandSignature)->toContain('transactionId');
    });
});
