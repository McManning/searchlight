<?php namespace Tests;

/**
 * Tests against the hierarchical menu facet
 */
final class HierarchicalMenuFacetTest extends TestCase
{
    public function testFilterHits()
    {
        $this->graphQL('
        query {
            results(
                filters: [
                    {
                        identifier: "genre"
                        level: 1
                        value: "Adventure"
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
                                primary_genre1
                                primary_genre2
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

    /**
     * Ensure that filtering on multiple levels of a hierarchical facet
     * provides results that only match those combined levels.
     *
     * E.g. `Adventure -> Epic`, omitting any `Action -> Epic`
     * or `Adventure -> Quest` results.
     */
    public function testMultilevelFiltering()
    {
        $this->graphQL('
        query {
            results(
                filters: [
                    {
                        identifier: "genre"
                        level: 1
                        value: "Adventure"
                    },
                    {
                        identifier: "genre"
                        level: 2
                        value: "epic"
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
                                primary_genre1
                                primary_genre2
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

    public function testQueryFacet()
    {
        $this->graphQL('
        query {
            results {
                facet(
                    identifier: "genre"
                ) {
                    identifier
                    label
                    type
                    display

                    # Query for 3 levels of hierarchical facets
                    entries {
                        label
                        count
                        isSelected
                        level
                        entries {
                            label
                            count
                            isSelected
                            level
                            entries {
                                label
                                count
                                isSelected
                                level
                            }
                        }
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    /**
     * Ensure that querying nested facet level keywords will only
     * include those filtered down by a parent facet level.
     *
     * E.g. if we filter on "Adventure" as the first level, we don't
     * want to include subgenres under "Action" or "Drama".
     */
    public function testQueryFacetWithAppliedLevels()
    {
        $this->graphQL('
        query {
            results(
                filters: [
                    {
                        identifier: "genre"
                        level: 1
                        value: "Adventure"
                    }
                ]
            ) {
                facet(
                    identifier: "genre"
                    size: 5
                ) {
                    identifier
                    label
                    type
                    display

                    # Query for 3 levels of hierarchical facets
                    entries {
                        label
                        count
                        isSelected
                        level
                        entries {
                            label
                            count
                            isSelected
                            level
                            entries {
                                label
                                count
                                isSelected
                                level
                            }
                        }
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
                    identifier: "genre",
                    level: 1,
                    value: "Adventure"
                }
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

    /** Ensure additional search criteria (size, include) are handled */
    public function testQueryFacetWithCriteria()
    {
        // TODO: Facet order cannot be specified through the API.
        // Should it be? Is there a use case for it?
        $this->graphQL('
        query{
            results {
                facet(
                    identifier: "genre"
                    size: 100
                    query: "d"
                ) {
                    identifier
                    label
                    type
                    display
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    /** Ensure additional search criteria applies to multiple levels */
    public function testQueryFacetWithCriteriaOnMultilevelResults()
    {
        // TODO: This test is incorrect. The facet query applies to the
        // top level facet only so we get back "Documentary" and "Drama"
        // but we don't get back "Adventure" so that we can nest D* level2
        // facets under that as well.
        // There *should* be an assumption that the filter-by values are implicitly
        // included in the results set at all times.
        $this->graphQL('
        query {
            results(
                filters: [
                    {
                        identifier: "genre"
                        level: 1
                        value: "Adventure"
                    }
                ]
            ) {
                facet(
                    identifier: "genre"
                    size: 100
                    query: "d"
                ) {
                    identifier
                    label
                    type
                    display

                    # Query for 3 levels of hierarchical facets
                    entries {
                        label
                        count
                        isSelected
                        level
                        entries {
                            label
                            count
                            isSelected
                            level
                            entries {
                                label
                                count
                                isSelected
                                level
                            }
                        }
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
        $this->markTestSkipped('TODO: This is incorrect. See test notes');
    }
}
