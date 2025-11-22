<?php

namespace LBHurtado\HyperVerge\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentSigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $transactionId,
        public Model $model,
        public mixed $signedDocument,
        public mixed $signatureMark,
        public array $metadata = [],
    ) {}
}
