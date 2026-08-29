<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Services\Payment\MockPaymentGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, MockPaymentGateway::class);
    }

    public function boot(): void
    {
        //
    }
}
