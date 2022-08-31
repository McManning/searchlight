<?php namespace Searchlight\Queries;

use Searchlight\Interfaces\IQuery;
use Searchlight\Exceptions\ConfigurationException;
use Searchlight\QueryCriteria;

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

    /**
     * {@inheritDoc}
     */
    public function getQuery(QueryCriteria $criteria): array
    {
        if (strlen($criteria->terms) < 1) {
            return [];
        }

        // Criteria may override default fields
        $fields = $criteria->fields ?? $this->fields;

        return [
            'bool' => [
                'should' => [
                    [ 'multi_match' => [
                        'query' => $criteria->terms,
                        'fields' => $fields,
                        'type' => 'best_fields',
                        'operator' => 'and',
                    ]],
                    [ 'multi_match' => [
                        'query' => $criteria->terms,
                        'fields' => $fields,
                        'type' => 'cross_fields',
                    ]],
                    [ 'multi_match' => [
                        'query' => $criteria->terms,
                        'fields' => $fields,
                        'type' => 'phrase',
                    ]],
                    [ 'multi_match' => [
                        'query' => $criteria->terms,
                        'fields' => $fields,
                        'type' => 'phrase_prefix',
                    ]],
                ],
            ]
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getHighlights(QueryCriteria $criteria): array
    {
        $fields = $criteria->fields ?? $this->fields;

        // Strip weights from fields
        return array_map(
            fn ($f) => preg_replace('/\^\d+/', '', $f),
            $fields
        );
    }
}
