<?php namespace Searchlight\Events;

use Searchlight\SearchRequest;
use Searchlight\SearchResponse;

/**
 * Event containing a single request-response cycle with ElasticSearch
 */
class ExecuteSearch
{
    public SearchRequest $request;
    public SearchResponse $response;

    public function __construct(SearchRequest $request, SearchResponse $response)
    {
        $this->request = $request;
        $this->response = $response;
    }
}
