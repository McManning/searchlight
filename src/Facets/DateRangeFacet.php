<?php namespace McManning\Searchlight\Facets;

use McManning\Searchlight\Interfaces\IFacet;
use McManning\Searchlight\FacetCriteria;
use McManning\Searchlight\FilterCriteria;
use McManning\Searchlight\Exceptions\ConfigurationException;

class DateRangeFacet implements IFacet
{
    const DEFAULT_DISPLAY = 'DateRange';

    protected string $identifier;
    protected string $label;
    protected string $field;
    protected string $display;

    public function __construct(array $args)
    {
        $this->identifier = data_get($args, 'identifier');
        $this->label = data_get($args, 'label', $this->identifier);
        $this->field = data_get($args, 'field');
        $this->display = data_get($args, 'display', self::DEFAULT_DISPLAY);

        if (!$this->identifier) {
            throw new ConfigurationException(
                "DateRangeFacet requires an 'identifier' argument"
            );
        }

        if (!$this->field) {
            throw new ConfigurationException(
                "DateRangeFacet '{$this->identifier}' requires a 'field' argument"
            );
        }
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * {@inheritDoc}
     */
    public function getFilters(array $criteria): array
    {
        $rangeFilter = [];
        if ($criteria[0]->dateMin !== null) {
            $rangeFilter['gte'] = $criteria[0]->dateMin;
        }

        if ($criteria[0]->dateMax !== null) {
            $rangeFilter['lte'] = $criteria[0]->dateMax;
        }

        $field = $this->field;

        return [[
            'range' => [ $field => $rangeFilter ]
        ]];
    }

    /**
     * {@inheritDoc}
     */
    public function getAggregation(FacetCriteria $criteria): array
    {
        return [];
    }

    /**
     * Return a `NumericRangeSelectedFilter` GraphQL type from response data
     *
     * @return array GraphQL type `NumericRangeSelectedFilter`
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

    /**
     * Convert an aggregation bucket into a concrete type for `SKFacetSet`.
     *
     * @return array GraphQL type `RangeFacet`
     */
    public function toSKFacetSet(array $response): array
    {
        return [
            'type' => 'DateRangeFacet',

            'identifier' => $this->identifier,
            'label' => $this->label,
            'display' => $this->display,
            'entries' => null,
        ];
    }
}
