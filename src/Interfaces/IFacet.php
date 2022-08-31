<?php namespace Searchlight\Interfaces;

use Searchlight\FacetCriteria;

interface IFacet
{
    public function getIdentifier(): string;
    public function getLabel(): string;

    /**
     * Create ElasticSearch aggregations from input criteria
     *
     * @param FacetCriteria $criteria
     */
    public function getAggregation(FacetCriteria $criteria): array;

    /**
     * Convert an aggregation bucket into a concrete type for `SKFacetSet`.
     *
     * @return array Concrete type that implements GraphQL interface `SKFacetSet`
     */
    public function toSKFacetSet(array $response): array;

    /**
     * Should this facet create a separate aggregations bucket that applies
     * all selected filters *except* those associated with this Facet instance.
     *
     * This should return `true` for facets that support multiple disjunctive values
     */
    public function excludesOwnFilters(): bool;
}
