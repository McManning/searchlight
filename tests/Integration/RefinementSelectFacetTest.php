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
                    {identifier: "rated", value: "R"}
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
                                rated
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
                {identifier: "type", value: "movie"}
                {identifier: "countries", value: "UK"}
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
            }
        }
        ');

        $this->assertSnapshot();
    }

    /**
     * Ensure that disjunctive facets (type) are all properly
     * applied alongside typical refinement facets
     */
    public function testDisjunctiveFilterHits()
    {
        $this->graphQL('
        query {
            results(filters: [
                {identifier: "type", value: "episode"}
                {identifier: "type", value: "game"}
                {identifier: "countries", value: "UK"}
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
