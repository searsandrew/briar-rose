# Briar Rose

**Briar Rose** is a Laravel-friendly NetSuite client that supports:

- **SuiteTalk REST Web Services (REST Record)** via OAuth 1.0a (HMAC-SHA256)
- **SuiteQL** queries via SuiteTalk REST Web Services
- **RESTlets** via OAuth 1.0a (HMAC-SHA256)
- Developer ergonomics including relative paths, pagination and hydration helpers, and configurable retries with backoff

---

## Requirements

- PHP ^8.2
- Laravel 10, 11, 12, or 13 (via Illuminate components)
- Guzzle ^7.5 or ^8.0
- NetSuite OAuth 1.0a integration (Consumer Key/Secret + Token ID/Secret)

CI currently tests Laravel 11 through 13. Laravel 10 remains install-compatible but is no longer included in CI because Composer blocks its available releases due to active security advisories.

---

## Installation

### Composer

```bash
composer require searsandrew/briar-rose
```

Laravel discovers the service provider and facade automatically.

---

## Configuration

Briar Rose supports configuration via **environment variables** (no config publish required).

### Required

```env
NETSUITE_ACCOUNT=0000000
NETSUITE_REST_BASE_URL=https://0000000.suitetalk.api.netsuite.com

NETSUITE_CONSUMER_KEY=...
NETSUITE_CONSUMER_SECRET=...
NETSUITE_TOKEN_ID=...
NETSUITE_TOKEN_SECRET=...
```

`NETSUITE_REST_BASE_URL` is optional; when omitted, Briar Rose builds the standard SuiteTalk URL from `NETSUITE_ACCOUNT`. The legacy `NETSUITE_BASE_URL` variable is also supported.

### Optional REST defaults

```env
NETSUITE_REST_DEFAULT_LIMIT=1000

NETSUITE_REST_RETRY=true
NETSUITE_REST_RETRY_MAX=5
NETSUITE_REST_RETRY_BASE_DELAY_MS=250
NETSUITE_REST_RETRY_MAX_DELAY_MS=5000
```

### Optional RESTlet connections

```env
NETSUITE_RESTLET_BASE_URL=https://0000000.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=0000&deploy=1
NETSUITE_RESTLET_SCRIPT_ID=...
NETSUITE_RESTLET_DEPLOY_ID=1
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
    ->get(0000);

$data = $response->json();
```

#### Get only certain fields

```php
$response = BriarRose::rest()
    ->record('inventoryItem')
    ->getFields(0000, ['id', 'itemId', 'displayName']);

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

## SuiteQL

Run a SuiteQL query using the SuiteTalk REST query endpoint:

```php
$response = BriarRose::rest()
    ->suiteql()
    ->query(
        "SELECT id, entityid FROM customer WHERE isinactive = 'F'",
        ['limit' => 1000, 'offset' => 0]
    );

$page = $response->json();
```

Briar Rose automatically sends the `Prefer: transient` header required by NetSuite. The second argument contains URL query parameters such as `limit` and `offset`. SuiteQL values must be included in the query string, so validate or allow-list any dynamic input before constructing a query.

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

### Requesting a RESTlet
You can call any RESTlet deployed to your account via the script method.

```php
BriarRose::restlet()
    ->script(0000, 1)
    ->get(['listId' => 0000]);
```

### Requesting a RESTlet via the script ID + deploy ID
If you have a single RESTlet script deployed, you can set environment variables to call it directly:
> Note: This is the preferred method for production use if you have a single RESTlet script deployed.

Set the deploy ID in the environment:
```env
NETSUITE_RESTLET_SCRIPT_ID=... // Script ID of the deployed RESTlet
NETSUITE_RESTLET_DEPLOY_ID=1   // Booleans are 1 or 0
```

```php
$response = BriarRose::restlet('000')
    ->request('GET', ['listId' => 000]);
```

### Requesting a RESTlet via the base URL
If you have a RESTlet base URL set, you can call it like this:

Set the RESTlet base URL in the environment:
```env
NETSUITE_RESTLET_BASE_URL=... // Be sure to set the Script ID of the RESTlet in the URL.
```

```php
$response = BriarRose::restlet()
    ->request('GET', ['listId' => 000]);

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

- SuiteQL pagination and incremental sync helpers
- Better query/filter helpers
- Additional first-class endpoints (as needed)

---

## License

MIT
