# SyncEngine — Shopware 6 Connector

Shopware 6 plugin that connects a Shopware instance to [SyncEngine](https://syncengine.io) and dispatches mapped automation endpoints when entities change.

## Features

- **SyncEngine API connection** via host + token (+ optional custom auth header)
- **Trigger discovery + mapping** from SyncEngine automations/connections to Shopware trigger blueprints
- **Entity event dispatching** for Product, Order, and Customer create/update/delete operations
- **Shopware Flow Builder integration** with action `Trigger SyncEngine Endpoint` (endpoint picker, optional trigger-event override, optional custom JSON payload)
- **Connector action routes** for status, cache refresh, trigger-map inspection, and Flow endpoint listing
- **Shopware Administration tools** to inspect and refresh the current trigger map
- **Refresh trust + throttling** to protect refresh endpoint from request storms
- **Local development support** (localhost/DDEV/docker host TLS handling)

---

## Requirements

- Shopware 6 (plugin-compatible version)
- Reachable SyncEngine installation
- SyncEngine API token with read access to:
	- automations
	- connections
	- endpoints (for endpoint picker/dispatch behavior)
- For automatic trigger discovery, corresponding SyncEngine module blueprints must exist (for example `SyncEngine/ShopwareAdminV1:*` trigger blueprints)

---

## Installation

1. Install/activate the plugin in Shopware.
2. Go to **Settings -> Extensions -> SyncEngine** (plugin config card).
3. Fill in:
	 - **SyncEngine Host** (for example `https://syncengine.example.com`)
	 - **SyncEngine Token**
	 - **Auth Header** (optional; defaults to `Authorization: Bearer <token>` when empty)
4. Ensure **Enable trigger dispatching** is on.

---

## Configuration

The plugin uses Shopware SystemConfig keys under `SyncEngineConnector.config.*`.

### User-managed settings

| Setting | Key | Description |
|---|---|---|
| SyncEngine Host | `apiHost` | Base URL of SyncEngine |
| SyncEngine Token | `apiToken` | API token used by the connector |
| Auth Header (optional) | `apiAuthHeader` | Custom auth header name |
| Enable trigger dispatching | `triggersEnabled` | Global dispatch on/off switch |

### Internal cache/runtime keys

- Trigger endpoint map cache and timestamp
- Refresh throttle state (trusted/untrusted buckets)
- Known trusted connection references
- SyncEngine API status/endpoints cache

When user-managed settings change, caches are invalidated automatically.

---

## Trigger Events

The plugin subscribes to Shopware DAL written events and maps operations to SyncEngine triggers:

| Entity | Insert | Update | Delete |
|---|---|---|---|
| Product | `new_product` | `updated_product` | `deleted_product` |
| Order | `new_order` | `updated_order` | `deleted_order` |
| Customer | `new_customer` | `updated_customer` | `deleted_customer` |

Dispatch event names in payload:

- `shopware_new_product`
- `shopware_updated_product`
- `shopware_deleted_product`
- `shopware_new_order`
- `shopware_updated_order`
- `shopware_deleted_order`
- `shopware_new_customer`
- `shopware_updated_customer`
- `shopware_deleted_customer`

---

## Trigger Discovery & Mapping

Trigger map construction flow:

1. Load automations from SyncEngine
2. Load connections from SyncEngine
3. Filter to local Shopware connection(s) by normalized host match
4. Match automation blueprint class to trigger key
5. Cache resulting trigger -> endpoints map for 5 minutes

Supported blueprint class mapping includes:

- `SyncEngine/ShopwareAdminV1:NewProduct`
- `SyncEngine/ShopwareAdminV1:UpdatedProduct`
- `SyncEngine/ShopwareAdminV1:DeletedProduct`
- `SyncEngine/ShopwareAdminV1:NewOrder`
- `SyncEngine/ShopwareAdminV1:UpdatedOrder`
- `SyncEngine/ShopwareAdminV1:DeletedOrder`
- `SyncEngine/ShopwareAdminV1:NewCustomer`
- `SyncEngine/ShopwareAdminV1:UpdatedCustomer`
- `SyncEngine/ShopwareAdminV1:DeletedCustomer`

---

## Payload Shape

Outgoing trigger payload uses JSON:API-like envelope for entity data:

```json
{
	"id": "<entity-id>",
	"event": "shopware_updated_product",
	"data": {
		"data": {
			"id": "<entity-id>",
			"type": "product",
			"attributes": { ... },
			"relationships": { ... },
			"meta": null
		},
		"included": [],
		"_handler": "syncengine"
	},
	"request": {
		"id": "<entity-id>"
	}
}
```

For delete events, `attributes`/`relationships` are empty but envelope remains stable.

### Flow Builder payload behavior

The Flow Builder action `Trigger SyncEngine Endpoint` dispatches to a selected SyncEngine endpoint.

Config fields in the modal:

- **Endpoint** (required): selected from `/api/_action/syncengine/endpoints`
- **Trigger event name (optional)**: overrides event name sent to SyncEngine
- **Custom payload JSON (optional)**: validated JSON merged as custom payload input

When trigger event name is empty, the plugin derives a default event name from the Flow/action context.

The action also forwards selected entity context when available (for example order/customer/product references), and sanitizes payload content for safe transport.

---

## Connector API Endpoints

The plugin exposes these Shopware API action routes:

| Method | Route | Description |
|---|---|---|
| `GET` | `/api/_action/syncengine/status` | Fast local status check (`online`) |
| `POST` | `/api/_action/syncengine/refresh` | Clears trigger map cache (throttled) |
| `GET` | `/api/_action/syncengine/trigger-map` | Returns current cached/rebuilt trigger map |
| `GET` | `/api/_action/syncengine/endpoints` | Returns endpoint options for Flow Builder endpoint picker |

### Status response

```json
{
	"success": true,
	"status": "online"
}
```

### Refresh response (success)

```json
{
	"success": true,
	"refreshed": true,
	"trusted": false,
	"timestamp": 1712345678
}
```

### Refresh response (throttled)

```json
{
	"success": true,
	"refreshed": false,
	"reason": "throttled",
	"trusted": false
}
```

### Trigger map response

```json
{
	"success": true,
	"updatedAt": 1712345678,
	"triggerMap": {
		"new_product": ["endpoint-a"],
		"updated_product": [],
		"deleted_product": []
	}
}
```

### Endpoints response

```json
{
	"success": true,
	"endpoints": [
		{
			"value": "endpoint-a",
			"label": "endpoint-a"
		}
	]
}
```

---

## Refresh Trust & Throttling

Refresh requests can provide `X-SyncEngine-Connection` header.

The plugin tracks trusted connection refs discovered during trigger-map resolution and applies different throttle windows:

| Request type | Window |
|---|---|
| Trusted ref | 1 second |
| Untrusted/anonymous | 10 seconds |

This keeps endpoint responsive while preventing invalidation storms.

---

## Shopware Administration UI

The plugin registers a settings module entry:

- **Settings -> Plugins -> SyncEngine Trigger Map**
- **Flow Builder action -> Trigger SyncEngine Endpoint**

Capabilities:

- View all trigger keys and resolved endpoint mappings
- Inspect endpoint counts
- Manual refresh action (calls connector refresh endpoint)
- In Flow Builder: select endpoint, optionally override trigger event name, optionally provide custom JSON payload

---

## Local Development Notes

The internal SyncEngine API client disables TLS peer/host verification for local development hosts:

- `localhost`, `127.0.0.1`, `::1`
- `host.docker.internal`, `gateway.docker.internal`
- `*.ddev.site`, `*.localhost`, `*.local`, `*.test`

This improves local DDEV/docker interoperability.

---

## Development

### Administration build

After changing files under `src/Resources/app/administration/`, rebuild administration assets in Shopware.

Typical workflow:

1. Rebuild administration bundle
2. Clear cache if needed
3. Reload admin and test trigger map module

### Relevant source areas

- API routes: `src/Controller/SyncEngineController.php`
- Trigger map logic: `src/Service/TriggerService.php`
- Payload shaping: `src/Service/ShopwarePayloadService.php`
- Dispatch: `src/Service/EndpointDispatcherService.php`
- Event subscription: `src/Subscriber/EntityTriggerSubscriber.php`
- Admin module: `src/Resources/app/administration/src/module/syncengine/`

---

## Troubleshooting

### Trigger map is empty

Check:

1. SyncEngine Host/Token are configured
2. SyncEngine is reachable from Shopware runtime
3. Automations use Shopware trigger blueprints
4. Local host normalization matches the configured Shopware connection host in SyncEngine

### Refresh endpoint responds `throttled`

Expected behavior under rapid repeated calls. Wait for the throttle window (1s trusted, 10s untrusted).

### Events are not dispatched

Check:

1. `triggersEnabled` is true
2. Trigger map contains endpoints for the emitted trigger
3. Endpoint execution errors in SyncEngine response payload/result logs

### SSL/local dev connectivity issues

Ensure your host matches supported local-dev patterns listed above.
