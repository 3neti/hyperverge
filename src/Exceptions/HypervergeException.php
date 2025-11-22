<?php

namespace LBHurtado\HyperVerge\Exceptions;

use Exception;

class HypervergeException extends Exception
{
    public function __construct(
        string $message,
        public array $response = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getResponse(): array
    {
        return $this->response;
    }
}
