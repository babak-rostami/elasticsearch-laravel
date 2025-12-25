<?php

namespace Babak\Elasticsearch\Providers;

use Babak\Elasticsearch\Console\Commands\ElasticsearchIndexModelCommand;
use Illuminate\Support\ServiceProvider;
use Babak\Elasticsearch\Services\ElasticsearchService;

class ElasticsearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/elasticsearch.php',
            'elasticsearch'
        );

        $this->app->singleton('elasticsearch_service', function () {
            return new ElasticsearchService();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                ElasticsearchIndexModelCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/elasticsearch.php' => config_path('elasticsearch.php'),
        ], 'elasticsearch-config');
    }
}
