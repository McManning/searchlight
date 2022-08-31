<?php namespace Tests;

/**
 * Tests against the range facet
 */
final class RangeFacetTest extends TestCase
{
    public function testFilterHits()
    {
        $this->graphQL('
        query {
            results(
                filters: [
                    {identifier: "imdbrating", min: 6, max: 8}
                ]
            ) {
                summary {
                    total
                }
                hits {
                    items {
                        id
                        ... on ResultHit {
                            fields {
                                imdbrating
                                title
                            }
                        }
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    public function testListFacets()
    {
        $this->graphQL('
        query {
            results(
                filters: [
                    {identifier: "imdbrating", min: 6, max: 8}
                ]
            ) {
                facets {
                    identifier
                    label
                    type
                    display
                    entries {
                        label
                        count
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    public function testQueryFacet()
    {
        $this->graphQL('
        query {
            results {
                facet(
                    identifier: "imdbrating"
                    size: 3
                ) {
                    identifier
                    label
                    type
                    display
                    entries {
                        label
                        count
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    /** Ensure the faceted value is included in the applied filters list */
    public function testAppliedFiltersSummary()
    {
        $this->graphQL('
        query {
            results(filters: [
                {identifier: "imdbrating", min: 6, max: 8}
            ]) {
                summary {
                    query
                    appliedFilters {
                        id
                        identifier
                        display
                        label
                        ... on NumericRangeSelectedFilter {
                            min
                            max
                        }
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    public function testOmitMin()
    {
        $this->graphQL('
        query {
            results(
                filters: [
                    {identifier: "imdbrating", max: 8}
                ]
            ) {
                summary {
                    total
                }
                hits {
                    items {
                        id
                        ... on ResultHit {
                            fields {
                                imdbrating
                                title
                            }
                        }
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    public function testOmitMax()
    {
        $this->graphQL('
        query {
            results(
                filters: [
                    {identifier: "imdbrating", min: 8}
                ]
            ) {
                summary {
                    total
                }
                hits {
                    items {
                        id
                        ... on ResultHit {
                            fields {
                                imdbrating
                                title
                            }
                        }
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    public function testMultipleRangesOnSameFacet()
    {
        $this->graphQL('
        query {
            results(filters: [
                {identifier: "imdbrating", min: 8, max: 10}
                {identifier: "imdbrating", min: 1, max: 6}
            ]) {
                summary {
                    query
                    appliedFilters {
                        id
                        identifier
                        display
                        label
                        ... on NumericRangeSelectedFilter {
                            min
                            max
                        }
                    }
                }
                hits {
                    items {
                        id
                        ... on ResultHit {
                            fields {
                                imdbrating
                                title
                            }
                        }
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();

        $this->markTestSkipped(
            'TODO: This is incorrect. post_filters contains only the first filter.'
        );
    }
}
