<?php namespace Searchlight;

/**
 * Query settings provided by the API consumer.
 *
 * This parses out the GraphQL input type `SKQueryOptions`
 * for use in applying filter values for search results
 */
class QueryCriteria
{
    public string $terms;
    public ?array $fields;

    /**
     * @param string    $terms      Full text search terms
     * @param array     $options    GraphQL type `SKQueryOptions`
     */
    public function __construct(string $terms, array $options)
    {
        $this->terms = $terms;
        $this->fields = data_get($options, 'fields');
    }
}
