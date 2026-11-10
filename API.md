# Quince API: endpoint reference

Module: `quince_api` 0.1.0.

This document is the contract for a system that reads merchandise returns (RMA) from
PrestaShop 9.2 and changes their status. **The module assumes nothing about the tool
on the other side.** Every shape below was checked against a running shop.

The module has no back office screen. The merchant handles returns where they
already do, on the native **Merchandise Returns** screen. The only consumers of this
API are external systems, over OAuth2.

---

## 1. Authentication

PrestaShop Admin API, OAuth2, `client_credentials`. The shop creates the client in
the back office (**Advanced Parameters → Admin API**) or from the console:

```
php bin/console prestashop:api-client create my-tool \
  --name="My tool" \
  --scopes=quince_api_order_returns_read,quince_api_order_returns_write
```

Token:

```http
POST /admin-api/access_token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
&client_id=my-tool
&client_secret=…
&scope=quince_api_order_returns_read quince_api_order_returns_write
```

Module scopes:

| Scope | Grants |
|---|---|
| `quince_api_order_returns_read` | reading returns, return states and `/info` |
| `quince_api_order_returns_write` | changing the state of a return |

**Multistore.** `shopId` is required in EVERY request (query parameter), also where
the resource itself is not per shop (`/info`, `/order-return-states`). The platform
enforces this with multistore on, before the request reaches this module. Without
`shopId` the answer is `400` before anything below runs.

---

## 2. `GET /quince-api/info`

Capability discovery: the module version and its resources, so a client can check
what a given shop has before relying on it (different shops, different versions).

```http
GET /admin-api/quince-api/info?shopId=1
```

```json
{
  "module": "quince_api",
  "version": "0.1.0",
  "resources": [
    {
      "resource": "/quince-api/order-returns",
      "scopes": ["quince_api_order_returns_read", "quince_api_order_returns_write"]
    },
    {
      "resource": "/quince-api/order-return-states",
      "scopes": ["quince_api_order_returns_read"]
    }
  ]
}
```

---

## 3. `GET /quince-api/order-return-states`

The states a return can move to. They are not tied to a shop: `shopId` only selects
the language of the names.

```http
GET /admin-api/quince-api/order-return-states?shopId=1
```

```json
[
  { "id": 1, "name": "Waiting for confirmation", "color": "#4169E1", "isCancellingReturn": false },
  { "id": 2, "name": "Waiting for package", "color": "#8A2BE2", "isCancellingReturn": false },
  { "id": 3, "name": "Package received", "color": "#32CD32", "isCancellingReturn": false },
  { "id": 4, "name": "Return denied", "color": "#DC143C", "isCancellingReturn": true },
  { "id": 5, "name": "Return completed", "color": "#108510", "isCancellingReturn": false }
]
```

The list and its order depend on the installation: the merchant can add states of
their own in the back office.

---

## 4. Merchandise returns

### 4.1 List

```http
GET /admin-api/quince-api/order-returns?shopId=1&updatedSince=2026-10-28T00:00:00%2B01:00&limit=50&page=1
```

| Parameter | Meaning |
|---|---|
| `shopId` | required |
| `updatedSince` | filter on `dateUpd`, **inclusive** (`>=`) |
| `orderId` | one order |
| `state` | one state (`id` from section 3) |
| `limit` | 50 by default, 200 at most |
| `page` | 1 by default |

Sorted by `dateUpd` ascending, then by `id`, so the order is stable. **The answer is
a bare array, with no separate server timestamp.** For the next poll, take the
`dateUpd` of the last row of the page. The filter is inclusive, so that row comes
again: import idempotently by `id`.

### 4.2 Detail

```http
GET /admin-api/quince-api/order-returns/{id}?shopId=1
```

### 4.3 Shape of a return

```json
{
  "id": 2,
  "reference": "#RE000002",
  "orderId": 50,
  "orderReference": "YTISRPUCG",
  "customerId": 302,
  "customerEmail": "customer@example.com",
  "shopId": 1,
  "stateId": 2,
  "stateName": "Waiting for package",
  "isCancellingReturn": false,
  "question": "The reason given by the customer.",
  "dateAdd": "2026-10-27T15:08:07+01:00",
  "dateUpd": "2026-10-28T13:37:36+01:00",
  "items": [
    {
      "idOrderDetail": 54,
      "productId": 11,
      "productAttributeId": 26,
      "reference": "demo_17",
      "ean": "",
      "name": "Hummingbird cushion (Color: White)",
      "quantityOrdered": 1,
      "quantityReturned": 1,
      "idCustomization": 0,
      "cancelled": false,
      "unitPriceNet": 18.9,
      "unitPriceGross": 23.247
    }
  ]
}
```

| Field | Notes |
|---|---|
| `reference` | the shop's `PS_RETURN_PREFIX` plus the number on six digits. The prefix belongs to the merchant: read it from here, do not guess it |
| `customerEmail` | **empty when the customer account was deleted.** `customerId` stays, and the return does not disappear from the list |
| `unitPriceNet` / `unitPriceGross` | the price from the ORDER line (`order_detail`). `order_return_detail` itself carries no price |
| `ean` | an empty string when the product has no EAN in the catalogue |

### 4.4 Known gap: removing a return line does not touch `dateUpd`

The native back office lets staff remove a single line from an existing return (not
change its quantity: the core has no such operation). It does so with a plain
`DELETE FROM order_return_detail`, without updating the parent return's `date_upd`.
**If staff remove a line without changing the state, polling by `updatedSince` will
not see that change.** This is a PrestaShop core limitation. The module exposes no
endpoint for removing lines, so it has no path of its own to fix it.

---

## 5. `PATCH /quince-api/order-returns/{id}`: change the state

```http
PATCH /admin-api/quince-api/order-returns/{id}?shopId=1
Content-Type: application/merge-patch+json

{ "stateId": 3 }
```

**The only writable field is `stateId`.** `id` comes from the address and `shopId`
from the query; anything else in the body (for example a forged `id` or `shopId`) is
ignored.

The change goes only through the core command `UpdateOrderReturnStateCommand`, the
same code as the button in the back office:

- the customer gets the standard PrestaShop email, **in the language and context of
  that order's shop**, not the context of the API request or the default shop;
- the native hooks `actionObjectOrderReturnUpdateBefore/After` run;
- **idempotent**: setting the SAME state again sends no second email (the core
  compares the previous and the new state before sending). `dateUpd` is updated all
  the same, because `ObjectModel::update()` already does that.

Answer: the resource in the shape from section 4.3, after the save.

---

## 6. Errors

### 404: the return does not exist (or belongs to another shop)

```http
GET /admin-api/quince-api/order-returns/99999?shopId=1
```

```json
{
  "type": "https://tools.ietf.org/html/rfc2616#section-10",
  "title": "An error occurred",
  "status": 404,
  "detail": "Not Found"
}
```

### 422: invalid `stateId`

```http
PATCH /admin-api/quince-api/order-returns/2?shopId=1
{ "stateId": 99999 }
```

```json
{
  "type": "https://tools.ietf.org/html/rfc2616#section-10",
  "title": "An error occurred",
  "status": 500,
  "detail": "OrderReturnState #99999 was not found"
}
```

**Note:** with PrestaShop developer mode on, the `status` field in the body shows
`500`, while the **real HTTP status code (the response header) is 422**. The error
renderer in developer mode shows the exception's status from before the module maps
it. **Rely on the HTTP status code, not on the `status` field in the body.** The 404
above has no such mismatch. Whether a shop without developer mode renders the body
differently was not checked.

Both examples are shortened: in developer mode the real answer also carries `class`
(the full exception name) and `trace` (the full stack trace).

---

## 7. What the module does not do

- **It does not create returns or add or remove lines.** It only reads existing
  returns and changes their state.
- **It does not issue credit slips or record refunds.**
- **It has no back office screen of its own.** API clients are set up on the native
  PrestaShop screen.
