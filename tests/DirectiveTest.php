<?php namespace Tests;

final class DirectiveTest extends TestCase
{
    public function testDefaultResolver(): void
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
}
