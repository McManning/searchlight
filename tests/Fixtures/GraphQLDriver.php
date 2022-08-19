<?php namespace Tests\Fixtures;

use PHPUnit\Framework\Assert;
use Spatie\Snapshots\Driver;
use Spatie\Snapshots\Exceptions\CantBeSerialized;

/**
 * Custom PHPUnit Snapshot Assertions driver that ignores
 * extension content while comparing GraphQL responses.
 */
class GraphQLDriver implements Driver
{
    public function serialize($data): string
    {
        if (is_string($data)) {
            $data = json_decode($data);
        }

        if (is_resource($data)) {
            throw new CantBeSerialized('Resources can not be serialized to json');
        }

        return json_encode($data, JSON_PRETTY_PRINT)."\n";
    }

    public function extension(): string
    {
        return 'json';
    }

    public function match($expected, $actual)
    {
        if (is_string($actual)) {
            $actual = json_decode($actual, true, 512, JSON_THROW_ON_ERROR);
        }

        $actual = $this->sanitize($actual);

        $expected = $this->sanitize(
            json_decode($expected, true, 512, JSON_THROW_ON_ERROR)
        );

        Assert::assertJsonStringEqualsJsonString(
            json_encode($expected),
            json_encode($actual),
            'Failed asserting that the two GraphQL responses are equivalent'
        );
    }

    protected function sanitize(array $payload): array
    {
        unset($payload['extensions']);
        return $payload;
    }
}
