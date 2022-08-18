<?php namespace McManning\Searchlight;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\RootType;

class ASTManipulator
{
    const DEFAULT_RESULT_TYPE = 'ResultSet';

    public function handle(ManipulateAST $manipulateAST): void
    {
        $documentAST = $manipulateAST->documentAST;

        $this->addResultTypes($documentAST);
        $this->addRootFields($documentAST);
    }

    protected function addResultTypes(DocumentAST &$documentAST): void
    {
        $resultTypes = array_map(
            fn ($provider) => data_get($provider, 'result_type', self::DEFAULT_RESULT_TYPE),
            config('searchlight.providers', [])
        );

        foreach ($resultTypes as $resultType) {
            $documentAST->setTypeDefinition(
                Parser::objectTypeDefinition(<<<GRAPHQL
type {$resultType} {
    summary: SKSummary
    hits(page: SKPageInput, sortBy: String): SKHitResults
    facets: [SKFacetSet]
    facet(identifier: String!, query: String, size: Float): SKFacetSet
}
GRAPHQL
                )
            );
        }
    }

    protected function addRootFields(DocumentAST &$documentAST): void
    {
        if (!isset($documentAST->types[RootType::QUERY])) {
            $documentAST->types[RootType::QUERY] = Parser::objectTypeDefinition('type Query');
        }

        /** @var ObjectTypeDefinitionNode */
        $queryType = $documentAST->types[RootType::QUERY];

        $providers = array_filter(
            config('searchlight.providers', []),
            fn (&$p) => data_get($p, 'query_type') !== null
        );

        foreach ($providers as $name => &$provider) {
            $type = data_get($provider, 'query_type');
            $resultType = data_get($provider, 'result_type');
            $add = data_get($provider, 'add_to_query_type', true);

            if (!$add) {
                continue;
            }

            $queryType->fields[] = Parser::fieldDefinition(<<<GRAPHQL
$type(
    query: String,
    queryOptions: SKQueryOptions,
    filters: [SKFiltersSet],
    # page: SKPageInput - OMIT, page is defined (and documented) at the hits level
): $resultType @searchlight(provider: "$name")
GRAPHQL
            );
        }
    }
}
