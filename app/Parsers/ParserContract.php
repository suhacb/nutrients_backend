<?php
namespace App\Parsers;

use Illuminate\Support\Collection;

interface ParserContract
{
    /**
     * Parse raw data and return a collection of normalized Nutrient objects.
     *
     * This is the main convenience method for simple usage:
     * (new Parser())->parse($data);
     *
     * @param array $data Raw data (from file, API, etc.)
     * @return Collection<Model>
     */
    public function parse(array $data): Collection;

    /**
     * Set the raw data to be parsed. Supports fluent method chaining.
     *
     * @param array $data
     * @return static
     */
    public function setData(array $data): self;

    /**
     * Prepare the raw data into a normalized intermediate structure.
     * Supports fluent method chaining.
     *
     * @return static
     */
    public function prepare(): self;

    /**
     * Convert the intermediate structure into actual Model instances.
     * Supports fluent method chaining.
     *
     * @return static
     */
    public function convert(): self;

    /**
     * Retrieve the parsed collection of Model objects.
     *
     * @return Collection<Model>
     */
    public function get(): Collection;
}
