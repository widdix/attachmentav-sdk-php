# Development

The SDK is hand-written (not generated with openapi-generator) so that it is framework-agnostic (PSR-18/PSR-17) and integrates with Laravel. `api.yml` is a copy of the OpenAPI specification from [attachmentav-oas](https://github.com/widdix/attachmentav-oas) for reference.

## Run tests

There are three test suites:

| Suite | Command | Requirements |
| --- | --- | --- |
| `unit` (default) | `composer test` | PHP 8.0+ |
| `laravel` | `composer test:laravel` | PHP 8.2+ (Laravel 11+ via orchestra/testbench) |
| `integration` | `ATTACHMENTAV_API_KEY=... composer test:integration` | API key for the attachmentAV API (SaaS), region EU. Real requests count against the quota. |

```bash
composer update
composer test
```

On PHP 8.0/8.1, remove the Laravel test dependencies first, because Composer refuses to install end-of-life Laravel releases: `composer remove --dev --no-update orchestra/testbench`.

Without a local PHP installation, use Docker:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer update
docker run --rm -v "$PWD":/app -w /app php:8.4-cli vendor/bin/phpunit
```

## Publish

The package is published at https://packagist.org/packages/attachmentav/virus-scan-sdk. Packagist picks up new tags through the GitHub webhook.

1. Update `AttachmentAVApi::VERSION` and push the change to `main`.
2. Tag the release and push the tag:

```bash
git tag v1.0.0
git push origin v1.0.0
```

The [Release workflow](.github/workflows/release.yml) checks that the tag matches `AttachmentAVApi::VERSION`, runs the tests, creates the GitHub Release, and asks Packagist to update. That last step only runs if the `PACKAGIST_USERNAME` and `PACKAGIST_TOKEN` secrets are set.
