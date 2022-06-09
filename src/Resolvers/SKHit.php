<?php namespace McManning\Searchlight\Resolvers;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Interface resolver for mapping `SKHit` types to concrete ElasticSearch document results
 */
class SKHit
{
    /** @var \Nuwave\Lighthouse\Schema\TypeRegistry */
    protected $typeRegistry;

    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * Decide which GraphQL type a resolved value has.
     *
     * @param  mixed  $rootValue The value that was resolved by the field. Usually an Eloquent model.
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @return \GraphQL\Type\Definition\Type
     */
    public function resolveType($rootValue, GraphQLContext $context, ResolveInfo $resolveInfo): Type
    {
        // __typename is prepopulated to a mapped GQL type when
        // constructing SKHit results in SearchResponse.
        return $this->typeRegistry->get($rootValue['__typename']);
    }
}
