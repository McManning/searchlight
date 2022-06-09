<?php namespace McManning\Searchlight\Queries;

use McManning\Searchlight\Interfaces\IQuery;
use McManning\Searchlight\Exceptions\ConfigurationException;

/**
 * Query builder for executing a set of multi_match queries
 */
class MultiMatchQuery implements IQuery
{
    /** @var string[] */
    protected array $fields;

    public function __construct(array $args)
    {
        $this->fields = data_get($args, 'fields');

        if ($this->fields === null) {
            throw new ConfigurationException(
                "MultiMatchQuery requires a 'fields' argument"
            );
        }
    }

    public function getFilter(string $query): array
    {
        if (strlen($query) < 1) {
            return [];
        }

        return [
            'bool' => [
                'should' => [
                    [ 'multi_match' => [
                        'query' => $query,
                        'fields' => $this->fields,
                        'type' => 'best_fields',
                        'operator' => 'and',
                    ]],
                    [ 'multi_match' => [
                        'query' => $query,
                        'fields' => $this->fields,
                        'type' => 'cross_fields',
                    ]],
                    [ 'multi_match' => [
                        'query' => $query,
                        'fields' => $this->fields,
                        'type' => 'phrase',
                    ]],
                    [ 'multi_match' => [
                        'query' => $query,
                        'fields' => $this->fields,
                        'type' => 'phrase_prefix',
                    ]],
                ],
            ]
        ];
    }
}
