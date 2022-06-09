<?php namespace McManning\Searchlight;

/**
 * Input filtering provided by the API consumer.
 *
 * This parses out the GraphQL input type `SKFiltersSet`
 * for use in applying filter values for search results
 */
class FilterCriteria
{
    public string $identifier;
    public ?string $value;
    public ?float $min;
    public ?float $max;
    public ?string $dateMin;
    public ?string $dateMax;
    // public ?GeoBoundingBoxInput $geoBoundingBox;
    public ?int $level;

    public function __construct(array $args)
    {
        $this->identifier = data_get($args, 'identifier');
        $this->value = data_get($args, 'value');
        $this->min = data_get($args, 'min');
        $this->max = data_get($args, 'max');
        $this->dateMin = data_get($args, 'dateMin');
        $this->dateMax = data_get($args, 'dateMax');
        $this->level = data_get($args, 'level');
    }
}
