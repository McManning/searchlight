<?php namespace Tests;

use GraphQL\Error\DebugFlag;
use GraphQL\Type\Schema;

use McManning\Searchlight\ServiceProvider as SearchlightServiceProvider;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Orchestra\Testbench\TestCase as BaseTestCase;

use Nuwave\Lighthouse\LighthouseServiceProvider;
use Nuwave\Lighthouse\CacheControl\CacheControlServiceProvider;

use Illuminate\Contracts\Config\Repository;
use Nuwave\Lighthouse\Testing\UsesTestSchema;

use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Testing\MocksResolvers;

/**
 * Base test case for setting up a Lighthouse instance configured for Searchlight.
 *
 * Most of this code is built off of Lighthouse's own base test case except
 * the Laravel portion is driven off of Orchestra's test bench instead.
 */
class TestCase extends BaseTestCase
{
    use MakesGraphQLRequests;
    use UsesTestSchema;
    use MocksResolvers;

    /**
     * A dummy query type definition that is added to tests by default.
     */
    public const PLACEHOLDER_QUERY = '
        type Query {
            foo: Int
        }
    ';

    protected function setUp(): void
    {
        parent::setUp();

        if (!isset($this->schema)) {
            $this->schema = self::PLACEHOLDER_QUERY;
        }

        $this->setUpTestSchema();
    }

    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            // Lighthouse providers
            LighthouseServiceProvider::class,
            CacheControlServiceProvider::class,

            // Searchlight providers
            SearchlightServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        /** @var Repository */
        $config = $app->make(Repository::class);

        // Configure lighthouse for tests
        $config->set('lighthouse.namespaces', [
            'models' => [
                'Tests\\Fixtures\\Models',
            ],
            'queries' => [
                'Tests\\Fixtures\\Queries',
            ],
            'mutations' => [
                'Tests\\Fixtures\\Mutations',
            ],
            'interfaces' => [
                'Tests\\Fixtures\\Interfaces',
            ],
            'scalars' => [
                'Tests\\Fixtures\\Scalars',
            ],
            'unions' => [
                'Tests\\Fixtures\\Unions',
            ],
            'directives' => [
                'Tests\\Fixtures\\Directives',
            ],
            'validators' => [
                'Tests\\Fixtures\\Validators',
            ],
        ]);


        $config->set('app.debug', true);
        $config->set(
            'lighthouse.debug',
            DebugFlag::INCLUDE_DEBUG_MESSAGE
            | DebugFlag::INCLUDE_TRACE
            // | Debug::RETHROW_INTERNAL_EXCEPTIONS
            | DebugFlag::RETHROW_UNSAFE_EXCEPTIONS
        );

        $config->set('lighthouse.cache.enable', false);
        // $config->set('lighthouse.guard', null);
    }

    /**
     * Build an executable schema from a SDL string, adding on a default Query type.
     */
    protected function buildSchemaWithPlaceholderQuery(string $schema = ''): Schema
    {
        return $this->buildSchema(
            $schema . self::PLACEHOLDER_QUERY
        );
    }

    /**
     * Build an executable schema from an SDL string.
     */
    protected function buildSchema(string $schema): Schema
    {
        $this->schema = $schema;

        $schemaBuilder = $this->app->make(SchemaBuilder::class);
        assert($schemaBuilder instanceof SchemaBuilder);

        return $schemaBuilder->schema();
    }
}
