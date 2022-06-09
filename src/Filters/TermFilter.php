<?php namespace McManning\Searchlight\Filters;

use McManning\Searchlight\Interfaces\IFilter;
use McManning\Searchlight\FilterCriteria;
use McManning\Searchlight\Exceptions\ConfigurationException;

/**
 * Apply a text filter to a specific field when searching.
 */
class TermFilter implements IFilter
{
    const DEFAULT_DISPLAY = 'TermFilter';

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
                "TermFilter requires an 'identifier' argument"
            );
        }

        if (!$this->field) {
            throw new ConfigurationException(
                "TermFilter '{$this->identifier}' requires a 'field' argument"
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
        // TODO (SQL): Plugin here.

        /*
            TODO: Conjunctive vs disjunctive support
            Example - the following should work but doesn't in Searchkit's impl:

            filters: [
                { identifier: "category", value: "Instrument" }
                { identifier: "category", value: "Service" }
            ]

            It could be argued that a different TermsFilter should be used for these.
            But it also makes sense to follow the precedent set by RefinementSelectFacet
            where an optional 'multipleSelect' arg can be provided.
        */

        $field = $this->field;
        return [[
            'bool' => [
                'filter' => array_map(fn (FilterCriteria $filter) => [
                    'term' => [ $field => $filter->value ]
                ], $criteria),
            ]
        ]];
    }

    /**
     * Return a concrete `ValueSelectedFilter` GraphQL type from response data
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
}
