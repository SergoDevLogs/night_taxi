<?php

namespace App\Providers;

use GuzzleHttp\Client;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Guzzle-клиент для вызовов Python/FastAPI (InstructionClient)
        $this->app->singleton(Client::class, function () {
            return new Client([
                'http_errors' => true,
                'headers'     => ['Accept' => 'application/json'],
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Пагинация в формате из OpenAPI-спеки:
        // meta: { current_page, last_page, total, per_page }
        JsonResource::macro('paginationInformation', function ($request, $paginator, $default) {
            if ($paginator instanceof LengthAwarePaginator) {
                return [
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page'    => $paginator->lastPage(),
                        'total'        => $paginator->total(),
                        'per_page'     => $paginator->perPage(),
                    ],
                ];
            }

            return $default;
        });
    }
}
