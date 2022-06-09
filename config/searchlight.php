<?php

use McManning\Searchlight\Queries\MultiMatchQuery;
use McManning\Searchlight\Facets\RefinementSelectFacet;
use McManning\Searchlight\Filters\TermFilter;

return [

    /*
    |--------------------------------------------------------------------------
    | Search Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Blah blah blah
    |
    */
    'providers' => [

        /*
         * Providers can be referenced by name within GraphQL directives
         */
        'default' => [
            /*
             * Host name of the ElasticSearch instance
             */
            'host' => env('SEARCHLIGHT_DEFAULT_HOST'),

            /*
             * ElasticSearch index to execute _search against
             */
            'index' => env('SEARCHLIGHT_DEFAULT_INDEX'),

            /*
             * Optional HTTP Basic Auth credentials
             */
            'http_basic_auth' => [
                'username' => env('SEARCHLIGHT_HTTP_USER'),
                'password' => env('SEARCHLIGHT_HTTP_PASS'),
            ],

            /*
             * Optional type name to automatically add to the Query type.
             * If omitted, you will need to manually add a field that accepts
             * searchkit inputs and returns your defined result_type.
             */
            'query_type' => 'results',

            /*
             * Type name for results returned
             */
            'result_type' => 'ResultSet',

            /**
             * Type name for each hit returned by ElasticSearch
             */
            'hit_type' => 'ResultHit',

            /*
             * If true, the `query_type` field will be automatically added to
             * the Query type and return a `result_type` with hits as `hit_type`.
             *
             * Setting this to false will require manual creation of the type
             * definition.
             */
            'add_to_query_type' => true,

            /*
             * Default pagination rules.
             *
             * These may be overridden by the API consumer on a per-request basis.
             */
            'paging' => [
                'from' => 0,
                'size' => 10,

                // TODO: Maximum values? I can see a use case for limiting page size.
            ],

            'query' => new MultiMatchQuery([
                // 'fields' => ['actors', 'writers', 'title^4', 'plot'],
                'fields' => ['name^4', 'description', 'referenced_by', 'inverse_referenced_by'],
            ]),

            // TODO: It's common for Laravel packages to use a class name
            // and related options instead of instantiating here. Why?
            // Is it just convention or is there a benefit?
            // If so, might look like the following instead:
            // 'query' => [
            //     'resolver' => MultiMatchQuery::class,
            //     'fields' => ['name^4', 'description', 'referenced_by', 'inverse_referenced_by'],
            // ]

            'hits' => [
                'fields' => ['name', 'description', 'category', 'kind'],
                'highlightedFields' => ['name', 'description', 'category', 'kind'],
            ],

            'sort' => [
                'default' => 'score',

                'options' => [
                    'score' => [
                        'label' => 'Score',
                        'field' => '_score',
                    ],
                    'category_desc' => [
                        'label' => 'Category (desc)',
                        'field' => 'category.keyword',

                        // Additional rules on the sort.
                        // This supports full ElasticSearch syntax for advanced rules
                        // per sorting field, e.g. {"order" : "asc", "format": "strict_date_optional_time_nanos"}
                        // See: https://www.elastic.co/guide/en/elasticsearch/reference/current/sort-search-results.html
                        'sort' => 'desc',
                    ],
                    'category_asc' => [
                        'label' => 'Category (asc)',
                        'field' => 'category.keyword',
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
        ],
    ],
];
