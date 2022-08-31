<?php namespace Tests;

/**
 * Tests against query options / criteria
 */
final class QueryOptionsTest extends TestCase
{
    public function testSpecifyFields()
    {
        $this->graphQL('
        query {
            results(query: "heat", queryOptions: {
                fields: ["title^2", "plot^1"]
            }) {
                hits {
                    items {
                        id
                        ... on ResultHit {
                            fields {
                                title
                                plot
                            }
                        }
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    public function testUnknownFieldThrowsError()
    {
        $this->graphQL('
        query {
            results(query: "heat", queryOptions: {
                fields: ["foobar"]
            }) {
                hits {
                    items {
                        id
                    }
                }
            }
        }
        ');

        // $this->assertGraphQLError('Cannot query field "foobar" on type "SKHit"."');
        $this->assertSnapshot();

        $this->markTestSkipped('TODO: Get ES to throw errors on unknown query fields');
    }

    public function testHighlights()
    {
        $this->graphQL('
        query {
            results(query: "heat") {
                hits {
                    items {
                        id
                        ... on ResultHit {
                            fields {
                                title
                                plot
                            }
                            highlights {
                                field
                                fragments
                            }
                        }
                    }
                }
            }
        }
        ');

        $this->assertSnapshot();
    }

    public function testHighlightsOnSpecificFields()
    {
        $this->graphQL('
        query {
            results(query: "action", queryOptions: {
                fields: ["title^2", "genres^1"]
            }) {
                hits {
                    items {
                        id
                        ... on ResultHit {
                            fields {
                                title
                                plot
                                genres
                            }
                            highlights {
                                field
                                fragments
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
