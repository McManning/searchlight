<?php namespace Tests;

/**
 * Tests to cover security features
 */
final class SecurityTest extends TestCase
{
    public function testMaxPageSize()
    {
        config([
            'searchlight.providers.default.security.max_page_size' => 5
        ]);

        $this->graphQL('
        query {
            results {
                hits(
                    page: {size: 20}
                ) {
                    items {
                        id
                    }
                }
            }
        }
        ');

        $this->assertGraphQLError(
            'Requested page size exceeds the maximum allowed value'
        );
    }

    public function testMaxFacetSize()
    {
        config([
            'searchlight.providers.default.security.max_facet_size' => 5
        ]);

        $this->graphQL('
        query {
            results {
                facet(
                    identifier: "actors"
                    size: 20
                ) {
                    identifier
                }
            }
        }
        ');

        $this->assertGraphQLError(
            'Requested facet size exceeds the maximum allowed value'
        );
    }
}
