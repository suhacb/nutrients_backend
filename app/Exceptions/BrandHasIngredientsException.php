<?php

namespace App\Exceptions;

use Exception;

class BrandHasIngredientsException extends Exception
{
    protected $message = 'Cannot delete a brand that has associated ingredients.';

    public int $status = 409;

    public function __construct(?string $message = null)
    {
        if ($message !== null) {
            $this->message = $message;
        }

        parent::__construct($this->message);
    }
}
