<?php

namespace Babak\Elasticsearch\Traits;

use Babak\Elasticsearch\Facades\Elasticsearch;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;

trait ElasticsearchableTrait
{
    /*
    |--------------------------------------------------------------------------
    | Convert Model to Elasticsearch Document
    |--------------------------------------------------------------------------
    | This method prepares model data before sending it to Elasticsearch.
    |
    | It takes the full model array and keeps ONLY the fields
    | that are allowed to be indexed in Elasticsearch.
    |
    | Example:
    | Model attributes:  id, title, body, created_at
    | elasticsearchFields(): ['title']
    |
    | Result:
    | ['title' => 'Post title']
    |
    | This prevents unnecessary data from being indexed.
    */
    public function toElasticsearchArray(): array
    {
        return Arr::only(
            $this->toArray(),
            static::elasticsearchFields()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Index Mapping
    |--------------------------------------------------------------------------
    | This method builds the index configuration for Elasticsearch.
    |
    | - settings.analysis:
    |   Loaded from config/elasticsearch.php
    |   Contains analyzers, tokenizers, filters (autocomplete, fulltext, etc.)
    |
    | - mappings.properties:
    |   Defined by each Model separately.
    |   Determines field types and which analyzer each field uses.
    |
    | This method is used when creating the Elasticsearch index.
    */
    public static function elasticsearchMapping(): array
    {
        return [
            'settings' => [
                'analysis' => Config::get('elasticsearch.analysis'),
            ],
            'mappings' => [
                'properties' => static::elasticsearchProperties(),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Search Using Elasticsearch
    |--------------------------------------------------------------------------
    | This is the main search entry point for models.
    |
    | Flow:
    | 1. Sends the query to Elasticsearch (normal + fuzzy fallback).
    | 2. Elasticsearch returns a list of matching IDs (ordered by relevance).
    | 3. We fetch records from the database using those IDs.
    | 4. Results are returned as Eloquent models.
    |
    | Important:
    | Elasticsearch decides the relevance order,
    | and we keep the SAME order in the database result.
    */
    public static function searchElastic(string $query)
    {
        // Step 1: Search in Elasticsearch and get ordered IDs
        $ids = Elasticsearch::smartSearch(
            static::elasticsearchIndex(),
            $query,
            static::elasticsearchFields()
        );

        // If Elasticsearch returns nothing, return empty collection
        if (empty($ids)) {
            return collect();
        }

        // Ensure IDs are integers (important for SQL safety)
        $ids = array_map('intval', $ids);

        /*
        |--------------------------------------------------------------------------
        | Preserve Elasticsearch Result Order
        |--------------------------------------------------------------------------
        | Databases do NOT keep order when using WHERE IN.
        |
        | Elasticsearch returns results ordered by relevance:
        | Example IDs: [9, 10, 3]
        |
        | This CASE statement forces the database to return rows
        | in the exact same order as Elasticsearch.
        |
        | Generated SQL (simplified):
        | ORDER BY CASE id
        |   WHEN 9 THEN 0
        |   WHEN 10 THEN 1
        |   WHEN 3 THEN 2
        | END
        */
        $orderBy = 'CASE id ';
        foreach ($ids as $index => $id) {
            $orderBy .= "WHEN {$id} THEN {$index} ";
        }
        $orderBy .= 'END';

        // Step 4: Fetch models from database in correct order
        return static::query()
            ->whereIn('id', $ids)
            ->orderByRaw($orderBy)
            ->get();
    }

    public static function createElasticIndex()
    {
        $index = static::elasticsearchIndex();
        $mapping = static::elasticsearchMapping();

        Elasticsearch::createIndex($index, $mapping);
    }

    public static function deleteElasticIndex(): void
    {
        Elasticsearch::deleteIndex(static::elasticsearchIndex());
    }

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Field Mapping (Required)
    |--------------------------------------------------------------------------
    | Each model MUST define this method.
    |
    | It describes:
    | - Field names
    | - Field types (text, keyword, integer, date, ...)
    | - Which analyzer is used for each field
    */
    abstract protected static function elasticsearchProperties(): array;

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Indexable Fields (Required)
    |--------------------------------------------------------------------------
    | Returns a list of model fields that should be indexed.
    |
    | These fields are:
    | - Sent to Elasticsearch
    | - Used for searching
    */
    abstract protected static function elasticsearchFields(): array;

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Index Name (Required)
    |--------------------------------------------------------------------------
    | Returns the index name used in Elasticsearch.
    |
    | Example:
    | posts, users, products
    */
    abstract public static function elasticsearchIndex(): string;
}
