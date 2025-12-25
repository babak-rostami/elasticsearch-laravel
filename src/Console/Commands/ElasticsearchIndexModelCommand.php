<?php

namespace Babak\Elasticsearch\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Babak\Elasticsearch\Facades\Elasticsearch;

class ElasticsearchIndexModelCommand extends Command
{
    protected $signature = 'elasticsearch:index';

    protected $description = 'Create Elasticsearch index and optionally bulk index model data';

    public function handle(): int
    {
        // Ask for model name
        $modelName = $this->ask('Which model do you want to index? (Example: Post)');
        $modelClass = 'App\\Models\\' . $modelName;

        if (! class_exists($modelClass)) {
            $this->error("Model [$modelClass] not found.");
            return self::FAILURE;
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            $this->error("[$modelClass] is not an Eloquent model.");
            return self::FAILURE;
        }

        // Check Elasticsearchable requirements
        foreach (
            [
                'elasticsearchIndex',
                'elasticsearchFields',
                'elasticsearchProperties'
            ] as $method
        ) {
            if (! method_exists($modelClass, $method)) {
                $this->error("Model must implement method: {$method}()");
                return self::FAILURE;
            }
        }

        $index = $modelClass::elasticsearchIndex();

        // Create index if not exists
        if (Elasticsearch::indexExists($index)) {
            $this->info("Index [{$index}] already exists.");
        } else {
            $this->info("Creating Elasticsearch index [{$index}]...");
            $modelClass::createElasticIndex();
            $this->info("Index [{$index}] created successfully.");
        }

        // Ask if user wants to bulk index data
        if (! $this->confirm('Do you want to bulk index existing model records?', true)) {
            $this->info('Index creation completed. No documents indexed.');
            return self::SUCCESS;
        }

        $total = $modelClass::count();

        if ($total === 0) {
            $this->warn('No records found. Nothing to index.');
            return self::SUCCESS;
        }

        // 5️⃣ Ask for chunk size (default: 500)
        $chunkSize = (int) $this->ask(
            'How many records should be indexed per batch?',
            500
        );

        if ($chunkSize <= 0) {
            $this->error('Chunk size must be a positive number.');
            return self::FAILURE;
        }

        // Bulk indexing
        $this->info("Indexing {$total} records in chunks of {$chunkSize}...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $modelClass::query()->chunk($chunkSize, function ($models) use ($bar) {
            Elasticsearch::bulkIndex($models);
            $bar->advance($models->count());
        });

        $bar->finish();
        $this->newLine();
        $this->info('Elasticsearch bulk indexing completed successfully.');

        return self::SUCCESS;
    }
}
