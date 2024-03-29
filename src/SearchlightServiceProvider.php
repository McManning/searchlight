<?php namespace Searchlight;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\BuildSchemaString;
use Searchlight\Events\ExecuteSearch;

class SearchlightServiceProvider extends IlluminateServiceProvider
{
    public function register(): void
    {
        // Omitting config merging for now. All configurations are encapsulated into
        // a provider; so an end user would need to setup a full provider's worth of settings.
        // This may change in the future if we add more global settings.
        // e.g. security may be moved to the `searchlight.security` level
        // $this->mergeConfigFrom(__DIR__.'/../config/searchlight.php', 'searchlight');

        $this->app->singleton(InspectionExtension::class);
    }

    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__.'\\Directives';
            }
        );

        $dispatcher->listen(ManipulateAST::class, ASTManipulator::class);
        $dispatcher->listen(BuildSchemaString::class, Schema::class);

        $dispatcher->listen(
            StartExecution::class,
            InspectionExtension::class . '@handleStartExecution'
        );

        $dispatcher->listen(
            BuildExtensionsResponse::class,
            InspectionExtension::class . '@handleBuildExtensionsResponse'
        );

        $dispatcher->listen(
            ExecuteSearch::class,
            InspectionExtension::class . '@handleExecuteSearch'
        );

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/searchlight.example.php' => config_path('searchlight.php'),
            ], 'config');
        }
    }
}
