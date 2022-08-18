<?php namespace McManning\Searchlight\Facets;

use McManning\Searchlight\Interfaces\IFacet;
use McManning\Searchlight\FacetCriteria;
use McManning\Searchlight\FilterCriteria;
use McManning\Searchlight\Exceptions\ConfigurationException;

class HierarchicalMenuFacet implements IFacet
{
    const DEFAULT_DISPLAY = 'HierarchicalMenu';

    protected string $identifier;
    protected string $label;

    /** @var string[] */
    protected array $fields;

    /**
     * @var string  `HierarchicalMenu` or an arbitrary string to
     *              define how frontend components should display this facet.
     */
    protected string $display;

    protected int $size;
    protected bool $multipleSelect;

    public function __construct(array $args)
    {
        $this->identifier = data_get($args, 'identifier');
        $this->label = data_get($args, 'label', $this->identifier);
        $this->fields = data_get($args, 'fields');
        $this->display = data_get($args, 'display', self::DEFAULT_DISPLAY);

        if (!$this->identifier) {
            throw new ConfigurationException(
                "HierarchicalMenuFacet requires an 'identifier' argument"
            );
        }

        if (!$this->field) {
            throw new ConfigurationException(
                "HierarchicalMenuFacet '{$this->identifier}' requires a 'field' argument"
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
        $fields = $this->fields;
        return [
            'bool' => [
                'must' => array_map(fn (FilterCriteria $filter) => [
                    'term' => [ [$fields[$filter->level - 1]] => $filter->value ]
                ], $filters)
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getAggregation(FacetCriteria $criteria): array
    {
        // TODO: Reference implementation is complex. 
        // Essentially needs to ask the query manager for applied parent filters
        // https://github.com/searchkit/searchkit/blob/9a603095a55c724c839ee35302a24318c4e9b1b3/packages/searchkit-sdk/src/facets/HierarchicalMenuFacet.ts#L33

        /*
            Roughly speaking:

            {   
                [$this->identifier]: {
                    filter: {
                        match_all: {}
                    },
                    aggs: {
                        'lvl_1': {
                            filter: {
                                match_all: {} 
                                    OR
                                bool: {
                                    must: {
                                        term: {
                                            [$fields[level-1]]: filter.value
                                        }
                                    }
                                }
                            },
                            aggs: {
                                aggs: {
                                    terms: {
                                        field: $fields[level-1]
                                    }
                                }
                            }
                        },
                        'lvl_2': {
                            ... same as above ... 
                        }
                    }
                }   
            }

        */
    }

    /**
     * Return a `HierarchicalValueSelectedFilter` GraphQL type from response data
     *
     * @return array GraphQL type `HierarchicalValueSelectedFilter`
     */
    public function toSKSelectedFilter(FilterCriteria $criteria): array
    {
        return [
            'type' => 'HierarchicalValueSelectedFilter',

            'id' => $this->identifier.'_'.$criteria->value,
            'identifier' => $this->identifier,
            'display' => $this->display,
            'label' => $this->label,
            'value' => $criteria->value,
            'level' => $criteria->level,
        ];
    }

    /**
     * Convert an aggregation bucket into a concrete type for `SKFacetSet`.
     *
     * @return array GraphQL type `HierarchicalMenuFacet`
     */
    public function toSKFacetSet(array $response): array
    {
        return [
            'type' => 'HierarchicalMenuFacet',

            'identifier' => $this->identifier,
            'label' => $this->label,
            'display' => $this->display,
            'entries' => $this->buildEntries($response, 1),
        ];
    }

    protected function buildEntries(array $response, int $level, string $parentId = '')
    {
        $buckets = data_get($response, 'lvl_' . $level . '.aggs.buckets', []);
        if (!$buckets) {
            return null;
        }

        // TODO: Needs applied filters from the query manager in order to filter out parents

        /** @var ???? */
        $appliedFilters = SomeWayToGetFiltersById($this->identifier) ?? [];
        $levelFilter = TODO; // find filter in appliedFilters s.t. filter.level == $level

        return array_map(function ($bucket) use ($level, $parentId, $levelFilter) {
            $label = $bucket['key'];
            $isSelected = $levelFilter && $levelFilter->value === $bucket->key;

            $id = "{$parentId}_{$this->identifier}_{$label}_{$level}";
            if ($isSelected) {
                $id .= '_selected';
            }

            return [
                'label' => $label,
                'count' => $bucket['doc_count'],
                'level' => $level,

                // NOTE: Reference impl does not implement isSelected in retval
                // https://github.com/searchkit/searchkit/blob/9a603095a55c724c839ee35302a24318c4e9b1b3/packages/searchkit-sdk/src/facets/HierarchicalMenuFacet.ts#L104
                'isSelected' => $isSelected,
                'entries' => $isSelected 
                    ? $this->buildEntries($level + 1, $id) 
                    : null,
            ];
        }, $buckets);
    }
}
