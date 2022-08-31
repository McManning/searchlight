<?php namespace Searchlight\Interfaces;

use Searchlight\QueryCriteria;

interface IQuery
{
    /**
     * Create an ElasticSearch query from input criteria
     *
     * @param QueryCriteria $criteria
     */
    public function getQuery(QueryCriteria $criteria): array;

    /**
     * Create ElasticSearch highlights from input criteria
     */
    public function getHighlights(QueryCriteria $criteria): array;
}
