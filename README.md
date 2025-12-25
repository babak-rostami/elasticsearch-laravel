# Elasticsearch Laravel

A clean, lightweight Elasticsearch integration for Laravel applications.  
This package provides a simple way to index Eloquent models
and perform fast, relevance-based searches using Elasticsearch.

Designed for:

- Full-text search
- Keyword-based search
- Autocomplete and fuzzy search
- Clean model-level integration

---

## Features

- 📦 Model-based indexing using a reusable trait
- 🔍 Smart search (normal search + fuzzy fallback)
- 🧠 Relevance-based result ordering
- ⚙️ Configurable analyzers and mappings
- 🧩 Easy to extend and customize

---

## Installation

Install the package via Composer:

```php
composer require babak-rostami/elasticsearch-laravel
```

## Publish Configuration (Optional)

You can publish the configuration file to customize Elasticsearch settings
(analyzers, tokenizers, autocomplete, etc.):

```php
php artisan vendor:publish --tag=elasticsearch-config
```

This will create: `config/elasticsearch.php`

You can freely modify this file to add:

- Custom analyzers
- Tokenizers
- Filters (ngram, edge_ngram, etc.)

## Basic Usage

### 1. Make a Model Elasticsearchable

Use the `ElasticsearchableTrait` in your Eloquent model.

```php
<?php

namespace App\Models;

use Babak\Elasticsearch\Traits\ElasticsearchableTrait;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use ElasticsearchableTrait;

    protected static function elasticsearchProperties(): array
    {
        return [
            'title' => [
                'type' => 'text',
                'analyzer' => 'autocomplete_index',
                'search_analyzer' => 'autocomplete_search',
            ]
        ];
    }


    protected static function elasticsearchFields(): array
    {
        return ['title'];
    }

    public static function elasticsearchIndex(): string
    {
        return 'posts';
    }
}

```

## 2. Create the Elasticsearch Index

simple Usage:

```php
Post::createElasticIndex();
```

This will:

- Create the index if it does not exist
- Apply your model mapping and analyzers

### Elasticsearch Index Command

This package also provides an interactive Artisan command to create Elasticsearch
indexes and optionally bulk index model data.

#### Usage

```bash
php artisan elasticsearch:index
```

#### Command Flow

You will be asked to enter the model name:

```bash
Which model do you want to index? (Example: Post)
> Post
```

The command will:

- Create the Elasticsearch index if it does not exist
- Skip index creation if it already exists

You will then be asked if you want to bulk index existing records:

```bash
Do you want to bulk index existing model records? (yes/no)
```

- If No → only the index will be created
- If Yes → records will be indexed into Elasticsearch

You can specify how many records should be indexed per batch:

```bash
How many records should be indexed per batch? [500]
```

What This Command Does

- Safely creates Elasticsearch indexes
- Bulk indexes data in memory-safe chunks
- Uses Elasticsearch bulk API for high performance

## 3. Data management in Elasticsearch

This package provides multiple ways to keep your database models
in sync with Elasticsearch, depending on your use case.

## Sync (Create or Replace Document)

```php
Elasticsearch::sync($post);
```

### What it does:

- Creates a new document in Elasticsearch if it does not exist
- Fully replaces the document if it already exists

### Important behavior:

- The entire Elasticsearch document is replaced
- Any field NOT included in toElasticsearchArray() will be removed

### Best use cases:

- Creating a new model
- Updating all searchable fields
- Keeping Elasticsearch fully in sync with the database

Example:

```php
$post = new Post();
$post->title = 'New title';
$post->body = 'laravel elasticsearch search';
$post->save();

Elasticsearch::sync($post);
```

## Partial Update (Update Specific Fields Only)

```php
Elasticsearch::update($post, [
    'title' => 'Updated title'
]);
```

What it does:

- Updates ONLY the given fields
- Other fields in Elasticsearch remain untouched

Important behavior:

- Does NOT replace the entire document
- Only modifies specified fields

Best use cases:

- Small changes (e.g. updating title or tags)
- Avoiding full document reindex
- High-frequency updates

Not recommended for:

- Full model updates
- Structural changes

## Delete Document

What it does:

- Removes the document from Elasticsearch
- Does nothing if the index does not exist

Best use cases:

- Deleting a model
- Keeping Elasticsearch clean and in sync

Example:

```php
$post->delete();
Elasticsearch::delete($post);
```

## Bulk Indexing (Recommended for Large Datasets)

```php
Elasticsearch::bulkIndex(Post::all());
```

What it does:

- Indexes multiple models in a single request
- Much faster than indexing one-by-one

Best use cases:

- Initial data sync
- Reindexing large datasets
- Importing existing database records

## 4. Search

```php
$results = Post::searchElastic('how to use elastic search in laravel');
```

How it works:

- Elasticsearch performs a relevance-based search
- Matching document IDs are returned in correct order
- Results are fetched from the database
- Ordering is preserved exactly as Elasticsearch scored them

If no relevant match exists, an empty collection is returned.

# Smart Search Strategy

This package uses a Smart Search approach:

- Normal full-text search (fast, strict)

- If no results are found → fuzzy search is applied

This provides:

- High performance when possible

- Flexible typo tolerance when needed

# Configuration & Customization

You can fully customize Elasticsearch behavior by editing:

`config/elasticsearch.php`

# Facade Usage

The Elasticsearch facade is available globally:

```php
Elasticsearch::sync($model);
Elasticsearch::delete($model);
Elasticsearch::createIndex($index, $mapping);
Elasticsearch::deleteIndex($index);
```

# License

MIT License.

Feel free to use, modify, and extend.

# Author

**Babak Rostami**
