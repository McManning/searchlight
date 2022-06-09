<?php namespace McManning\Searchlight\Interfaces;

use McManning\Searchlight\FacetCriteria;
use McManning\Searchlight\FilterCriteria;

interface IFacet
{
    public function getIdentifier(): string;
    public function getLabel(): string;

    /**
     * Create ElasticSearch filters from input criteria
     *
     * @param FilterCriteria[] $criteria
     */
    public function getFilters(array $criteria): array;

    public function getAggregation(FacetCriteria $criteria): array;

    /**
     * Convert an aggregation bucket into a concrete type for `SKFacetSet`.
     *
     * @return array Concrete type that implements GraphQL interface `SKFacetSet`
     */
    public function toSKFacetSet(array $response): array;

    /**
     * @return array Concrete type that implements GraphQL interface `SKSelectedFilter`
     */
    public function toSKSelectedFilter(FilterCriteria $criteria): array;
}
