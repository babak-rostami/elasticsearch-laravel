<?php

namespace Babak\Elasticsearch\Services;


use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class ElasticsearchService
{
    /*
    |----------------------------------------------------------------------
    | Elasticsearch Client Instance
    |----------------------------------------------------------------------
    | This client is responsible for communicating with Elasticsearch.
    | It is created once and reused for all operations.
    */
    protected Client $client;

    /*
    |----------------------------------------------------------------------
    | Constructor
    |----------------------------------------------------------------------
    | Builds the Elasticsearch client using host and port
    | defined in config/elasticsearch.php.
    |
    | This keeps connection logic in one place.
    */
    public function __construct()
    {
        $host = Config::get('elasticsearch.host') . ':' . Config::get('elasticsearch.port');

        $this->client = ClientBuilder::create()
            ->setHosts([$host])
            ->build();
    }

    /*
    |----------------------------------------------------------------------
    | Full Sync (Create or Replace Document)
    |----------------------------------------------------------------------
    | This method sends the full model data to Elasticsearch.
    |
    | Behavior:
    | - If the document does NOT exist → it will be created
    | - If the document exists → it will be fully replaced
    |
    | IMPORTANT:
    | Any field NOT sent here will be removed from Elasticsearch.
    |
    | Best use cases:
    | - Creating a new model
    | - Updating all searchable fields
    */
    public function sync(Model $model): void
    {
        $this->client->index([
            'index' => $model::elasticsearchIndex(),
            'id'    => $model->getKey(),
            'body'  => $model->toElasticsearchArray(),
        ]);
    }

    /*
    |----------------------------------------------------------------------
    | Partial Update (Update Specific Fields Only)
    |----------------------------------------------------------------------
    | Updates ONLY the given fields in the Elasticsearch document.
    |
    | Behavior:
    | - Only provided fields are changed
    | - Other fields remain untouched
    |
    | Best use cases:
    | - Small updates (like updating title only)
    | - Avoiding full document replacement
    |
    | Not recommended for full model updates.
    */
    public function update(Model $model, array $fields): void
    {
        $this->client->update([
            'index' => $model::elasticsearchIndex(),
            'id'    => $model->getKey(),
            'body'  => [
                'doc' => $fields,
            ],
        ]);
    }

    /*
    |----------------------------------------------------------------------
    | Delete Document
    |----------------------------------------------------------------------
    | Removes a single document from Elasticsearch.
    |
    | If the index does not exist, nothing happens.
    */
    public function delete(Model $model): void
    {
        if (! $this->indexExists($model::elasticsearchIndex())) {
            return;
        }

        $this->client->delete([
            'index' => $model::elasticsearchIndex(),
            'id'    => $model->getKey(),
        ]);
    }

    /*
    |----------------------------------------------------------------------
    | Create Index
    |----------------------------------------------------------------------
    | Creates a new Elasticsearch index with the given mapping.
    |
    | If the index already exists, it will NOT be recreated.
    |
    | Usually called once per model.
    */
    public function createIndex($index, $mapping): void
    {
        if ($this->indexExists($index)) {
            return;
        }

        $this->client->indices()->create([
            'index' => $index,
            'body'  => $mapping,
        ]);
    }

    /*
    |----------------------------------------------------------------------
    | Delete Index
    |----------------------------------------------------------------------
    | Completely removes an index and all its documents.
    |
    | WARNING:
    | This operation is irreversible.
    |
    | Common use cases:
    | - Resetting data
    | - Recreating index with new mapping
    */
    public function deleteIndex(string $index): void
    {
        if (! $this->indexExists($index)) {
            return;
        }

        $this->client->indices()->delete([
            'index' => $index,
        ]);
    }

    /*
    |----------------------------------------------------------------------
    | Check If Index Exists
    |----------------------------------------------------------------------
    | Returns true if the given index exists in Elasticsearch.
    */
    public function indexExists(string $index): bool
    {
        return $this->client
            ->indices()
            ->exists(['index' => $index])
            ->asBool();
    }

    /*
    |----------------------------------------------------------------------
    | Bulk Index
    |----------------------------------------------------------------------
    | Indexes multiple models in a single request.
    |
    | This is much faster than indexing one-by-one.
    |
    | Best use cases:
    | - Initial data sync
    | - Reindexing large datasets
    */
    public function bulkIndex(Collection $models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $body = [];

        foreach ($models as $model) {
            $body[] = [
                'index' => [
                    '_index' => $model::elasticsearchIndex(),
                    '_id'    => $model->getKey(),
                ],
            ];

            $body[] = $model->toElasticsearchArray();
        }

        $this->client->bulk(['body' => $body]);
    }

    /*
    |----------------------------------------------------------------------
    | Check If Document Exists
    |----------------------------------------------------------------------
    | Checks whether a specific document exists in Elasticsearch.
    */
    public function documentExists(Model $model): bool
    {
        if (! $this->indexExists($model::elasticsearchIndex())) {
            return false;
        }

        return $this->client->exists([
            'index' => $model::elasticsearchIndex(),
            'id'    => $model->getKey(),
        ])->asBool();
    }

    /*
    |----------------------------------------------------------------------
    | Normal Search
    |----------------------------------------------------------------------
    | Performs a standard full-text search.
    |
    | - All words must match (AND operator)
    | - No typo tolerance
    | - Faster and more strict
    */
    public function searchNormal(
        string $index,
        string $query,
        array $fields,
        int $size = 10
    ): array {
        $response = $this->client->search([
            'index' => $index,
            'body' => [
                'size' => $size,
                'query' => [
                    'multi_match' => [
                        'query'    => $query,
                        'fields'  => $fields,
                        'operator' => 'and',
                    ],
                ],
            ],
        ]);

        return collect($response['hits']['hits'])
            ->pluck('_id')
            ->toArray();
    }

    /*
    |----------------------------------------------------------------------
    | Fuzzy Search (Typo Tolerant)
    |----------------------------------------------------------------------
    | Used when normal search returns no results.
    |
    | Features:
    | - Allows up to 1 typo
    | - First 2 characters must be correct
    | - Useful for user mistakes
    */
    public function searchFuzzy(
        string $index,
        string $query,
        array $fields,
        int $size = 10
    ): array {
        $response = $this->client->search([
            'index' => $index,
            'body' => [
                'size' => $size,
                'query' => [
                    'multi_match' => [
                        'query'           => $query,
                        'fields'          => $fields,
                        'fuzziness'       => 1,
                        'operator'        => 'and',
                        'prefix_length'   => 2,
                        'max_expansions'  => 20,
                    ],
                ],
            ],
        ]);

        return collect($response['hits']['hits'])
            ->pluck('_id')
            ->toArray();
    }

    /*
    |----------------------------------------------------------------------
    | Smart Search
    |----------------------------------------------------------------------
    | First tries normal search.
    | If no results are found, falls back to fuzzy search.
    |
    | This gives:
    | - Fast results when possible
    | - Flexible results when needed
    */
    public function smartSearch(
        string $index,
        string $query,
        array $fields
    ): array {
        $results = $this->searchNormal($index, $query, $fields);

        if (! empty($results)) {
            return $results;
        }

        return $this->searchFuzzy($index, $query, $fields);
    }
}
