<?php namespace Searchlight\Filters;

use Searchlight\Interfaces\IFilter;
use Searchlight\FilterCriteria;
use Searchlight\Exceptions\ConfigurationException;

/**
 * Apply a numeric range to a specific field when searching.
 */
class NumericRangeFilter implements IFilter
{
    const DEFAULT_DISPLAY = 'Slider';

    protected string $identifier;
    protected string $label;
    protected string $display;
    protected string $field;

    public function __construct(array $args)
    {
        $this->identifier = data_get($args, 'identifier');
        $this->label = data_get($args, 'label', $this->identifier);
        $this->display = data_get($args, 'display', self::DEFAULT_DISPLAY);
        $this->field = data_get($args, 'field');

        if (!$this->identifier) {
            throw new ConfigurationException(
                "NumericRangeFilter requires an 'identifier' argument"
            );
        }

        if (!$this->field) {
            throw new ConfigurationException(
                "NumericRangeFilter '{$this->identifier}' requires a 'field' argument"
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

        // TODO: Searchkit implementation only supports one range
        // see: https://github.com/searchkit/searchkit/blob/9a603095a55c724c839ee35302a24318c4e9b1b3/packages/searchkit-sdk/src/filters/NumericRangeFilter.ts#L22
        // This looks like it could be improved on.

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
     * Return a concrete `NumericRangeSelectedFilter` GraphQL type from response data
     *
     * @return array GraphQL type `NumericRangeSelectedFilter`
     */
    public function toSKSelectedFilter(FilterCriteria $criteria): array
    {
        // NOTE: While Searchkit's docs for summary.appliedFilters says
        // this returns a value string (https://www.searchkit.co/docs/core/reference/searchkit-sdk/filters/NumericRangeFilter)
        // that's not the implementation for https://github.com/searchkit/searchkit/blob/9a603095a55c724c839ee35302a24318c4e9b1b3/packages/searchkit-sdk/src/filters/NumericRangeFilter.ts#L29
        // Is that specific to the non-GraphQL SDK?
        return [
            'type' => 'NumericRangeSelectedFilter',

            'id' => $this->identifier.'_'.$criteria->min.'_'.$criteria->max,
            'identifier' => $this->identifier,
            'display' => $this->display,
            'label' => $this->label,

            // 'value' => $criteria->min.' - '.$criteria->max,
            'min' => $criteria->min,
            'max' => $criteria->max,
        ];
    }
}
