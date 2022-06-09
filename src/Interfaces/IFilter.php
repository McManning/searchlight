<?php namespace McManning\Searchlight\Interfaces;

use McManning\Searchlight\FilterCriteria;

interface IFilter
{
    public function getIdentifier(): string;
    public function getLabel(): string;

    /**
     * Create ElasticSearch filters from input criteria
     *
     * @param FilterCriteria[] $criteria
     */
    public function getFilters(array $criteria): array;

    /**
     * Return a GraphQL type from filter criteria
     *
     * @return array Concrete type for GraphQL interface `SKSelectedFilter`
     */
    public function toSKSelectedFilter(FilterCriteria $criteria): array;
}
