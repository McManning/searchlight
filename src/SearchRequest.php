<?php namespace Searchlight;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Client;

use Searchlight\Interfaces\IFacet;
use Searchlight\Interfaces\IQuery;
use Searchlight\Interfaces\IFilter;
use Searchlight\Events\ExecuteSearch;
use Searchlight\Exceptions\ConfigurationException;
use Searchlight\Exceptions\GenericException;
use Searchlight\Exceptions\ValidationException;

/**
 * Request state for a new query against ElasticSearch
 *
 * Ref: https://github.com/searchkit/searchkit/blob/next/packages/searchkit-schema/src/resolvers/ResultsResolver.ts#L18
 */
class SearchRequest
{
    protected Dispatcher $dispatcher;
    protected ConfigProvider $provider;
    protected Client $client;

    protected bool $usesFacets = false;

    protected ?QueryCriteria $query = null;

    /** @var Array<string, IFilter> Combination of facets and filters that are usable */
    protected array $availableFilters = [];

    /** @var Array<string, FilterCriteria[]> Criteria grouped by valid IFilter */
    protected array $filterCriteria = [];

    /** @var FacetCriteria[] */
    protected array $facetCriteria = [];

    /** @var string[] */
    protected array $fields = [];

    /** @var mixed[] Raw Lucene base filters to apply to all queries */
    protected array $baseFilters = [];

    protected ?SearchResponse $response = null;

    protected ?array $lastElasticQuery = null;

    // Pagination for hits
    protected int $size = 0;
    protected int $from = 0;

    // Sorting
    protected ?string $sortId = null;

    public function __construct(ConfigProvider $provider)
    {
        $this->provider = $provider;

        $this->initializeAvailableFilters();

        $builder = ClientBuilder::create();
        $builder->setHosts([
            $provider->get('host'),
        ]);

        $username = $provider->get('http_basic_auth.username');
        $password = $provider->get('http_basic_auth.password');
        if ($username && $password) {
            $builder->setBasicAuthentication($username, $password);
        }

        $this->baseFilters = $provider->get('base_filters', []);
        $this->query = new QueryCriteria('', []);

        // TODO: https://github.com/jeskew/amazon-es-php
        $this->client = $builder->build();
    }

    public function useFacets(bool $enable)
    {
        $this->usesFacets = $enable;
    }

    public function setQuery(QueryCriteria $query)
    {
        $this->query = $query;
    }

    /**
     * @param FilterCriteria[] $criteria
     */
    public function setFilterCriteria(array $criteria)
    {
        foreach ($criteria as $entry) {
            $filter = $this->findFilter($entry->identifier);

            if ($filter) {
                $this->addFilterCriteria($filter, $entry);
            } else {
                throw new ValidationException(
                    "Unknown filter identifier \"{$entry->identifier}\""
                );
            }
        }
    }

    protected function addFilterCriteria(IFilter $filter, FilterCriteria $criteria)
    {
        $id = $filter->getIdentifier();

        if (!isset($this->filterCriteria[$id])) {
            $this->filterCriteria[$id] = [];
        }

        $this->filterCriteria[$id][] = $criteria;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getFrom(): int
    {
        return $this->from;
    }

    public function getSortId(): ?string
    {
        return $this->sortId;
    }

    public function getQuery(): QueryCriteria
    {
        return $this->query;
    }

    /**
     * Define fields for a multi-match query.
     *
     * Fields may be boosted using Elastic syntax.
     * Example: `['actors', 'writers', 'title^4', 'plot']`
     *
     * @param string[] $fields
     */
    public function setFields(array $fields)
    {
        $this->fields = $fields;
    }

    public function addFacetCriteria(FacetCriteria $criteria)
    {

        $limit = $this->provider->get('security.max_facet_size', -1);
        if ($limit >= 0 && $criteria->getSize() > $limit) {
            throw new ValidationException(
                'Requested facet size exceeds the maximum allowed value'
            );
        }

        $this->facetCriteria[] = $criteria;
    }

    public function setPage(?int $size, ?int $from)
    {
        $limit = $this->provider->get('security.max_page_size', -1);
        if ($limit >= 0 && $size > $limit) {
            throw new ValidationException(
                'Requested page size exceeds the maximum allowed value'
            );
        }

        $this->size = $size;
        $this->from = $from;
    }

    public function setSortBy(?string $sortId)
    {
        $this->sortId = $sortId;
        // TODO: Validate against known sorts.
    }

    public function search(): SearchResponse
    {
        if ($this->response) {
            return $this->response;
        }

        $query = $this->buildElasticQuery();
        $this->lastElasticQuery = $query;

        $this->response = $this->executeSearch($query);

        Event::dispatch(new ExecuteSearch($this, $this->response));

        return $this->response;
    }

    /**
     * Retrieve filter data that was last applied to the request
     *
     * @return array GraphQL type `[SKSelectedFilter!]!`
     */
    public function getAppliedFilters(): array
    {
        $active = $this->getActiveFilters();

        $applied = [];
        foreach ($active as $filter) {
            $id = $filter->getIdentifier();

            if (isset($this->filterCriteria[$id])) {
                $applied = array_merge(
                    $applied,
                    array_map(
                        fn ($c) => $filter->toSKSelectedFilter($c),
                        $this->filterCriteria[$id]
                    )
                );
            }
        }

        return $applied;
    }

    /**
     * Retrieve filter data that was disabled during the request
     */
    public function getDisabledFilters(): array
    {
        // TODO!
        return [];
    }

    protected function executeSearch(array $query): SearchResponse
    {
        try {
            $results = $this->client->search([
                'index' => $this->provider->get('index'),
                'body' => $query,
                'client' => [
                    'timeout' => 5,
                    'connect_timeout' => 5
                ]
            ]);
        }
        catch (\Throwable $e) {
            // Rethrow any service exceptions
            throw new GenericException(
                'Failed to execute search: ' . $e->getMessage(),
                null, null, [], null, $e
            );
        }

        return new SearchResponse(
            $this->provider,
            $this,
            200, // TODO: Response code from ES
            $results
        );
    }

    protected function findFilter(string $identifier): ?IFilter
    {
        $filters = array_merge(
            $this->provider->get('filters', []),
            $this->provider->get('facets', [])
        );

        foreach ($filters as $filter) {
            if ($filter instanceof IFilter && $filter->getIdentifier() === $identifier) {
                return $filter;
            }
        }

        return null;
    }

    protected function initializeAvailableFilters()
    {
        // Facets and filters are combined as one set
        $filters = array_merge(
            $this->provider->get('filters', []),
            $this->provider->get('facets', [])
        );

        foreach ($filters as $filter) {
            $id = $filter->getIdentifier();

            if (isset($this->availableFilters[$id])) {
                throw new ConfigurationException(
                    'Duplicate identifier: "' . $id .
                    '". Identifiers must be unique across both facets and filters'
                );
            }

            $this->availableFilters[$id] = $filter;
        }
    }

    public function getLastElasticQuery(): ?array
    {
        return $this->lastElasticQuery;
    }

    protected function hasFilters(): bool
    {
        return count($this->filterCriteria) > 0;
    }

    /**
     * @return Array<IFilter>
     */
    protected function getActiveFilters(): array
    {
        /** @var Array<IFilter> */
        $active = [];

        foreach ($this->filterCriteria as $identifier => $criteria) {
            if (isset($this->availableFilters[$identifier])) {
                $active[] = $this->availableFilters[$identifier];
            }
        }

        return $active;
    }

    /**
     * Transform a set of `IFilter` to ElasticSearch filters
     *
     * @var Array<IFilter> $facets
     */
    protected function transformFilters(array $filters): array
    {
        $transformed = [];
        foreach ($filters as $filter) {
            if (isset($this->filterCriteria[$filter->getIdentifier()])) {
                $transformed = array_merge($transformed, $filter->getFilters(
                    $this->filterCriteria[$filter->getIdentifier()]
                ));
            }
        }

        return $transformed;
    }

    /**
     * Generate ElasticSearch aggregation buckets based on
     * the provider's facets and the current filtering criteria.
     */
    protected function buildAggregations(): array
    {
        /** @var IFacet[] */
        $facets = $this->provider->get('facets', []);

        // If the request includes an explicit subset of facets:
        // filter down to only the defined subset
        if (!empty($this->facetCriteria)) {
            $identifiers = array_map(
                fn (FacetCriteria $criteria) => $criteria->getIdentifier(),
                $this->facetCriteria
            );

            $facets = array_filter(
                $facets,
                fn (IFacet $f) => in_array($f->getIdentifier(), $identifiers)
            );
        }

        // One bucket represents the combination of *all* facet filtering
        $buckets = [
            'facet_bucket_all' => [
                'aggs' => [],
                'filter' => [
                    'bool' => [
                        'must' => $this->transformFilters($this->getActiveFilters()),
                    ],
                ],
            ],
        ];

        // Generate an ElasticSearch aggregation from each active facet
        foreach ($facets as $facet) {
            $id = $facet->getIdentifier();
            $agg = $facet->getAggregation(
                $this->getFacetCriteria($facet->getIdentifier())
            );

            // If the facet can't be combined with other aggs into the same bucket:
            // add a new unique bucket just for this facet and exclude its filters
            if ($facet->excludesOwnFilters() && $this->hasFilters()) {
                $buckets['facet_bucket_' . $id] = [
                    'aggs' => [$id => $agg],
                    'filter' => [
                        'bool' => [
                            'must' => $this->transformFilters(array_filter(
                                $facets,
                                fn (IFilter $f) => $f->getIdentifier() !== $id
                            )),
                        ],
                    ],
                ];
            } else {
                // Otherwise we add it to the combined bucket
                if ($agg) {
                    $buckets['facet_bucket_all']['aggs'][$id] = $agg;
                }
            }
        }

        // If nothing was added to the combined facet,
        // omit entirely to optimize the query a bit.
        if (empty($buckets['facet_bucket_all']['aggs'])) {
            unset($buckets['facet_bucket_all']);
        }

        return ['aggs' => (object)$buckets];
    }

    protected function buildQuery(): array
    {
        /** @var IQuery */
        $queryBuilder = $this->provider->get('query');
        $queryFilter = $queryBuilder->getQuery($this->query);

        // Start with raw Lucene filters if we got 'em
        $baseFilters = $this->baseFilters;

        // Combine base filters + query filter
        $query = [];
        if (isset($queryFilter['bool'])) {
            if (isset($queryFilter['bool']['filter'])) {
                // Combine base filters and the query filter into a single clause
                $query['query']['bool']['filter'] = array_merge(
                    $queryFilter['bool']['filter'],
                    $baseFilters,
                );
            } else {
                // Append a filter clause to the existing query
                $query['query']['bool'] = array_merge(
                    $queryFilter['bool'],
                    ['filter' => $baseFilters],
                );
            }
        } else {
            // Query is not a bool filter so append one onto the query clause
            $query['query'] = array_merge(
                $queryFilter,
                [ 'bool' => [
                    'filter' => $baseFilters,
                ]]
            );
        }

        return $query;
    }

    protected function buildElasticQuery(): array
    {
        // TODO:
        // There's also a bunch of logic for activating facets when rules are satisfied as a processing step
        // Ref: https://github.com/searchkit/searchkit/blob/9a603095a55c724c839ee35302a24318c4e9b1b3/packages/searchkit-sdk/src/facets/VisibilityRules/index.ts#L7

        $query = $this->buildQuery();
        $sort = $this->buildSort();
        $highlighting = $this->buildHighlighting();

        $aggs = [];
        if ($this->usesFacets) {
            $aggs = $this->buildAggregations();
        }

        // Apply all FilterCriteria as a post_filter. This is to ensure
        // we filter results *after* aggregations are computed.
        $postFilter = [];
        $activeFilters = $this->getActiveFilters();
        if ($activeFilters) {
            $postFilter = [
                'post_filter' => [
                    'bool' => [
                        'must' => $this->transformFilters($activeFilters)
                    ]
                ]
            ];
        }

        $source = [
            '_source' => [
                'includes' => $this->provider->get('hits.fields', []),
            ],
        ];

        $paging = [
            'size' => $this->size,
            'from' => $this->from,
        ];

        return array_merge(
            $paging,
            $sort,
            $source,
            $highlighting,
            $aggs,
            $query,
            $postFilter,
        );
    }

    protected function buildSort(): array
    {
        $sort = [];
        if ($this->sortId !== null) {
            // TODO: Needs to fallback to sort.default if not supplied
            // (and if default is omitted, then don't do anything)

            $sortOptions = $this->provider->get('sort.options', []);
            $field = data_get($sortOptions, [$this->sortId, 'field'], '_score');
            $rules = data_get($sortOptions, [$this->sortId, 'sort'], 'desc');

            $sort = [
                'sort' => [[$field => $rules]]
            ];
        }

        return $sort;
    }

    protected function buildHighlighting(): array
    {
        /** @var IQuery */
        $queryBuilder = $this->provider->get('query');

        $fields = $queryBuilder->getHighlights($this->query);

        $highlight = [];

        // TODO: Does this transform make more sense to implement
        // in the request or in the IQuery::getHighlights?
        // My immediate assumption is that Imost IQuery classes will
        // handle highlights the same way and configuration of the
        // highlighting is a global setting.

        if ($fields) {
            $map = [];
            foreach ($fields as $field) {
                $map[$field] = (object)[];
            };

            $highlight = [
                'highlight' => [
                    'fields' => $map
                ]
            ];
        }

        return $highlight;
    }

    protected function getFacetCriteria(string $identifier): FacetCriteria
    {
        $filters = $this->getFilterCriteria($identifier);

        foreach ($this->facetCriteria as $criteria) {
            if ($criteria->getIdentifier() === $identifier) {
                $criteria->setFilterCriteria($filters);
                return $criteria;
            }
        }

        $criteria = new FacetCriteria($identifier);
        $criteria->setFilterCriteria($filters);
        return $criteria;
    }

    /**
     * Get all applied FilterCriteria for an identifier.
     *
     * @return FilterCriteria[] If none are applied, this will return an empty array.
     */
    protected function getFilterCriteria(string $identifier): array
    {
        if (isset($this->filterCriteria[$identifier])) {
            return $this->filterCriteria[$identifier];
        }

        return [];
    }
}
