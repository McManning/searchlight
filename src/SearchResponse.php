<?php namespace Searchlight;

use Searchlight\Interfaces\IFacet;

class SearchResponse
{
    protected ConfigProvider $provider;
    protected SearchRequest $request;
    protected int $status;
    protected array $body;

    public function __construct(ConfigProvider $provider, SearchRequest $request, int $status, array $body)
    {
        $this->provider = $provider;
        $this->request = $request;
        $this->status = $status;
        $this->body = $body;

        $this->postProcessAggregations();
    }

    /**
     * Return the raw ElasticSearch query that generated this response
     */
    public function getRawQuery(): array
    {
        $query = $this->request->getLastElasticQuery();
        assert(is_array($query));
        return $query;
    }

    /**
     * Return the raw ElasticSearch response body
     */
    public function getRawResponse(): array
    {
        return $this->body;
    }

    public function getSortedBy(): ?string
    {
        return $this->request->getSortId();
    }

    /**
     * @return array GraphQL type `[SKHit]`
     */
    public function getHits(): array
    {
        // Convert an Elastic hit to a concrete of `SKHit`
        $items = array_map(
            fn (array $hit) => [
                'id' => $hit['_id'],
                'fields' => $hit['_source'],
                'highlights' => $this->getHighlights($hit),

                // TODO: How is scoring reported in searchkit? And what else is omitted?
                'score' => $hit['_score'],
                'index' => $hit['_index'],

                // Type used by the SKHit interface resolver.
                // TODO: Mapping between $hit['_type'] and GraphQL types
                '__typename' => $this->provider->get('hit_type'),
            ],
            $this->body['hits']['hits']
        );

        return $items;
    }

    /**
     * @return array GraphQL type `SKSummary`
     */
    public function getSummary(): array
    {
        return [
            'total' => $this->getTotalHits(),
            'query' => $this->request->getQuery()->terms,
            'appliedFilters' => $this->request->getAppliedFilters(),
            'disabledFilters' => $this->request->getDisabledFilters(),
            'sortOptions' => $this->getSortOptions(),
        ];
    }

    /** @return array GraphQL type `[SKSortOption]` */
    protected function getSortOptions(): array
    {
        $options = $this->provider->get('sort.options', []);

        return array_map(fn ($identifier) => [
            'id' => $identifier,
            'label' => data_get($options[$identifier], 'label', $identifier)
        ], array_keys($options));
    }

    public function getTotalHits(): int
    {
        return data_get($this->body, 'hits.total.value', 0);
    }

    /** @var Array<string, Array<IFacet, mixed>> Mapping between a facet and aggregation response */
    protected array $aggregations = [];

    /**
     * Extract highlight information from a hit when available
     *
     * @return array GraphQL type `[SKHighlight!]!`
     */
    protected function getHighlights(array $hit): array
    {
        $highlights = data_get($hit, 'highlight', []);
        return array_map(fn ($field) => [
            'field' => $field,
            'fragments' => $highlights[$field]
        ], array_keys($highlights));
    }

    protected function postProcessAggregations()
    {
        $facets = $this->provider->get('facets', []);

        $data = $this->getRawResponse();
        $buckets = data_get($data, 'aggregations', []);

        // Flatten different aggregation buckets into a single set of fields
        $flattened = [];
        foreach ($buckets as $key => &$data) {
            if (strpos($key, 'facet_bucket_') === 0) {
                $flattened = array_merge($flattened, $data);
            }
        }

        $this->aggregations = [];
        foreach ($facets as $facet) {
            $id = $facet->getIdentifier();

            if (isset($flattened[$id])) {
                $this->aggregations[$id] = [$facet, $flattened[$id]];
            }
        }
    }

    /**
     * @return array GraphQL type `[SKFacetSet]`
     */
    public function getFacets(): array
    {
        return array_map(
            fn (array $pair) => $pair[0]->toSKFacetSet($pair[1]),
            $this->aggregations
        );
    }

    /**
     * @return array GraphQL type `SKPageInfo`
     */
    public function getPageInfo(): array
    {
        $total = $this->getTotalHits();
        $from = $this->request->getFrom();
        $size = $this->request->getSize();
        $totalPages = 1;
        $pageNumber = 1;

        if ($size > 0) {
            $totalPages = max(0, intval($total / $size)) + 1;
            $pageNumber = intval($from / $size) + 1;
        }

        return [
            'total' => $total,
            'totalPages' => $totalPages,
            'pageNumber' => $pageNumber,
            'from' => $from,
            'size' => $size,
        ];
    }

    /**
     * Find a facet by identifier and return the GraphQL type data from the response
     *
     * @return array|null Inherited type of the GraphQL interface `SKFacetSet`
     */
    public function getFacet(string $identifier): ?array
    {
        if (isset($this->aggregations[$identifier])) {
            [$facet, $bucket] = $this->aggregations[$identifier];
            return $facet->toSKFacetSet($bucket);
        }

        // TODO: Error?
        return null;

        // $facets = $this->provider->get('facets', []);

        // $this->aggregations[$id] = [$facet, $buckets[$id]];

        // foreach ($facets as $facet) {
        //     if ($facet->getIdentifier() === $identifier)  {
        //         if (isset($this->aggregations[$identifier])) {
        //             return $this->
        //         }
        //         return $facet->toSKFacetSet(
        //             $this->getRawResponse()
        //         );
        //     }
        // }

        // return null;
    }
}
