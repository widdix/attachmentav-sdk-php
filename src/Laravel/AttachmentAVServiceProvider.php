<?php

declare(strict_types=1);

namespace AttachmentAV\Laravel;

use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Callback\CallbackVerifier;
use AttachmentAV\Configuration;
use AttachmentAV\Exception\InvalidArgumentException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the attachmentAV API client in the Laravel service container.
 *
 * The provider is registered automatically via package discovery. Publish the config file with:
 *
 *     php artisan vendor:publish --tag=attachmentav-config
 */
class AttachmentAVServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/attachmentav.php', 'attachmentav');

        $this->app->singleton(Configuration::class, static function (Container $app): Configuration {
            /** @var array<string, mixed> $config */
            $config = $app->make('config')->get('attachmentav', []);

            return Configuration::fromArray($config);
        });

        // The SDK creates a Guzzle client with the configured timeout (Guzzle ships with Laravel).
        $this->app->singleton(AttachmentAVApi::class, static function (Container $app): AttachmentAVApi {
            return new AttachmentAVApi($app->make(Configuration::class));
        });
        $this->app->alias(AttachmentAVApi::class, 'attachmentav');

        $this->app->singleton(CallbackVerifier::class, static function (Container $app): CallbackVerifier {
            $configuration = $app->make(Configuration::class);
            $tenantId = (string) $app->make('config')->get('attachmentav.tenant_id', '');
            if (!$configuration->isSaas() || $configuration->getRegion() === null) {
                throw new InvalidArgumentException('Callbacks are only supported by the attachmentAV API (SaaS).');
            }
            if ($tenantId === '') {
                throw new InvalidArgumentException('Set ATTACHMENTAV_TENANT_ID to verify callbacks.');
            }

            return CallbackVerifier::forRegion($configuration->getRegion(), $tenantId);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/attachmentav.php' => config_path('attachmentav.php'),
            ], 'attachmentav-config');
        }
    }
}
