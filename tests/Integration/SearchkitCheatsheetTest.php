<?php namespace Tests;

/**
 * Tests to cover Searchkit's GQL Schema Queries Cheatsheet
 *
 * Ref: https://www.searchkit.co/docs/graphql/guides/graphql-schema-queries-cheatsheet
 */
final class SearchkitCheatsheetTest extends TestCase
{
    public function testSimpleHits()
    {
        $this->graphQL('
        query {
            results {
                summary {
                    total
                }
                hits {
                    items {
                        ... on ResultHit {
                            id
                            fields {
                                title
                                year
                            }
                        }
                    }
                }
            }
        }
        ');

        // $this->assertTotalHits(1000);
        // $this->assertTopHit([
        //     'id' => 'tt0111161',
        //     'fields' => [
        //         'title' => 'The Shawshank Redemption',
        //         'year' => '1994',
        //     ]
        // ]);

        $this->assertSnapshot();
    }

    public function testHitQuerying()
    {
        $this->graphQL('
        query {
            results(query: "heat") {
                summary {
                    total
                }
                hits {
                    items {
                        ... on ResultHit {
                            fields {
                                title
                            }
                        }
                    }
                }
            }
        }
        ');

        // $this->assertTotalHits(8);
        // $this->assertTopHit([
        //     'fields' => [
        //         'title' => 'Heat',
        //     ]
        // ]);

        $this->assertSnapshot();
    }

    public function testUnknownFilterIdentifier()
    {
        $this->graphQL('
        query {
            results(
                filters: [
                    {identifier: "foobar", value: "movie"}
                ]
            ) {
                hits {
                    items {
                        id
                    }
                }
            }
        }
        ');

        $this->assertGraphQLError('Unknown filter identifier "foobar"');
    }

    public function testHitFiltering()
    {
        $this->graphQL('
        query {
            results(
                filters: [
                    {identifier: "type", value: "movie"}
                    {identifier: "metascore", min: 0, max: 70}
                    {
                        identifier: "released"
                        dateMin: "1995-01-01T00:00:00.000Z"
                        dateMax: "1996-01-01T00:00:00.000Z"
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
                                title
                                released
                                metascore
                            }
                        }
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    public function testFacets()
    {
        $this->graphQL('
        query {
            results(filters: [{identifier: "type", value: "movie"}]) {
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

    public function testSingleFacet()
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

    public function testPagination()
    {
        $this->graphQL('
        query {
            results {
                hits(
                    page: {from: 100, size: 20}     ## Set current page cursor
                ) {
                    items {
                        id
                    }
                    page {                          ## Current page information
                        total
                        totalPages
                        pageNumber
                        from
                        size
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    public function testSortingResults()
    {
        $this->graphQL('
        query {
            results {
                summary {
                    sortOptions {           ## All available sort options
                        id
                        label
                    }
                }
                hits(
                    sortBy: "rated_desc"    ## The sort ID we want to sort on
                ) {
                    sortedBy                ## The active sort
                    items {
                        id
                        ... on ResultHit {
                            fields {
                                rated
                            }
                        }
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    public function testNumericRangeFilterSummary()
    {
        $this->graphQL('
        query {
            results(filters: [
                {identifier: "metascore", min: 0, max: 100}
            ]) {
                summary {
                    query                       ## The search terms
                    appliedFilters {            ## List of filters currently applied
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
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    public function testDateRangeFilterSummary()
    {
        $this->graphQL('
        query {
            results(filters: [
                {
                    identifier: "released"
                    dateMin: "1995-01-01T00:00:00.000Z"
                    dateMax: "1996-01-01T00:00:00.000Z"
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
                hits {
                    items {
                        id
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    public function testRefinementSelectFilterSummary()
    {
        $this->graphQL('
        query {
            results(filters: [
                {identifier: "type", value: "movie"}
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
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    public function testQueryOptionsFields()
    {
        $this->graphQL('
        query {
            results(query: "heat", queryOptions: {
                fields: ["title^2", "plot^1"]
            }) {
                hits {
                    items {
                        id
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    // TODO: Geo location filtering and hierarchical menu faceting.
    // This requires non-IMDb datasets - or enhancement to indexed IMDb data
}
