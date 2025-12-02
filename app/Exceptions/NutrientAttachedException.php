<?php

namespace App\Exceptions;

use Exception;

class NutrientAttachedException extends Exception
{
    /**
     * The exception message.
     *
     * @var string
     */
    protected $message = 'Nutrient is attached to one or more ingredients and cannot be deleted.';

    /**
     * Optionally, you can set a custom HTTP status code for API responses.
     *
     * @var int
     */
    public int $status = 422;

    /**
     * Constructor allows optional custom message.
     */
    public function __construct(?string $message = null)
    {
        if ($message !== null) {
            $this->message = $message;
        }

        parent::__construct($this->message);
    }
}
