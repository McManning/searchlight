
# Searchlight

![example workflow](https://github.com/mcmanning/searchlight/actions/workflows/ci.yml/badge.svg)

[Searchkit](https://www.searchkit.co/) compatible GraphQL API extensions for applications built with [Lighthouse PHP](https://lighthouse-php.com/).

## Requirements

* PHP ^7.4, ^8
* [Lighthouse PHP](https://lighthouse-php.com/) ^5

## Installation

Install the package via Composer:

```sh
composer require searchlight/searchlight
```

This package uses Laravel auto-discovery to register the service provider.

Publish a copy of the configuration file to your local config:

```sh
php artisan vendor:publish --provider="Searchlight\SearchlightServiceProvider"
```

### Lumen

You can register the service provider in `bootstrap/app.php`:

```php
$app->register(Searchlight\SearchlightServiceProvider::class);
```

To change the configuration, copy `searchlight.example.php` the file to your config folder and enable it:

```php
$app->configure('searchlight');
```

## Configuration

:snail:

## Usage

:snail:

## Contributing

PRs welcome for additional compatibility with Searchkit or bug fixes. Or if you have a cool idea, let me know!

## Security Vulnerabilities

If you discover a security vulnerability within Searchlight, please send an email to security@sybolt.com.

