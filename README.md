# briar-rose

**NetSuite RESTlet + SuiteTalk REST client for Laravel** using OAuth 1.0a Token-Based Auth (TBA) with HMAC-SHA256 signing.

## Install

```bash
composer require searsandrew/briar-rose
```

## REST
* List (one page)
`$page = BriarRose::rest()->record('inventoryItem')->list(['limit' => 1000, 'offset' => 0])->json();`

* List all items (stream)
```
foreach (BriarRose::rest()->record('inventoryItem')->listItemsAll(['limit' => 1000]) as $item) {
    $item is usually { id, links... } not full expanded record
}
```

* Filtered list
`$page = BriarRose::rest()->record('inventoryItem')->where('isinactive IS false')->json();`

* Fields on GET
`$item = BriarRose::rest()->record('inventoryItem')->getFields(2336, ['id', 'itemId'])->json();`