<?php namespace Tests;

/**
 * Tests against the refinement select facet
 */
final class RefinementSelectFacetTest extends TestCase
{
    public function testFilterHits()
    {
        $this->graphQL('
        query {
            results(
                filters: [
                    {identifier: "type", value: "game"}
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
                                type
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

    public function testListAvailableFacets()
    {
        $this->graphQL('
        query {
            results(filters: [{identifier: "type", value: "game"}]) {
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
                    identifier: "actors"
                    query: "al"             ## Prefix for filtering facet entries
                    size: 20                ## Number of entries to return
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
                {identifier: "type", value: "game"}
            ]) {
                summary {
                    query
                    appliedFilters {
                        id
                        identifier
                        display
                        label
                        ... on ValueSelectedFilter {
                            value
                        }
                    }
                }
                hits {
                    items {
                        id
                        ... on ResultHit {
                            fields {
                                type
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

    public function testDisjunctiveFilterHits()
    {
        $this->graphQL('
        query {
            results(filters: [
                {identifier: "type", value: "episode"}
                {identifier: "type", value: "game"}
            ]) {
                summary {
                    query
                    appliedFilters {
                        id
                        identifier
                        display
                        label
                        ... on ValueSelectedFilter {
                            value
                        }
                    }
                }
                hits {
                    items {
                        id
                        ... on ResultHit {
                            fields {
                                type
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
