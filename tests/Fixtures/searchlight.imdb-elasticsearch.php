<?php

use Searchlight\Queries\MultiMatchQuery;
use Searchlight\Facets\RefinementSelectFacet;
use Searchlight\Facets\RangeFacet;
use Searchlight\Facets\DateRangeFacet;
use Searchlight\Facets\HierarchicalMenuFacet;
use Searchlight\Filters\TermFilter;

return [
    'providers' => [
        /*
         * Configurations for the test IMDb search database
         */
        'default' => [
            // Assumes a Docker container if not set as an envvar.
            'host' => env('SEARCHLIGHT_DEFAULT_HOST', 'elasticsearch:9200'),
            'index' => env('SEARCHLIGHT_DEFAULT_INDEX', 'imdb_movies'),

            /*
             * Optional HTTP Basic Auth credentials
             */
            'http_basic_auth' => [
                'username' => env('SEARCHLIGHT_DEFAULT_AUTH_USERNAME'),
                'password' => env('SEARCHLIGHT_DEFAULT_AUTH_PASSWORD'),
            ],

            'query_type' => 'results',
            'result_type' => 'ResultSet',
            'hit_type' => 'ResultHit',

            'add_to_query_type' => true,

            'paging' => [
                'from' => 0,
                'size' => 10,
            ],

            'query' => new MultiMatchQuery([
                'fields' => [
                    'title',
                    'genres',
                    'directors',
                    'writers',
                    'actors',
                    'countries',
                    'plot',
                ],
            ]),

            'hits' => [
                'fields' => [
                    'type',
                    'title',
                    'year',
                    'rated',
                    'released',
                    'genres',
                    'directors',
                    'writers',
                    'actors',
                    'countries',
                    'plot',
                    'poster',
                    'id',
                    'metascore',
                    'imdbrating',
                    'primary_genre1',
                    'primary_genre2',
                    'primary_genre3',
                ],
            ],

            'sort' => [
                'default' => 'relevance',

                'options' => [
                    'relevance' => [
                        'label' => 'Relevance',
                        'field' => '_score',
                    ],
                    'rated_desc' => [
                        'label' => 'Rated (desc)',
                        'field' => 'rated',
                        'sort' => 'desc',
                    ],
                    'rated_asc' => [
                        'label' => 'Rated (asc)',
                        'field' => 'rated',
                        'sort' => 'asc',
                    ],
                ],
            ],

            'facets' => [
                new RefinementSelectFacet([
                    'identifier' => 'type',
                    'label' => 'Type',
                    'field' => 'type',
                    'multiple_select' => true,
                ]),
                new RefinementSelectFacet([
                    'identifier' => 'rated',
                    'label' => 'Rated',
                    'field' => 'rated',
                ]),
                new RefinementSelectFacet([
                    'identifier' => 'genres',
                    'label' => 'Genres',
                    'field' => 'genres.keyword',
                    'multiple_select' => true,
                ]),
                new RefinementSelectFacet([
                    'identifier' => 'directors',
                    'label' => 'Director',
                    'field' => 'directors.keyword',
                ]),
                new RefinementSelectFacet([
                    'identifier' => 'writers',
                    'label' => 'Writer',
                    'field' => 'writers.keyword',
                ]),
                new RefinementSelectFacet([
                    'identifier' => 'actors',
                    'label' => 'Actor',
                    'field' => 'actors.keyword',
                ]),
                new RefinementSelectFacet([
                    'identifier' => 'countries',
                    'label' => 'Country',
                    'field' => 'countries.keyword',
                ]),

                new RangeFacet([
                    'identifier' => 'imdbrating',
                    'label' => 'IMDb Rating',
                    'field' => 'imdbrating',
                    'min' => 0,
                    'max' => 10,
                ]),
                new RangeFacet([
                    'identifier' => 'metascore',
                    'label' => 'Metascore',
                    'field' => 'metascore',
                    'min' => 0,
                    'max' => 100,
                    'interval' => 5,
                ]),

                new HierarchicalMenuFacet([
                    'identifier' => 'genre',
                    'label' => 'Genre',
                    'fields' => [
                        'primary_genre1.keyword',
                        'primary_genre2.keyword',
                        'primary_genre3.keyword'
                    ],
                ]),

                new DateRangeFacet([
                    'identifier' => 'released',
                    'label' => 'Released',
                    'field' => 'released',
                ]),
            ],

            // 'base_filters' => [
            //     ['term' => ['_type' => '_doc']],
            // ],

            // 'filters' => [
            //     new TermFilter([
            //         'identifier' => 'location',
            //         'label' => 'Location',
            //         'field' => 'location.keyword',
            //     ]),
            //     new TermFilter([
            //         'identifier' => 'category_term',
            //         'label' => 'Category Term',
            //         'field' => 'category.keyword',
            //     ]),
            // ],
        ],
    ],
];
