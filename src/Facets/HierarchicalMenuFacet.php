<?php namespace Searchlight\Facets;

use Searchlight\Interfaces\IFacet;
use Searchlight\Interfaces\IFilter;
use Searchlight\FacetCriteria;
use Searchlight\FilterCriteria;
use Searchlight\Exceptions\ConfigurationException;

class HierarchicalMenuFacet implements IFacet, IFilter
{
    const DEFAULT_SIZE = 5;
    const DEFAULT_DISPLAY = 'HierarchicalMenu';

    const SORT_BY_VALUE = 0;
    const SORT_BY_COUNT = 1;

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

        $this->sort = data_get($args, 'order', self::SORT_BY_VALUE);
        $this->size = data_get($args, 'size', self::DEFAULT_SIZE);

        if (!$this->identifier) {
            throw new ConfigurationException(
                "HierarchicalMenuFacet requires an 'identifier' argument"
            );
        }

        if (!$this->fields) {
            throw new ConfigurationException(
                "HierarchicalMenuFacet '{$this->identifier}' requires a 'fields' argument"
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
    public function excludesOwnFilters(): bool
    {
        return true;
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
                    'term' => [ $fields[$filter->level - 1] => $filter->value ]
                ], $filters)
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getAggregation(FacetCriteria $criteria): array
    {
        $level = 1;
        $aggs = [];

        // Each "level" of the hierarchy is represented by a different field
        // on the indexed document. We iterate through this mapping of fields
        // to construct levels of aggregations such that each set of aggregate
        // values depends on the current filtering criteria on any parent level(s).
        foreach ($this->fields as $field) {
            $parentFilters = array_filter(
                $criteria->getFilterCriteria(),
                fn (FilterCriteria $f) => $f->level && $f->level < $level
            );

            $directParentFilter = current(array_filter(
                $parentFilters,
                fn (FilterCriteria $f) => $f->level && $f->level == $level - 1
            ));

            // If there are filters specified at a higher level than our
            // current level: ensure that this level's aggregation
            // takes in account all parent terms as well.
            if ($parentFilters) {
                $filter = [
                    'bool' => [
                        'must' => array_map(
                            fn (FilterCriteria $f) => [
                                'term' => [
                                    $this->fields[$f->level - 1] => $f->value
                                ]
                            ],
                            $parentFilters
                        )
                    ]
                ];
            }
            else {
                // Otherwise, don't apply any additional filtering on this level
                $filter = [
                    'match_all' => (object)[]
                ];
            }

            $terms = [
                'field' => $field,
                'size' => $criteria->getSize() ?? $this->size,
                'order' => $this->getAggregationOrder(),
            ];

            $query = $criteria->getQueryAsRegex();
            if ($query) {
                $terms['include'] = $query;
            }

            $aggs['lvl_' . $level] = [
                'filter' => $filter,
                'aggs' => [
                    'aggs' => [
                        'terms' => $terms,
                        'meta' => [
                            'parent' => $directParentFilter
                                    ? $directParentFilter->value
                                    : null
                        ],
                    ],
                ],
            ];

            $level++;
        }

        return [
            'filter' => [
                'match_all' => (object)[],
            ],
            'aggs' => $aggs
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

    protected function buildEntries(array $response, int $level, ?string $parentKey = null)
    {
        $buckets = data_get($response, 'lvl_' . $level . '.aggs.buckets', []);
        $parent = data_get($response, 'lvl_' . $level . '.aggs.meta.parent');

        if (!$buckets || $parentKey !== $parent) {
            return null;
        }

        // TODO: Needs applied filters from the query manager in order to filter out parents

        // TODO: Reference impl does not implement isSelected in retval
        // https://github.com/searchkit/searchkit/blob/9a603095a55c724c839ee35302a24318c4e9b1b3/packages/searchkit-sdk/src/facets/HierarchicalMenuFacet.ts#L104
        // However it *does* only fill entries if this facet level is selected.

        // I guess the question is: Do I care if they're selected or not?
        // he only recurses because isSelected is defined, so it increases in level.
        // but I *could* just recurse based on whether we find a lvl_N in the results.

        // isSelected *does* change the ID which would invalidate GraphQL caching.
        // But I could simply check if there's a lvl_(N+1).
        // I GUESS the other issue is multiple values at each level. But that
        // might be a future hierarchical refinement component problem.


        // ACTUALLY - he doesn't even pass in ID anywhere.
        // he aggregates it via parent IDs but it's never provided by the API.
        // See: https://github.com/searchkit/searchkit/blob/next/packages/searchkit-sdk/src/facets/HierarchicalMenuFacet.ts#L100-L103
        // re: ID not being used (and isSelected not being set for the type).

        // needs to attach as children ONLY TO THE SELECTED PARENT.
        // (which is assumed to be one).
        // Hence the need for the list of selected filters.
        // can I just key off the name in the entries list instead?

        // level2 and on are always filtering on a bool.must for the parent.
        // So there's no confusion about whether it's a one vs many situation.
        // Only one level of refinement is possible. Therefore...
        // we only really need to maintain the parent name as an identifier
        // at each level. But identifier != value. It's high risk to use value
        // (unicode and such) and probably won't work in ES.
        // Only one parent at each level is the "selected" one.
        // and it's going to be the bucket key...

        // Annoying.

        $entries = [];
        foreach ($buckets as $bucket) {
            $key = $bucket['key'];
            $nested = $this->buildEntries($response, $level + 1, $key);

            $entries[] = [
                'label' => $key,
                'count' => $bucket['doc_count'],
                'level' => $level,

                // TODO: Reference impl does not implement isSelected in retval
                // https://github.com/searchkit/searchkit/blob/9a603095a55c724c839ee35302a24318c4e9b1b3/packages/searchkit-sdk/src/facets/HierarchicalMenuFacet.ts#L104
                // However it *does* only fill entries if this facet level is selected.
                // 'isSelected' => $isSelected,
                'entries' => $nested
            ];
        }

        return $entries;
    }
}
