<?php
namespace App\Data;

use Illuminate\Support\Arr;
use App\Data\DataTransferObjectContract;
use Illuminate\Database\Eloquent\Model;

abstract class DataTransferObject implements DataTransferObjectContract
{
    /**
     * Store instances per subclass.
     */
    protected static array $instances = [];

    /**
     * The original raw data.
     */
    protected ?array $raw = null;

    /**
     * Prevent direct instantiation.
     */
    protected function __construct() {}

    /**
     * Get the singleton instance.
     */
    public static function instance(): static
    {
        $class = static::class;

        if (!isset(static::$instances[$class])) {
            static::$instances[$class] = new static();
        }

        return static::$instances[$class];
    }

    /**
     * Load raw data into the singleton for transformation.
     */
    public function load(array $data): static
    {
        $this->raw = $data;
        return $this;
    }

    /**
     * Get raw data.
     */
    public function getRaw(): ?array
    {
        return $this->raw;
    }

    /**
     * Utility to safely fetch nested keys from raw data.
     */
    protected function get(string $key, mixed $default = null): mixed
    {
        if (!$this->raw) {
            return $default;
        }
        return Arr::get($this->raw, $key, $default);
    }

    /**
     * Convert loaded data to internal array structure.
     */
    abstract public function toModel(): array;

    /**
     * Convert loaded data to internal array structure.
     */
    abstract public function toStage(array $context = []): array;
}