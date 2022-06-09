<?php namespace Tests;

/**
 * Quick examples for using the base TestCase
 */
final class ExampleTest extends TestCase
{
    public function testMockResolver(): void
    {
        $this->mockResolver(static function ($root, array $args): string {
            return 'bar';
        });

        $this->schema = '
            type Query {
                foo: String! @mock
            }
        ';

        $this->graphQL('
            {
                foo
            }
        ')->assertJson([
            'data' => [
                'foo' => 'bar'
            ]
        ]);
    }

    public function testCreatePost(): void
    {
        // These won't work since we're not actually running a lighthouse
        // instance for unit testing here. It'd be in the demo/test app instead.

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation ($title: String!) {
                createPost(title: $title) {
                    id
                }
            }
        ', [
            'title' => 'Automatic testing proven to reduce stress levels in developers'
        ])->assertJson([
            'data' => [
                'createPost' => [
                    'id' => '1'
                ]
            ]
        ]);
    }
}
