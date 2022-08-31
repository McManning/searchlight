<?php namespace Searchlight\Filters;

use Searchlight\Interfaces\IFilter;
use Searchlight\FilterCriteria;
use Searchlight\Exceptions\ConfigurationException;

/**
 * Apply a date range filter to a specific field when searching
 */
class DateRangeFilter implements IFilter
{
    const DEFAULT_DISPLAY = 'DateRangeFilter';

    protected string $identifier;
    protected string $label;
    protected string $display;
    protected string $field;

    public function __construct(array $args)
    {
        $this->identifier = data_get($args, 'identifier');
        $this->label = data_get($args, 'label');
        $this->display = data_get($args, 'display', self::DEFAULT_DISPLAY);
        $this->field = data_get($args, 'field');

        if (!$this->identifier) {
            throw new ConfigurationException(
                "DateRangeFilter requires an 'identifier' argument"
            );
        }

        if (!$this->field) {
            throw new ConfigurationException(
                "DateRangeFilter '{$this->identifier}' requires a 'field' argument"
            );
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * {@inheritDoc}
     */
    public function getFilters(array $criteria): array
    {
        $field = $this->field;
        return [[
            'bool' => [
                'filter' => array_map(function (FilterCriteria $filter) use ($field) {
                    $range = [];
                    if ($filter->dateMin) {
                        $range['gte'] = $filter->dateMin;
                    }
                    if ($filter->dateMax) {
                        $range['lte'] = $filter->dateMax;
                    }

                    return ['range' => [[$field] => $range]];
                }, $criteria)
            ]
        ]];
    }

    /**
     * Return a concrete `DateRangeSelectedFilter` GraphQL type from response data
     *
     * @return array GraphQL type `DateRangeSelectedFilter`
     */
    public function toSKSelectedFilter(FilterCriteria $criteria): array
    {
        return [
            'type' => 'DateRangeSelectedFilter',

            'id' => $this->identifier.'_'.$criteria->dateMin.'_'.$criteria->dateMax,
            'identifier' => $this->identifier,
            'display' => $this->display,
            'label' => $this->label,

            'dateMin' => $criteria->dateMin,
            'dateMax' => $criteria->dateMax,
        ];
    }
}
