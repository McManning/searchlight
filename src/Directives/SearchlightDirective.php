<?php namespace Searchlight\Directives;

use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

use Searchlight\Exceptions\ConfigurationException;
use Searchlight\ConfigProvider;
use Searchlight\SearchRequest;
use Searchlight\FilterCriteria;
use Searchlight\FacetCriteria;
use Searchlight\QueryCriteria;

class SearchlightDirective extends BaseDirective implements FieldResolver
{
    /** Provider configuration to use for requests */
    protected ConfigProvider $provider;

    protected SearchRequest $request;

    public static function definition(): string
    {
        return <<<GQL
"""
Use a Searchlight provider to resolve search results
"""
directive @searchlight(
    """
    Specify the provider to search against.
    If omitted, the 'default' provider will be selected.
    """
    provider: String
) on FIELD_DEFINITION
GQL;
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): array {
            $name = $this->directiveArgValue('provider', 'default');
            $this->provider = $this->findProvider($name);

            $resultSet = $this->search($args);

            return $resultSet;
        });

        return $fieldValue;
    }

    protected function findProvider(string $name): ConfigProvider
    {
        foreach (config('searchlight.providers', []) as $key => $config) {
            if ($key === $name) {
                return new ConfigProvider($name, $config);
            }
        }

        throw new ConfigurationException('No provider named: ' . $name);
    }

    protected function search(array $args)
    {
        /** @var string */
        $terms = data_get($args, 'query', '');

        /** @var array */
        $options = data_get($args, 'queryOptions', []);

        /** @var FilterCriteria[] filter inputs from `[SKFiltersSet]` input type */
        $filters = array_map(
            fn ($filter) => new FilterCriteria($filter),
            data_get($args, 'filters', [])
        );

        // Construct a request to Elastic
        $this->request = new SearchRequest($this->provider);

        $this->request->setFilterCriteria($filters);
        $this->request->setQuery(new QueryCriteria($terms, $options));

        return $this->resolveResultSet();
    }

    protected function resolveResultSet(): array
    {
        return [
            // SKSummary
            'summary' => new Deferred(function ()  {
                $response = $this->request->search();
                return $response->getSummary();
            }),

            // SKHitResults
            'hits' => function ($root, array $args): Deferred {
                $from = data_get($args, 'page.from', 0);
                $size = data_get($args, 'page.size', $this->provider->get('paging.size', 10));
                $sortBy = data_get($args, 'sortBy', $this->provider->get('sort.default'));

                $this->request->setPage($size, $from);
                $this->request->setSortBy($sortBy);

                return new Deferred(function ()  {
                    $results = $this->request->search();

                    return [
                        'items' => $results->getHits(),
                        'page' => $results->getPageInfo(),
                        'sortedBy' => $results->getSortedBy(),
                    ];
                });
            },

            // [SKFacetSet]
            'facets' => function (): Deferred {
                $this->request->useFacets(true);

                return new Deferred(function () {
                    $response = $this->request->search();
                    return $response->getFacets();
                });
            },

            // SKFacetSet
            'facet' => function ($root, array $args): Deferred {
                $identifier = data_get($args, 'identifier');
                $query = data_get($args, 'query', '');
                $size = data_get($args, 'size', $this->provider->get('faceting.size'));

                $this->request->useFacets(true);
                $this->request->addFacetCriteria(new FacetCriteria($identifier, $query, $size));

                return new Deferred(function () use ($identifier) {
                    $response = $this->request->search();
                    return $response->getFacet($identifier);
                });
            }
        ];
    }
}
