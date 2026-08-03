# Running tests

Unit tests run standalone — no PrestaShop instance required.

```bash
composer install
composer test
```

Or via Docker, without installing PHP/Composer locally:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 install --no-interaction
docker run --rm -v "$PWD":/app -w /app php:8.1-cli php vendor/bin/phpunit
```
