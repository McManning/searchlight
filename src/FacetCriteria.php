<?php namespace McManning\Searchlight;

/**
 * Input criteria provided by an API request for a specific facet
 */
class FacetCriteria
{
    protected string $identifier;
    protected ?string $query;
    protected ?float $size;

    public function __construct(string $identifier, ?string $query = null, ?float $size = null)
    {
        $this->identifier = $identifier;
        $this->query = $query;
        $this->size = $size;
    }

    public function getQuery(): ?string
    {
        // TODO: searchkit post-processes the query string with a createRegexQuery() method:
        // https://github.com/searchkit/searchkit/blob/9a603095a55c724c839ee35302a24318c4e9b1b3/packages/searchkit-sdk/src/facets/utils/index.ts#L1
        // Applies to: https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-terms-aggregation.html#_filtering_values_with_regular_expressions_2
        return $this->query;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }
}

