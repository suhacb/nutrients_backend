<?php
namespace App\Data;

use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Model;
use App\Data\DataTransferObjectContract;
use Illuminate\Support\Facades\Validator;

abstract class DataTransferObject implements DataTransferObjectContract
{
    /**
     * The original raw data.
     */
    protected array $raw = [];

    /**
     * Validation errors, if any.
     */
    protected array $errors = [];

    /**
     * Base constructor.
     */
    final public function __construct(array $data)
    {
        $this->raw = $data;
        $this->validate();
    }

    /**
     * Named constructor for consistency.
     */
    public static function fromArray(array $data): static
    {
        return new static($data);
    }

    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * Default validation logic — override `rules()` in subclasses.
     */
    public function validate(): void
    {
        if (!method_exists($this, 'rules')) {
            return;
        }

        $validator = Validator::make($this->raw, $this->rules());

        if ($validator->fails()) {
            $this->errors = $validator->errors()->toArray();
        }
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Utility to safely fetch nested keys.
     */
    protected function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->raw, $key, $default);
    }

    /**
     * Must be implemented by subclass to define model-ready array.
     */
    abstract public function toArray(): array;

    /**
     * Must be implemented by subclass to return model instance.
     */
    abstract public function toModel(): Model;
}