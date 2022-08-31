<?php namespace Searchlight;

// TODO: Move this elsewhere. I don't like this AND ASTManipulator
// Searchkit GQL schema aligned with https://github.com/searchkit/searchkit/blob/next/packages/searchkit-schema/src/schema.ts

class Schema
{
    public function handle(): string
    {
        return <<<GQL

type SKSortOption {
    id: ID!
    label: String!
}

type SKSummary {
    total: Float!
    appliedFilters: [SKSelectedFilter]
    disabledFilters: [SKDisabledFilter]
    query: String
    sortOptions: [SKSortOption]
}

type SKDisabledFilter {
    identifier: String!
}

interface SKSelectedFilter @interface(
    resolveType: "Searchlight\\\\Resolvers\\\\SKSelectedFilter@resolveType"
) {
    id: ID!
    identifier: String!
    label: String!
    display: String!
}

type ValueSelectedFilter implements SKSelectedFilter {
    id: ID!
    identifier: String!
    display: String!
    label: String!
    value: String!
}

type NumericRangeSelectedFilter implements SKSelectedFilter {
    id: ID!
    identifier: String!
    label: String!
    display: String!
    min: Float
    max: Float
}

type DateRangeSelectedFilter implements SKSelectedFilter {
    id: ID!
    identifier: String!
    label: String!
    display: String!
    dateMin: String!
    dateMax: String!
}

type GeoBoundingBoxSelectedFilter implements SKSelectedFilter {
    id: ID!
    identifier: String!
    label: String!
    display: String!
    topLeft: SKGeoPoint
    bottomRight: SKGeoPoint
    bottomLeft: SKGeoPoint
    topRight: SKGeoPoint
}

type HierarchicalValueSelectedFilter implements SKSelectedFilter {
    id: ID!
    identifier: String!
    label: String!
    display: String!
    level: Int!
    value: String!
}

type SKGeoPoint {
    lat: Float!
    lon: Float!
}

type SKPageInfo {
    total: Float
    totalPages: Float
    pageNumber: Float
    from: Float
    size: Float
}

type SKHitResults {
    items: [SKHit]
    page: SKPageInfo
    sortedBy: String
}

type SKHighlight {
    field: String!
    fragments: [String!]!
}

interface SKHit @interface(
    resolveType: "Searchlight\\\\Resolvers\\\\SKHit@resolveType"
) {
    id: ID!
}

input SKPageInput {
    from: Float
    size: Float
}

input SKQueryOptions {
    fields: [String]
}

input SKFiltersSet {
    identifier: String!
    value: String
    min: Float
    max: Float
    dateMin: String
    dateMax: String
    geoBoundingBox: SKGeoBoundingBoxInput
    level: Int
}

input SKGeoBoundingBoxInput {
    topLeft: SKGeoPointInput
    bottomRight: SKGeoPointInput
    topRight: SKGeoPointInput
    bottomLeft: SKGeoPointInput
}

input SKGeoPointInput {
    lat: Float!
    lon: Float!
}

interface SKFacetSet @interface(
    resolveType: "Searchlight\\\\Resolvers\\\\SKFacetSet@resolveType"
) {
    identifier: String
    label: String
    type: String
    display: String
    entries: [SKFacetSetEntry]
}

type SKFacetSetEntry {
    label: String
    count: Float
    isSelected: Boolean
    level: Int
    entries: [SKFacetSetEntry]
}

type RefinementSelectFacet implements SKFacetSet {
    identifier: String
    label: String
    type: String
    display: String
    entries: [SKFacetSetEntry]
}

type RangeFacet implements SKFacetSet {
    identifier: String
    label: String
    type: String
    display: String
    entries: [SKFacetSetEntry]
}

type DateRangeFacet implements SKFacetSet {
    identifier: String
    label: String
    type: String
    display: String
    entries: [SKFacetSetEntry]
}

type HierarchicalMenuFacet implements SKFacetSet {
    identifier: String
    label: String
    type: String
    display: String
    entries: [SKFacetSetEntry]
}
GQL;

    }
}
