# Connexus Backend API

**Private/Proprietary** - This is the backend API server for the Connexus platform.

## Overview

This PHP API serves JSON data to the [Connexus Public API Client](https://github.com/productdesignexperts/connexus_public_api) JavaScript library. It queries a MongoDB database and returns events, members, business listings, and other chamber of commerce data.

## Related Repository

The **public client library** that websites use to consume this API is at:
- Repository: [connexus_public_api](https://github.com/productdesignexperts/connexus_public_api)
- Location: `/var/www/ococsite.connexus.team/connexus_api`
- Script URL: `https://ococsite.connexus.team/connexus_api/src/connexus-api.js`

## Base URL

```
https://api.connexus.team
```

## Endpoints

All data endpoints require an API key via `Authorization: Bearer <key>` header or `?api_key=<key>` query parameter.

| Endpoint | Description |
|----------|-------------|
| `GET /` | API info |
| `GET /v1/ping` | Health check |
| `GET /v1/events` | List events |
| `GET /v1/events/:id` | Single event |
| `GET /v1/members` | Member directory |
| `GET /v1/members/:id` | Single member |
| `GET /v1/businesses` | Business listings |
| `GET /v1/businesses/:id` | Single business |
| `GET /v1/discounts` | Member discounts |
| `GET /v1/discounts/:id` | Single discount |
| `GET /v1/announcements` | Announcements |
| `GET /v1/announcements/:id` | Single announcement |

### Pagination

```
?limit=20&offset=0
```

### Response Format

```json
{
  "data": [...],
  "meta": {
    "total": 100,
    "limit": 20,
    "offset": 0
  }
}
```

## Installation

```bash
composer install --ignore-platform-req=ext-mongodb
```

## Configuration

Environment variables (or edit `src/config.php`):
- `CONNEXUS_MONGO_URI` - MongoDB connection string (default: `mongodb://localhost:27017`)
- `CONNEXUS_DB_NAME` - Database name (default: `ococ_portal`)

## License

Proprietary - All rights reserved.
