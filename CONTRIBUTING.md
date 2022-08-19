
# Contributing

## Creating a Test Environment

1. Pull packages with `composer install`.
2. Spin up Elasticsearch in Docker with `docker-compose up`
3. Setup in the index mapping and throw in the IMDb test dataset:

```sh
curl -XPUT "http://localhost:9200/imdb_movies"
```

```sh
curl -H "Content-Type: application/json" -XPUT "http://localhost:9200/imdb_movies/_mapping?pretty" --data-binary "@tests/Data/mapping.json"
```

```sh
curl -H "Content-Type: application/json" -XPOST "http://localhost:9200/imdb_movies/_bulk?pretty" --data-binary "@tests/Data/imdb_movies.json"
```

4. Fire off PHPUnit tests with `composer test`


Note: If you're running PHP from another docker container, make sure they're networked. E.g.:

```sh
docker network connect searchlight_default <php-app-container>
```

# Testing Cheatsheet

## Snapshots

Snapshot comparisons are performed for each test case that uses `assertSnapshot()`. Few rules apply to snapshot tests:

1. An error response from the API is a failure, regardless of snapshot content
2. The GraphQL `extensions` payload is ignored during comparison, allowing analytical data to be included in the snapshot file while not negatively impacting the test case.

Refresh PHPUnit snapshots with:

```sh
composer test-update
```
