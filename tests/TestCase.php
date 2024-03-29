<?php namespace Tests;

use GraphQL\Error\DebugFlag;
use GraphQL\Type\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;

use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Contracts\Config\Repository;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use Nuwave\Lighthouse\CacheControl\CacheControlServiceProvider;
use Nuwave\Lighthouse\Testing\UsesTestSchema;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Testing\MocksResolvers;
use Spatie\Snapshots\MatchesSnapshots;

use Searchlight\SearchlightServiceProvider;
use Tests\Fixtures\GraphQLDriver;

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
    use MatchesSnapshots;

    /**
     * A dummy query type definition that is added to tests by default.
     */
    public const PLACEHOLDER_QUERY = '
        type ResultHit implements SKHit {
            id: ID!
            fields: HitFields!
            highlights: [SKHighlight!]
        }

        type HitFields {
            id: ID!
            type: String
            title: String
            year: Int
            rated: String
            released: String
            genres: [String]
            directors: [String]
            writers: [String]
            actors: [String]
            countries: [String]
            plot: String
            poster: String
            metascore: Float
            imdbrating: Float
            primary_genre1: String
            primary_genre2: [String]
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
        // $app->useEnvironmentPath(__DIR__.'/..');
        // $app->bootstrapWith([LoadEnvironmentVariables::class]);

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
            // | DebugFlag::RETHROW_UNSAFE_EXCEPTIONS
        );

        $config->set('lighthouse.cache.enable', false);
        // $config->set('lighthouse.guard', null);

        $config->set('searchlight', require(
            __DIR__.'/Fixtures/searchlight.imdb-elasticsearch.php'
        ));
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

    protected function formatStackEntry(array $f): string
    {
        return (isset($f['call']) ? $f['call'] : $f['function'])
            . (isset($f['file']) ? (' at ' . basename($f['file']) . ':' . $f['line']) : '');
    }

    /**
     * A really hacky method to clean up stack traces to something useful in PHPUnit
     */
    protected function prettyPrintGraphQLErrors(array $response): string
    {
        $errors = [];
        try {
            foreach ($response['errors'] as $err) {

                if (isset($err['debugMessage'])) {
                    $message = "\t" . $err['debugMessage'] . ' at ' . implode('.', $err['path']);
                } else {
                    $message = "\t" . $err['message'];
                }

                if (isset($err['trace'])) {
                    $stackCount = count($err['trace']);

                    $message .= array_reduce(
                        array_slice($err['trace'], 0, 5),
                        fn ($agg, $f) => $agg .= "\n\t\t" . $this->formatStackEntry($f),
                        "\n\t\tTrace: "
                    );

                    if ($stackCount > 5) {
                        $message .= "\n\t\t... omitting " . ($stackCount - 5) . ' additional calls ...';
                    }
                }

                $errors[] = $message;
            }
        } catch (\Throwable $e) {
            $errors[] = "Ironic error parsing GraphQL error: "
                . $e->getMessage() . " from response:\n"
                . json_encode($response, JSON_PRETTY_PRINT);
        }

        return implode("\n\n", $errors);
    }

    protected function assertNotGraphQLError(?string $assertionMessage = null)
    {
        $actual = $this->response->json();

        if (isset($actual['errors'])) {
            $this->fail(
                ($assertionMessage ?? "Failed asserting that the GraphQL response does not contain errors") .
                ":\n" . $this->prettyPrintGraphQLErrors($actual)
            );
        }
    }

    /**
     * Ensure a GraphQL response contains an error starting with the given prefix.
     *
     * In the case of multiple errors returned, this only tests against the first
     * error as that is typically the one that is presented to API consumers.
     *
     * @param string $prefix            Expected start of the error message string
     * @param string $assertionMessage  Custom message to show on assertion failure.
     *                                  If omitted, a default assertion message will be used.
     */
    protected function assertGraphQLError(string $prefix, string $assertionMessage = '')
    {
        $actual = $this->response->getContent();
        $response = json_decode($actual, true);

        $this->assertArrayHasKey(
            'errors',
            $response,
            "Expected response with errors but got [{$actual}]"
        );

        $this->assertNotEmpty(
            $response['errors'],
            "Expected response with at least one error but got [{$actual}]"
        );

        $error = $response['errors'][0]['message'];

        $this->assertStringStartsWith(
            $prefix,
            $error,
            $assertionMessage ?? "Failed to find the text [{$prefix}] within the first error [{$error}]"
        );
    }

    /**
     * Execute a POST to the GraphQL endpoint.
     *
     * Use this over graphQL() when you need more control or want to
     * test how your server behaves on incorrect inputs.
     *
     * @param  array<mixed, mixed>  $data  JSON-serializable payload
     * @param  array<string, string>  $headers  HTTP headers to pass to the POST request
     *
     * @return \Illuminate\Testing\TestResponse
     */
    protected function postGraphQL(array $data, array $headers = [])
    {
        $this->response = $this->postJson(
            $this->graphQLEndpointUrl(),
            $data,
            $headers
        );

        return $this->response;
    }

    /** Assert a specific hit was returned */
    protected function assertHit(array $hit, string $message = '')
    {
        $hits = data_get($this->response->json(), 'data.*.hits', [[]]);
        $this->assertContains($hit, $hits[0], $message);
    }

    /** Assert that the search returned at least one hit */
    protected function assertHasHits(string $message = '')
    {
        $total = data_get($this->response->json(), 'data.*.summary.total', [0]);
        $this->assertGreaterThan(0, $total[0], $message);
    }

    /** Assert that a specific hit was the *first* returned */
    protected function assertTopHit(array $expected, string $message = '')
    {
        $hits = data_get($this->response->json(), 'data.*.hits.items', [[]]);
        $this->assertSame($expected, $hits[0][0], $message);
    }

    /** Assert that the search returned exactly as many hits as we expected */
    protected function assertTotalHits(int $expected, string $message = '')
    {
        $actual = data_get($this->response->json(), 'data.*.summary.total', [0]);
        $this->assertSame($expected, $actual[0], $message);
    }

    // protected function assertTopHitHighlightsFields(array $expected, string $message = '')
    // {
    //     $actual = array_map(
    //         function ($highlight) { return $highlight['field']; },
    //         $this->results['results'][0]['highlights']
    //     );

    //     $this->assertContains($expected, $actual, $message);
    // }

    // protected function assertTopHitHighlightsField(string $expected, string $message = '')
    // {
    //     $actual = array_map(
    //         function ($highlight) { return $highlight['field']; },
    //         $this->results['results'][0]['highlights']
    //     );

    //     $this->assertContains($expected, $actual, $message);
    // }

    // protected function assertTopHitContainsFragment(string $field, string $expectedFragment, string $message = '')
    // {
    //     $actual = $this->extractHighlight(0, $field);
    //     $this->assertSame($expectedFragment, $actual, $message);
    // }

    // protected function extractHighlight(int $hit, string $field): ?string
    // {
    //     foreach ($this->results['results'][$hit]['highlights'] as $highlight) {
    //         if ($highlight['field'] === $field) {
    //             return $highlight['fragments'][0];
    //         }
    //     }

    //     return null;
    // }

    // protected function assertHasNonzeroMaxScore(string $message = '')
    // {
    //     $this->assertGreaterThan(0.0001, $this->results['maxScore'], $message);
    // }

    /**
     * Snapshot comparison of a GraphQL response payload
     */
    protected function assertSnapshot()
    {
        $response = $this->response->json();

        $this->assertNotGraphQLError();
        $this->assertMatchesSnapshot($response, new GraphQLDriver());
    }
}
