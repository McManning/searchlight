<?php namespace Searchlight\Facets;

use Searchlight\Interfaces\IFacet;
use Searchlight\Interfaces\IFilter;
use Searchlight\FacetCriteria;
use Searchlight\FilterCriteria;
use Searchlight\Exceptions\ConfigurationException;

class RefinementSelectFacet implements IFacet, IFilter
{
    const DEFAULT_SIZE = 5;
    const DEFAULT_DISPLAY = 'ListFacet';

    const SORT_BY_VALUE = 0;
    const SORT_BY_COUNT = 1;

    protected string $identifier;
    protected string $label;
    protected string $field;

    /** @var int One of `SORT_BY_*` constants */
    protected int $sort;

    /**
     * @var string  `ListFacet`, `ComboFacet` or an arbitrary string to
     *              define how frontend components should display this facet.
     */
    protected string $display;

    protected int $size;
    protected bool $multipleSelect;

    public function __construct(array $args)
    {
        $this->identifier = data_get($args, 'identifier');
        $this->label = data_get($args, 'label', $this->identifier);
        $this->field = data_get($args, 'field');
        $this->display = data_get($args, 'display', self::DEFAULT_DISPLAY);

        $this->sort = data_get($args, 'order', self::SORT_BY_VALUE);
        $this->size = data_get($args, 'size', self::DEFAULT_SIZE);
        $this->multipleSelect = data_get($args, 'multiple_select', false);

        if (!$this->identifier) {
            throw new ConfigurationException(
                "RefinementSelectFacet requires an 'identifier' argument"
            );
        }

        if (!$this->field) {
            throw new ConfigurationException(
                "RefinementSelectFacet '{$this->identifier}' requires a 'field' argument"
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
    public function getFilters(array $filters): array
    {
        $condition = $this->multipleSelect ? 'should' : 'must';

        $field = $this->field;
        return [[
            'bool' => [
                $condition => array_map(fn (FilterCriteria $filter) => [
                    'term' => [ $field => $filter->value ]
                ], $filters)
            ]
        ]];
    }

    /**
     * {@inheritDoc}
     */
    public function getAggregation(FacetCriteria $criteria): array
    {
        $terms = [
            'field' => $this->field,
            'size' => $criteria->getSize() ?? $this->size,
            'order' => $this->getAggregationOrder(),
        ];

        $query = $criteria->getQueryAsRegex();
        if ($query) {
            $terms['include'] = $query;
        }

        return [
            'terms' => $terms,
        ];
    }

    /**
     * Build ElasticSearch order JSON for an aggregation
     */
    private function getAggregationOrder(): array
    {
        if ($this->sort === self::SORT_BY_VALUE) {
            return [ '_key' => 'asc' ];
        }

        // SORT_BY_COUNT
        return  [ '_count' => 'desc' ];
    }

    /**
     * Return a `ValueSelectedFilter` GraphQL type from response data
     *
     * @return array GraphQL type `ValueSelectedFilter`
     */
    public function toSKSelectedFilter(FilterCriteria $criteria): array
    {
        return [
            'type' => 'ValueSelectedFilter',

            'id' => $this->identifier.'_'.$criteria->value,
            'identifier' => $this->identifier,
            'display' => $this->display,
            'label' => $this->label,
            'value' => $criteria->value,
        ];
    }

    /**
     * Convert an aggregation bucket into a concrete type for `SKFacetSet`.
     *
     * @return array GraphQL type `RefinementSelectFacet`
     */
    public function toSKFacetSet(array $bucket): array
    {
        return [
            'type' => 'RefinementSelectFacet',

            'identifier' => $this->identifier,
            'label' => $this->label,
            'display' => $this->display,
            'entries' => array_map(fn ($entry) => [
                'label' => $entry['key'],
                'count' => $entry['doc_count'],
            ], $bucket['buckets'])
        ];
    }

    /**
     * {@inheritDoc}
     *
     * Returns true if multiple disjunctive values can be selected by setting `multiple_select`.
     */
    public function excludesOwnFilters(): bool
    {
        return $this->multipleSelect === true;
    }
}
