<?php

namespace App\Exceptions;

use Exception;

class SourceHasNutrientsException extends Exception
{
    protected $message = 'Cannot delete source: it has one or more nutrients attached.';

    public int $status = 409;

    public function __construct(?string $message = null)
    {
        if ($message !== null) {
            $this->message = $message;
        }

        parent::__construct($this->message);
    }
}
