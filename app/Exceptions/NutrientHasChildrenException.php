<?php

namespace App\Exceptions;

use Exception;

class NutrientHasChildrenException extends Exception
{
    protected $message = 'Cannot delete nutrient: it has one or more non-deleted children.';

    public int $status = 409;

    public function __construct(?string $message = null)
    {
        if ($message !== null) {
            $this->message = $message;
        }

        parent::__construct($this->message);
    }
}