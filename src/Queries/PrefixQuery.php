<?php namespace McManning\Searchlight\Queries;

use McManning\Searchlight\Interfaces\IQuery;
use McManning\Searchlight\Exceptions\ConfigurationException;

/**
 * Query builder for executing a multi_match.bool_prefix query
 */
class PrefixQuery implements IQuery
{
    /** @var string[] */
    protected array $fields;

    public function __construct(array $args)
    {
        $this->fields = data_get($args, 'fields');

        if ($this->fields === null) {
            throw new ConfigurationException(
                "PrefixQuery requires a 'fields' argument"
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
                'must' => [
                    [ 'multi_match' => [
                        'query' => $query,
                        'fields' => $this->fields,
                        'type' => 'bool_prefix',
                    ]],
                ],
            ]
        ];
    }
}
