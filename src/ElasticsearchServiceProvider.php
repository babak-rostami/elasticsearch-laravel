<?php

namespace Babak\Elasticsearch;

use Illuminate\Support\ServiceProvider;
use Babak\Elasticsearch\Services\ElasticsearchService;

class ElasticsearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('elasticsearch_service', function () {
            return new ElasticsearchService();
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../config/elasticsearch.php',
            'elasticsearch'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/elasticsearch.php' => config_path('elasticsearch.php'),
        ], 'elasticsearch-config');
    }
}
