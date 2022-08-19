<?php namespace McManning\Searchlight\Facets;

use McManning\Searchlight\Interfaces\IFacet;
use McManning\Searchlight\Interfaces\IFilter;
use McManning\Searchlight\FacetCriteria;
use McManning\Searchlight\FilterCriteria;
use McManning\Searchlight\Exceptions\ConfigurationException;

class RangeFacet implements IFacet, IFilter
{
    const DEFAULT_DISPLAY = 'RangeSliderFacet';

    protected string $identifier;
    protected string $label;
    protected string $field;
    protected string $display;

    protected int $min;
    protected int $max;
    protected int $interval;
    protected int $minDocCount;

    public function __construct(array $args)
    {
        $this->identifier = data_get($args, 'identifier');
        $this->label = data_get($args, 'label', $this->identifier);
        $this->field = data_get($args, 'field');
        $this->display = data_get($args, 'display', self::DEFAULT_DISPLAY);

        $this->min = data_get($args, 'min');
        $this->max = data_get($args, 'max');
        $this->interval = data_get($args, 'interval', 1);
        $this->minDocCount = data_get($args, 'min_doc_count', 0);

        if (!$this->identifier) {
            throw new ConfigurationException(
                "RangeFacet requires an 'identifier' argument"
            );
        }

        if (!$this->field) {
            throw new ConfigurationException(
                "RangeFacet '{$this->identifier}' requires a 'field' argument"
            );
        }

        if ($this->min === null || $this->max === null || $this->interval === null) {
            throw new ConfigurationException(
                "RangeFacet '{$this->identifier}' requires 'min', 'max', and 'interval' arguments"
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
        // TODO: This is just a duplicate of NumericRangeFilter.
        $rangeFilter = [];
        if ($criteria[0]->min !== null) {
            $rangeFilter['gte'] = $criteria[0]->min;
        }

        if ($criteria[0]->max !== null) {
            $rangeFilter['lte'] = $criteria[0]->max;
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
        return [
            'histogram' => [
                'field' => $this->field,
                'interval' => $this->interval,
                'min_doc_count' => $this->minDocCount,
                'extended_bounds' => [
                    'min' => $this->min,
                    'max' => $this->max,
                ],
            ],
        ];
    }

    /**
     * Return a `NumericRangeSelectedFilter` GraphQL type from response data
     *
     * @return array GraphQL type `NumericRangeSelectedFilter`
     */
    public function toSKSelectedFilter(FilterCriteria $criteria): array
    {
        // TODO: This is just a duplicate of NumericRangeFilter.
        // Can we just create a filter from a facet and use that?
        return [
            'type' => 'NumericRangeSelectedFilter',

            'id' => $this->identifier.'_'.$criteria->min.'_'.$criteria->max,
            'identifier' => $this->identifier,
            'display' => $this->display,
            'label' => $this->label,

            'min' => $criteria->min,
            'max' => $criteria->max,
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
            'type' => 'RangeFacet',

            'identifier' => $this->identifier,
            'label' => $this->label,
            'display' => $this->display,
            'entries' => array_map(fn ($entry) => [
                'label' => $entry['key'],
                'count' => $entry['doc_count'],
            ], $response['buckets'])
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function excludesOwnFilters(): bool
    {
        return false;
    }
}
