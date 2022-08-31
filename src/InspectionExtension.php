<?php namespace Searchlight;

use Searchlight\Events\ExecuteSearch;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Execution\ExtensionsResponse;

class InspectionExtension
{
    /** @var int Aggregate time took for all ElasticSearch queries */
    protected int $took = 0;

    // TODO: Sum of time it took *outside* Elastic (PHP execution time, network latency, etc)

    /** @var array Aggregation of request + response trips and the body payloads in each */
    protected array $trips = [];

    // TODO: Error response aggregations?

    public function handleStartExecution(StartExecution $startExecution): void
    {
        // do setup, config will be request+response cycles
        // (in case we have multiple)
    }

    public function handleBuildExtensionsResponse(BuildExtensionsResponse $buildExtensionsResponse): ExtensionsResponse
    {
        return new ExtensionsResponse(
            'searchlight_inspection',
            [
                'version' => 1,
                'took' => $this->took,
                'trips' => $this->trips,
            ]
        );
    }

    public function handleExecuteSearch(ExecuteSearch $executeSearch): void
    {
        $response = $executeSearch->response->getRawResponse();

        // TODO: Arguments / GraphQL type information so we can
        // associate Query.field: ResultSetType -> trip instance
        $this->trips[] = [
            'query' => $executeSearch->response->getRawQuery(),
            'response' => $response,
        ];

        $this->took += data_get($response, 'took', 0);
    }
}

