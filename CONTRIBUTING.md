
# Contributing

... will eventually be written in some nice format. For now though:

If you want to run the integration tests:

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

4. Fire off PHPUnit tests with `vendor/bin/phpunit`

If you're running PHP from another docker container, make sure they're networked. E.g.:

```
docker network connect searchlight_default <php-app-container>
```

