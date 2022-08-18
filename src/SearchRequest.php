<?php namespace McManning\Searchlight;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Client;

use McManning\Searchlight\Interfaces\IFacet;
use McManning\Searchlight\Interfaces\IQuery;
use McManning\Searchlight\Interfaces\IFilter;
use McManning\Searchlight\Events\ExecuteSearch;
use McManning\Searchlight\Exceptions\GenericException;

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

    protected string $query = '';

    /** @var Array<string, FilterCriteria[]> Criteria grouped by identifier */
    protected array $filterCriteria = [];

    /** @var FacetCriteria[] */
    protected array $facetCriteria = [];

    /** @var string[] */
    protected array $fields = [];

    /** @var mixed[] Raw Lucene base filters to apply to all queries */
    protected array $baseFilters = [];

    /** @var mixed[] */
    protected array $appliedFilters;

    /** @var mixed[] */
    protected array $disabledFilters;

    protected ?SearchResponse $response = null;

    protected ?array $lastElasticQuery = null;

    // Pagination for hits
    protected int $size = 0;
    protected int $from = 0;

    // Sorting
    protected ?string $sortId = null;

    public function __construct(ConfigProvider $provider)
    {
        // $this->dispatcher = $dispatcher;
        $this->provider = $provider;

        $builder = ClientBuilder::create();
        $builder->setHosts([
            $provider->get('host'),
        ]);
        
        $username = $provider->get('http_basic_auth.username');
        $password = $provider->get('http_basic_auth.password');
        if ($username && $password) {
            $builder->setBasicAuthentication($username, $password);
        }

        // TODO: https://github.com/jeskew/amazon-es-php

        $this->client = $builder->build();
    }

    public function useFacets(bool $enable)
    {
        $this->usesFacets = $enable;
    }

    /**
     * @param array[] Base Lucene filters to apply to all queries
     */
    public function setBaseFilters(array $baseFilters)
    {
        $this->baseFilters = $baseFilters;
    }

    public function setQuery(string $query)
    {
        $this->query = $query;
    }

    /**
     * @param FilterCriteria[] $filters
     */
    public function setFilterCriteria(array $filters)
    {
        foreach ($filters as $filter) {
            if (!isset($this->filterCriteria[$filter->identifier])) {
                $this->filterCriteria[$filter->identifier] = [];
            }

            $this->filterCriteria[$filter->identifier][] = $filter;
        }
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

    public function getQuery(): string
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

    public function setHits(?int $size, ?int $from, ?string $sortId = null)
    {
        $this->size = $size;
        $this->from = $from;
        $this->sortId = $sortId;
    }

    public function addFacetCriteria(FacetCriteria $criteria)
    {
        $this->facetCriteria[] = $criteria;
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
     */
    public function getAppliedFilters(): array
    {
        return $this->appliedFilters;
    }

    /**
     * Retrieve filter data that was disabled during the request
     */
    public function getDisabledFilters(): array
    {
        return $this->disabledFilters;
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
        catch (\Exception $e) {
            dd($e, $this->client);
            // TODO: Encapsulate error to ES error classes and propagate safely.
        }

        return new SearchResponse(
            $this->provider,
            $this,
            200, // TODO: Response code from ES
            $results
        );
    }

    protected function findFacetTemplate(string $identifier): ?IFacet
    {
        $facets = $this->provider->get('facets', []);

        foreach ($facets as $facet) {
            if ($facet->getIdentifier() === $identifier) {
                return $facet;
            }
        }

        return null;
    }

    protected function findFilterTemplate(string $identifier): ?IFilter
    {
        $filters = $this->provider->get('filters', []);

        foreach ($filters as $filter) {
            if ($filter->getIdentifier() === $identifier) {
                return $filter;
            }
        }

        return null;
    }

    public function getLastElasticQuery(): ?array
    {
        return $this->lastElasticQuery;
    }

    protected function hasFilters(): bool 
    {
        return count($this->filterCriteria) > 0;
    }

    protected function buildElasticQuery(): array
    {
        // Ref: https://github.com/searchkit/searchkit/blob/77b5fc9664a27b2ff66e509c4486c29c675d30d9/packages/searchkit-sdk/src/core/RequestBodyBuilder.ts

        $this->appliedFilters = [];
        $this->disabledFilters = [];

        /** @var IQuery */
        $queryBuilder = $this->provider->get('query');
        $queryFilter = $queryBuilder->getFilter($this->query);

        // Start with base Lucene filters, if provided
        $baseFilters = $this->baseFilters;

        $facetFilters = [];

        // If the API consumer supplied filter criteria to the search then
        // map each one to either a filter or facet as an applied filter
        foreach ($this->filterCriteria as $identifier => $criteria) {
            $filter = $this->findFilterTemplate($identifier);

            if ($filter) {
                $baseFilters = array_merge($baseFilters, $filter->getFilters($criteria));

                foreach ($criteria as $c) {
                    $this->appliedFilters[] = $filter->toSKSelectedFilter($c);
                }
            }
            else {
                $facet = $this->findFacetTemplate($identifier);
                if ($facet) {
                    $facetFilters = array_merge($facetFilters, $facet->getFilters($criteria));
                    
                    foreach ($criteria as $c) {
                        $this->appliedFilters[] = $facet->toSKSelectedFilter($c);
                    }
                }
                else {
                    throw new GenericException('TODO: Better... code here... this branching is ass');
                }
            }
        }

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

        $aggFacets = [];

        // There's also a bunch of logic for activating facets when rules are satisfied as a processing step
        // Ref: https://github.com/searchkit/searchkit/blob/9a603095a55c724c839ee35302a24318c4e9b1b3/packages/searchkit-sdk/src/facets/VisibilityRules/index.ts#L7

        // If there were supplied facet criteria to the search,
        // then the facets applied will only match the input criteria.
        if ($this->facetCriteria) {
            foreach ($this->facetCriteria as $criteria) {
                $id = $criteria->getIdentifier();

                $facet = $this->findFacetTemplate($id);
                if (!$facet) {
                    throw new GenericException('Unsupported facet: ' . $id);
                }

                $aggFacets[$id] = $facet->getAggregation($criteria);
            }
        }
        else if ($this->usesFacets) {
            // There was no input criteria but we want to include all faceting in the results.
            // Add all configured facets as aggregations.
            $facets = $this->provider->get('facets', []);
            foreach ($facets as $facet) {
                $aggFacets[$facet->getIdentifier()] = $facet->getAggregation(
                    new FacetCriteria($facet->getIdentifier())
                );
            }
        }

        $aggs = [];
        $postFilter = [];

        if ($this->usesFacets) {
            $aggs = [
                'aggs' => [
                    'facet_bucket_all' => [
                        'aggs' => (object)$aggFacets,

                        'filter' => [
                            'bool' => [
                                'must' => $facetFilters
                            ]
                        ],
                    ]
                    // TODO: There should also be separate facet buckets
                    // for disjoint facets. i.e. Category should show all
                    // possible value matches (combined with the post_filter)
                    // but it, itself, should not be filtered. 

                    // This is driven by the "excludeOwnFilters" feature
                    // (refinement select uses it if multiple_select is true)
                    // Ref: https://github.com/searchkit/searchkit/blob/9a603095a55c724c839ee35302a24318c4e9b1b3/packages/searchkit-sdk/src/core/FacetsFns.ts#L25
                ]
            ];
        }

        if ($facetFilters) {
            $postFilter = [
                'post_filter' => [
                    'bool' => [
                        'must' => $facetFilters
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

        $highlight = [];
        if (true) { // TODO: Condition (probably just whether or not the array config is empty)
                    // TODO: searchkit does a direct copy of "advanced" config for highlights right into ES.
                    // Ref: https://github.com/searchkit/searchkit/blob/9a603095a55c724c839ee35302a24318c4e9b1b3/packages/searchkit-sdk/src/core/RequestBodyBuilder.ts#L78
            $highlight = [
                'highlight' => [
                    'fields' => [
                        'name' => (object)[],
                        'description' => (object)[],
                        'category' => (object)[],
                        'kind' => (object)[],
                    ]
                ]
            ];
        }

        $sort = [];
        if ($this->sortId !== null) {
            $sortOptions = $this->provider->get('sort.options', []);
            $field = data_get($sortOptions, [$this->sortId, 'field'], '_score');
            $rules = data_get($sortOptions, [$this->sortId, 'sort'], 'desc');

            $sort = [
                'sort' => [[$field => $rules]]
            ];
        }

        return array_merge(
            $paging,
            $sort,
            $source,
            $highlight,
            $aggs,
            $query,
            $postFilter,
        );
    }
}
