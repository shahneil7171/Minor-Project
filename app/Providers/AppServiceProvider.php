<?php

namespace App\Providers;

use App\Models\Address;
use App\Models\Category;
use App\Models\Review;
use App\Models\User;
use App\Policies\AddressPolicy;
use App\Policies\ReviewPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
protected $policies = [
    Address::class => AddressPolicy::class,
    Review::class => ReviewPolicy::class,
];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Single instance per request so catalog reads are memoized and
        // writes through the same service stay consistent.
        $this->app->singleton(\App\Services\ProductCatalogService::class);

        // PHASE 3 — one instance of each per request so exchange rates are read
        // from the settings table once instead of once per rendered price.
        $this->app->singleton(\App\Services\PreferenceService::class);
        $this->app->singleton(\App\Services\CurrencyService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
        | PHASE 3 — money rendering.
        |
        | @money($amount)          format a BASE-currency amount in the currency
        |                          the visitor is currently browsing in.
        | @orderMoney($o, $amount) format a base amount with the code + rate the
        |                          order snapshotted at checkout, so a historical
        |                          order never re-prices itself.
        |
        | Directives (not helpers) keep this dependency-free for Blade and keep
        | every view expression valid PHP without an autoloaded helper file.
        */
        \Illuminate\Support\Facades\Blade::directive('money', function (string $expression): string {
            return "<?php echo e(app(\\App\\Services\\CurrencyService::class)->format({$expression})); ?>";
        });

        \Illuminate\Support\Facades\Blade::directive('orderMoney', function (string $expression): string {
            [$order, $amount] = array_pad(explode(',', $expression, 2), 2, '0');

            return "<?php echo e({$order}->money({$amount})); ?>";
        });

        Gate::define('manage-reviews', function (User $user): bool {
        return $user->account_type === 'admin';
    });
        // Share the active navigation categories (top-level, with their subcategories)
        // with any view that renders the main header/layout.
        View::composer('layouts.app', function (\Illuminate\View\View $view) {
            $navCategories = collect();

            try {
                $navCategories = Category::query()
                    ->active()
                    ->parent()
                    ->ordered()
                    ->with(['children' => function ($query) {
                        $query->active()->ordered();
                    }])
                    ->get();
            } catch (\Throwable $e) {
                // Categories table may not exist yet on a fresh install; render menu without it.
                $navCategories = collect();
            }

            $view->with('navCategories', $navCategories);
        });
    }
}
