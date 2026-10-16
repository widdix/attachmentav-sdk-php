# Development

The SDK is hand-written (not generated with openapi-generator) so that it is framework-agnostic (PSR-18/PSR-17) and integrates with Laravel. `api.yml` is a copy of the OpenAPI specification from [attachmentav-oas](https://github.com/widdix/attachmentav-oas) for reference.

## Run tests

```bash
composer update
vendor/bin/phpunit
```

Without a local PHP installation, use Docker:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer update
docker run --rm -v "$PWD":/app -w /app php:8.0-cli vendor/bin/phpunit
```

## Publish

1. Update `AttachmentAVApi::VERSION`.
2. Tag the release and push the tag. Packagist picks up new tags automatically once the GitHub repository is registered at https://packagist.org/packages/submit.

```bash
git tag v1.0.0
git push --tags
```
