<?php

namespace App\Providers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // PHP discards uploads larger than post_max_size. This is detected before the session starts, so redirect
        // back to the form (which needs the session for the CSRF token) instead of showing an error page.
        $this->app->make(ExceptionHandler::class)->renderable(function (PostTooLargeException $e) {
            return redirect()->route('scan.show', ['error' => 'too-large']);
        });
    }
}
