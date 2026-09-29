<?php

namespace App\Providers;

use App\Services\Ai\AiProviderFactory;
use App\Support\TokenAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiProviderFactory::class, fn () => new AiProviderFactory(config('vokso.ai')));
    }

    public function boot(): void
    {
        Auth::viaRequest('vokso-token', fn (Request $request) => TokenAuth::userFromRequest($request));
    }
}
