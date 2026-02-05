# briar-rose


**Briar Rose** is a Laravel-friendly NetSuite client that supports:

- **SuiteTalk REST Web Services (REST Record)** via OAuth 1.0a (HMAC-SHA256)
- **RESTlets** via OAuth 1.0a (HMAC-SHA256)
- Developer ergonomics: relative paths, pagination helpers, hydration helpers, retries/backoff.

---

## Requirements

- PHP ^8.2
- Laravel 10 / 11 / 12 (via illuminate components)
- NetSuite OAuth 1.0a integration (Consumer Key/Secret + Token ID/Secret)

---

## Installation

### Composer

For now (GitHub repo), add the repo to your app’s `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:searsandrew/briar-rose.git"
    }
  ],
  "require": {
    "searsandrew/briar-rose": "^0.2"
  }
}
```

Then:

```bash
composer update searsandrew/briar-rose
```

---

## Configuration

Briar Rose supports configuration via **environment variables** (no config publish required).

### Required

```env
NETSUITE_ACCOUNT=5802217
NETSUITE_BASE_URL=https://5802217.suitetalk.api.netsuite.com

NETSUITE_CONSUMER_KEY=...
NETSUITE_CONSUMER_SECRET=...
NETSUITE_TOKEN_ID=...
NETSUITE_TOKEN_SECRET=...
```

### Optional REST defaults

```env
NETSUITE_REST_DEFAULT_LIMIT=1000

NETSUITE_REST_RETRY=true
NETSUITE_REST_RETRY_MAX=5
NETSUITE_REST_RETRY_BASE_DELAY_MS=250
NETSUITE_REST_RETRY_MAX_DELAY_MS=5000
```

### Optional RESTlet base URL

```env
NETSUITE_RESTLET_BASE_URL=https://5802217.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=1850&deploy=1
```

> Sandbox note: NetSuite sandbox account ids often use underscores in the OAuth realm. Briar Rose will normalize realm formatting for you.

---

## Usage

You can call Briar Rose via the facade:

```php
use Searsandrew\BriarRose\Facades\BriarRose;
```

### SuiteTalk REST Record

#### Get a record (by internal id)

```php
$response = BriarRose::rest()
    ->record('inventoryItem')
    ->get(2336);

$data = $response->json();
```

#### Get only certain fields

```php
$response = BriarRose::rest()
    ->record('inventoryItem')
    ->getFields(2336, ['id', 'itemId', 'displayName']);

$data = $response->json();
```

#### List a record collection (one page)

```php
$response = BriarRose::rest()
    ->record('classification')
    ->list(['limit' => 1000, 'offset' => 0]);

$page = $response->json(); // items + links
```

#### List all pages (generator)

```php
foreach (BriarRose::rest()->record('classification')->listAll(['limit' => 1000]) as $pageResponse) {
    $page = $pageResponse->json();
}
```

#### Iterate all collection items (generator)

```php
foreach (BriarRose::rest()->record('classification')->listItemsAll(['limit' => 1000]) as $item) {
    // Usually contains id + links
    $id = $item['id'] ?? null;
}
```

#### Iterate all item IDs (generator)

```php
foreach (BriarRose::rest()->record('classification')->listItemIdsAll(['limit' => 1000]) as $id) {
    // $id is the internal id
}
```

#### Filter a collection (q=…)

NetSuite supports a `q` query parameter for record collection filtering. Briar Rose exposes this directly:

```php
$response = BriarRose::rest()
    ->record('inventoryItem')
    ->where('isinactive IS false', ['limit' => 1000]);

$page = $response->json();
```

> Note: NetSuite’s filtering syntax varies by record type. Refer to NetSuite docs for supported fields/operators.

---

## Hydration helper (list → getFields)

Collection endpoints typically return **id + links**, not full records.
If you want to cache “id + name” locally, hydrate each record.

### Generator mode

```php
$endpoint = BriarRose::rest()->record('classification');

foreach ($endpoint->hydrateAll(['limit' => 1000], ['id', 'name']) as $row) {
    // $row includes the requested fields
}
```

### Callback mode

```php
$endpoint = BriarRose::rest()->record('classification');

$endpoint->hydrateAll(
    listQuery: ['limit' => 1000],
    fields: ['id', 'name'],
    onRow: function (array $row) {
        // Your app code: upsert locally, cache, etc.
    }
);
```

---

## RESTlets

If you have a RESTlet base URL set, you can call it like this:

```php
$response = BriarRose::restlet()
    ->request('GET', ['listId' => 238]);

$data = $response->json();
```

---

## Error handling

All requests return `Illuminate\Http\Client\Response`. Use standard helpers:

```php
$response->successful();
$response->status();
$response->throw(); // throws RequestException
```

Retries/backoff are enabled by default for common transient failures (429 / 5xx) and honor `Retry-After` when present.

---

## Roadmap

- SuiteQL endpoint helpers (paged queries, incremental sync patterns)
- Better query/filter helpers (thin sugar over NetSuite syntax)
- Additional first-class endpoints (as needed)

---

## License

MIT