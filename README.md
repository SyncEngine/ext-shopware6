# SyncEngine Shopware 6 Connector

This plugin dispatches Shopware Product, Order, and Customer events to SyncEngine endpoints.

## Features

- Connect to SyncEngine API with host + token
- Fetch endpoint and automation metadata
- Auto-map SyncEngine automations to Shopware triggers using ShopwareAdminV1 trigger blueprints
- Dispatch mapped endpoints on product/order/customer create, update, and delete events
- Refresh trigger-endpoint map via connector endpoint

## Connector routes

- `GET /api/_action/syncengine/status`
- `POST /api/_action/syncengine/refresh`
