# Quince API

**Admin API endpoints for PrestaShop 9.2 data the native API does not expose.**

Quince API adds resources to the PrestaShop Admin API for data that neither the
native Admin API nor the Webservice hands out. Today these are merchandise returns
(RMA): an external system can read them with their line items and prices, and
change their state the same way the back office does. The module has no settings
screen: its only users are external systems, over OAuth2.

> Quince API is a free, open-source module (OSL-3.0). There is nothing to buy.
> If it saves you time, you can support development, see
> [Supporting the project](#supporting-the-project).

---

## Features

- **Returns with their lines.** A list of merchandise returns and a single return,
  each with its line items: product, combination, reference, EAN, ordered and
  returned quantity, and unit prices with and without tax taken from the order.
- **Polling for changes.** Filter by `updatedSince` (inclusive), by order and by
  state, with paging up to 200 rows. Rows are sorted by update date and id, so a
  client can resume from the last row it saw.
- **State changes like the back office.** `PATCH` with a new `stateId` goes through
  the PrestaShop core command: the customer gets the standard email in the language
  of the order's shop, the native hooks run, and setting the same state again sends
  no second email. Only `stateId` is writable.
- **Return states.** The list of states a return can move to, with names in the
  shop's language and the flag that marks a state as cancelling the return.
- **Capability discovery.** `/quince-api/info` returns the module version and its
  resources, so a client can check what a shop supports.
- **Own OAuth2 scopes.** Reading and writing are separate scopes, granted to an API
  client like any native scope.
- **Multistore aware.** Every request names its shop with `shopId`; a return from
  another shop answers 404.

The full contract, with request and response examples, is in [API.md](API.md).

## Requirements

| Requirement | Version |
|-------------|---------|
| PrestaShop  | 9.2 (tested on 9.2.0) |
| PHP         | 8.4 (tested), 8.1 or newer required |
| Quince Engine | not required |

The module works on its own, in any PrestaShop 9.2 shop, with or without the other
Quince modules. It needs the PrestaShop Admin API, which is part of the core.

## Installation

The release ZIP already contains everything the module needs. Composer and the
command line are not required.

**From the back office**

1. Download the latest **`quince_api.zip`** from the
   [**Releases**](https://github.com/quinceproject/quince_api/releases) page.
2. In the back office go to **Modules → Module Manager → Upload a module** and
   choose the ZIP. PrestaShop installs it.

**By copying the files to the server**

1. Unpack the release ZIP. It contains a `quince_api` folder.
2. Upload that folder to the `modules/` directory of the shop, for example by FTP.
3. In **Modules → Module Manager** find Quince API and click **Install**.

**From Quince Engine**

With Quince Engine installed, **Quince project → Quince modules** installs and updates
this module from the Quince release list, without downloading anything by hand.

**With Composer**

A PrestaShop shop ships without a `composer.json` of its own, so Quince packages are
installed from a separate folder next to the shop. The one-time setup of that folder is
described in the [Quince Engine README](https://github.com/quinceproject/quince_engine#installation).
Then, from that folder:

```sh
composer require "quinceproject/api:*"
php ../bin/console prestashop:module install quince_api
```

> ⚠️ **Do not** use the green **Code → Download ZIP** button on this page. That
> archive contains the source only and is **missing the `vendor/` folder**, so the
> module will not run. Always install from a
> [**Release**](https://github.com/quinceproject/quince_api/releases).

## Configuration

The module has no settings. To let an external system in:

1. In the back office go to **Advanced Parameters → Admin API** and create an API
   client, or use the console:

   ```bash
   php bin/console prestashop:api-client create my-tool \
     --name="My tool" \
     --scopes=quince_api_order_returns_read,quince_api_order_returns_write
   ```

2. Give the client only the scopes it needs:

   | Scope | Grants |
   |-------|--------|
   | `quince_api_order_returns_read` | reading returns, return states and `/info` |
   | `quince_api_order_returns_write` | changing the state of a return |

3. The external system gets a token from `/admin-api/access_token` with
   `client_credentials` and calls the endpoints below.

## Endpoints

All paths are under `/admin-api/` and require `shopId` in the query.

| Method | Path | Scope |
|--------|------|-------|
| `GET` | `/quince-api/info` | read |
| `GET` | `/quince-api/order-return-states` | read |
| `GET` | `/quince-api/order-returns` | read |
| `GET` | `/quince-api/order-returns/{id}` | read |
| `PATCH` | `/quince-api/order-returns/{id}` | write |

## Known limitations

- **Removing a return line does not change `dateUpd`.** The native back office
  deletes a line without updating the parent return, so polling by `updatedSince`
  does not see that change unless the state changes too. This is a PrestaShop core
  behaviour.
- **In developer mode an invalid `stateId` shows `500` in the error body** while the
  real HTTP status is 422. Rely on the HTTP status code.
- **The module does not create returns, add or remove lines, issue credit slips or
  record refunds.**

## Architecture

- **API Platform resources** in `src/ApiPlatform/Resources`, picked up by the
  PrestaShop Admin API, with their own providers and a processor registered in
  `config/services.yml`.
- **`Repository`** reads returns, lines and prices straight from the core tables.
- **`OrderReturnStateProcessor`** changes the state through
  `UpdateOrderReturnStateCommand`, taking the id from the address and the shop from
  the query, never from the body.
- The module stores no settings and creates no tables of its own.

## Development / building from source

This repository tracks **source only**. The `vendor/` directory is git-ignored, so a
`git clone` is **not** directly installable. **Start with `composer install`**: the
development environment mounts module directories straight from disk, and without
that step a fresh clone gives a broken shop.

```bash
git clone https://github.com/quinceproject/quince_api.git
cd quince_api
composer install
```

The folder must be called `quince_api`, the same as the module. A clone of this
repository already has that name.

To build an installable archive, zip the `quince_api/` folder **with its `vendor/`
directory included**, leaving out `.git/` and `.gitignore`.

## License

Code: [Open Software License 3.0 (OSL-3.0)](LICENSE). It is a reciprocal licence:
whoever modifies and distributes this code has to publish the changes under the same
licence and keep the original copyright and author notices.

**The name "Quince" is not covered by the code licence.** A fork may use the code; it
may not use the name.

© 2026 Marcin Gajewski

## Supporting the project

Quince modules are free downloads and there is nothing to buy. If the module is useful
to you, you can support its development by buying me a coffee:

**[buymeacoffee.com/marcingajewski](https://buymeacoffee.com/marcingajewski)**
