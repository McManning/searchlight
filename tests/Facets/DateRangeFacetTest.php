<?php namespace Tests;

/**
 * Tests against the date range facet
 */
final class DateRangeFacetTest extends TestCase
{
    public function testFilterHits()
    {
        $this->graphQL('
        query {
            results(
                filters: [
                    {
                        identifier: "released"
                        dateMin: "1995-01-01T00:00:00+00:00"
                        dateMax: "1996-01-01T00:00:00+00:00"
                    }
                ]
            ) {
                summary {
                    total
                }
                hits(page: {size: 20}) {
                    items {
                        id
                        ... on ResultHit {
                            fields {
                                released
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
                    {
                        identifier: "released"
                        dateMin: "1995-01-01T00:00:00+00:00"
                        dateMax: "1996-01-01T00:00:00+00:00"
                    }
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
                    identifier: "released"
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
                {
                    identifier: "released"
                    dateMin: "1995-01-01T00:00:00+00:00"
                    dateMax: "1996-01-01T00:00:00+00:00"
                }
            ]) {
                summary {
                    query
                    appliedFilters {
                        id
                        identifier
                        display
                        label
                        ... on DateRangeSelectedFilter {
                            dateMin
                            dateMax
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
                    {
                        identifier: "released"
                        dateMax: "1996-01-01T00:00:00+00:00"
                    }
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
                                released
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
                    {
                        identifier: "released"
                        dateMin: "1996-01-01T00:00:00+00:00"
                    }
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
                                released
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
}
