<?php

use Searchlight\Queries\MultiMatchQuery;
use Searchlight\Facets\RefinementSelectFacet;
use Searchlight\Filters\TermFilter;

return [

    /*
    |--------------------------------------------------------------------------
    | Search Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Multiple search providers may be defined - each with their hosts, facets,
    | filters, GraphQL types, etc.
    |
    */
    'providers' => [

        /*
         * Providers can be referenced by name within GraphQL directives
         */
        'default' => [
            /*
             * Host name of the ElasticSearch or OpenSearch instance
             */
            'host' => env('SEARCHLIGHT_DEFAULT_HOST', 'localhost:9200'),

            /*
             * Index to execute _search against
             */
            'index' => env('SEARCHLIGHT_DEFAULT_INDEX'),

            /*
             * Optional HTTP Basic Auth credentials
             */
            'http_basic_auth' => [
                'username' => env('SEARCHLIGHT_HTTP_USER', null),
                'password' => env('SEARCHLIGHT_HTTP_PASS', null),
            ],

            /*
             * GraphQL type name to automatically add to the Query type.
             *
             * This setting is ignored if `add_to_query_type` is false.
             */
            'query_type' => 'results',

            /*
             * GraphQL type name for the response type to the `query_type`.
             *
             * This setting is ignored if `add_to_query_type` is false.
             */
            'result_type' => 'ResultSet',

            /**
             * GraphQL type name for each hit returned.
             *
             * The type must implement the `SKHit` interface and contain fields
             * typed to match your hit document's fields.
             */
            'hit_type' => 'ResultHit',

            /*
             * If true, the `query_type` field will be automatically added to
             * the Query type and return a `result_type` with hits as `hit_type`.
             *
             * Setting this to false will require manual creation of the type
             * definitions.
             */
            'add_to_query_type' => true,

            /*
             * Default pagination rules.
             *
             * These may be overridden by the API consumer on a per-request basis.
             */
            'paging' => [
                'from' => 0,

                /**
                 * Default number of hits per page
                 */
                'size' => 10,
            ],

            /**
             * Transformer for API queries into search service queries.
             */
            'query' => new MultiMatchQuery([
                /**
                 * Document fields to query against.
                 *
                 * Default relevancy weights may be specified for an
                 * ElasticSearch or OpenSearch search provider.
                 *
                 * If the search provider supports result highlighting,
                 * these fields will also be used for term match highlights.
                 */
                'fields' => ['title^4', 'genres', 'plot'],
            ]),

            // TODO: It's common for Laravel packages to use a class name
            // and related options instead of instantiating here. Why?
            // Is it just convention or is there a benefit?
            // If so, might look like the following instead:
            // 'query' => [
            //     'resolver' => MultiMatchQuery::class,
            //     'fields' => ['name^4', 'description', 'referenced_by', 'inverse_referenced_by'],
            // ]
            // Would this be due to config caching?

            /**
             * The list of document fields requested from the search provider.
             *
             * The GraphQL type specified in `hit_type` should contain this list of fields
             * mapped to their appropriate GraphQL types.
             */
            'hits' => [
                'fields' => ['type', 'title', 'year', 'genres', 'plot', 'metascore', 'released'],
            ],

            /**
             * Options provided to the API consumer for sorting results.
             *
             * A consumer may specify an explicit sort by the ID, e.g. 'title_desc'.
             * If one is not specified, then the `sort.default` option will be chosen.
             */
            'sort' => [
                'default' => 'relevance',

                'options' => [
                    'relevance' => [
                        'label' => 'Relevance',
                        'field' => '_score',
                    ],
                    'title_desc' => [
                        'label' => 'Title (desc)',
                        'field' => 'title.keyword',

                        /**
                         * Optional field to specify more advanced sort configurations.
                         *
                         * This accepts ElasticSearch or OpenSearch syntax.
                         *
                         * For example:
                         *  'sort' => [
                         *      'order' => 'asc',
                         *      'format' => 'strict_date_optional_time_nanos'
                         *  ]
                         */
                        'sort' => 'desc',
                    ],
                    'title_asc' => [
                        'label' => 'Title (asc)',
                        'field' => 'title.keyword',
                        'sort' => 'asc',
                    ],
                ],
            ],

            /*
             * Filtering for the search that should not be displayed as facets.
             *
             * The API consumer apply filters by identifier for the search query.
             */
            'filters' => [
                new TermFilter([
                    'identifier' => 'location',
                    'label' => 'Location',
                    'field' => 'location.keyword',
                ]),
                new TermFilter([
                    'identifier' => 'category_term',
                    'label' => 'Category Term',
                    'field' => 'category.keyword',
                ]),
            ],

            /*
             * Faceting for the search that can be controlled by the end user.
             *
             * The API consumer may configure results for specific facets or apply a
             * facet as a filter to the search query.
             */
            'facets' => [
                new RefinementSelectFacet([
                    'identifier' => 'category',
                    'label' => 'Category',
                    'field' => 'category.keyword',
                    'multiple_select' => true,
                ]),

                new RefinementSelectFacet([
                    'identifier' => 'kind',
                    'label' => 'Kind',
                    'field' => 'kind.keyword',
                    'multiple_select' => true,
                ]),

                // TODO:
                // new Range([
                //     'identifier' => 'metascore',
                //     'label' => 'Metascore',
                //     'field' => 'metaScore',
                //     'range' => [
                //         'min' => 0,
                //         'max' => 100,
                //         'interval' => 5,
                //     ]
                // ]),

                // new HierarchicalMenu([
                //     'identifier' => 'categories',
                //     'label' => 'Categories',
                //     'fields' => ['categories1', 'categories2', 'categories3'],
                // ]),
            ],

            /*
             * Lucene base filters to be appended to the ElasticSearch filter.
             *
             * These will not appear in the SKSummary response.
             */
            'base_filters' => [
                ['term' => ['_type' => '_doc']],
            ],

            /**
             * Additional security restrictions applied to queries.
             */
            'security' => [
                /**
                 * Maximum number of hits that a user may request in one trip.
                 * Exceeding this value will result in a validation error response.
                 *
                 * A value of -1 indicates no limit.
                 */
                'max_page_size' => -1,

                /**
                 * Maximum number of facet entries a user may request in one trip.
                 * Exceeding this value  will result in a validation error response.
                 *
                 * A value of -1 indicates no limit.
                 */
                'max_facet_size' => -1,
            ],
        ],
    ],
];
