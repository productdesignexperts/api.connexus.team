# Connexus Backend API (Private/Proprietary)

> **IMPORTANT**: This is a **PRIVATE, PROPRIETARY** backend repository. It is NOT the public client library.

## Repository Distinction

| Repository | Purpose | Visibility |
|------------|---------|------------|
| **`api.connexus.team`** (THIS REPO) | Backend PHP API server | **PRIVATE** - Proprietary |
| `connexus_public_api` | Client JavaScript library for websites | **PUBLIC** - Open source |

---

## Project Origin

This project was initiated on December 10, 2025.

### Initial Requirements (Preserved)

> "In this directory and inside public_html we are going to create an API backend that works with several other directories on this server... the idea is that we will make a symbolic link into that directory so the ococsite can use this API to call a javascript file that will read the document object model DOM and the user will tell the JS file what styles are in the HTML that are to be used for the dynamic data retrieved from the API, so they will create a html site, with CSS classes and styles and IDs, they will add this JS file and what will happen is that JS file will empty the DOM objects and classes and refill them with the database making an easy and quick way to add content management to their website."

---

## System Architecture

### The Two-Repository Pattern

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           USER'S WEBSITE                                     │
│                                                                              │
│  <script src="https://ococsite.connexus.team/connexus_api/src/connexus-api.js">
│                                                                              │
│  ConnexusAPI.init({                                                          │
│    apiKey: 'their-api-key',                                                  │
│    events: {                                                                 │
│      container: '.events-list',                                              │
│      template: '.event-item',                                                │
│      mapping: { '.title': 'title', '.date': 'date' }                        │
│    }                                                                         │
│  });                                                                         │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ fetch()
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                    https://api.connexus.team/v1/events                       │
│                                                                              │
│                         THIS REPOSITORY (PRIVATE)                            │
│                                                                              │
│  - Validates API key                                                         │
│  - Queries MongoDB                                                           │
│  - Returns JSON data                                                         │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              MongoDB                                         │
│                          Database: ococ_portal                               │
└─────────────────────────────────────────────────────────────────────────────┘
```

### All Components

| Component | Directory | URL | Repository |
|-----------|-----------|-----|------------|
| Member Dashboard | `/var/www/myococ.connexus.team/public_html/dashboard` | https://myococ.connexus.team/dashboard | `productdesignexperts/myococ` |
| Admin Panel | `/var/www/myococ.connexus.team/public_html/admin` | https://ococsite.connexus.team/admin | `productdesignexperts/myococ` |
| Public Website | `/var/www/ococsite.connexus.team/public_html` | https://ococsite.connexus.team | `productdesignexperts/ococ_site` |
| **Client JS Library** | `/var/www/ococsite.connexus.team/connexus_api` | https://ococsite.connexus.team/connexus_api/src/connexus-api.js | `productdesignexperts/connexus_public_api` **(PUBLIC)** |
| **Backend API** | `/var/www/api.connexus.team/public_html` | https://api.connexus.team | `productdesignexperts/api.connexus.team` **(PRIVATE - THIS REPO)** |

---

## How Users Integrate

1. User creates HTML with example/placeholder content and CSS classes
2. User includes the **public** JS library:
   ```html
   <script src="https://ococsite.connexus.team/connexus_api/src/connexus-api.js"></script>
   ```
3. User initializes with their API key and class mappings:
   ```javascript
   ConnexusAPI.init({
     apiKey: 'their-api-key',
     events: {
       container: '.my-events',
       template: '.event-card',
       mapping: {
         '.event-title': 'title',
         '.event-date': 'date'
       }
     }
   });
   ```
4. The JS library calls **this backend** (`https://api.connexus.team/v1/...`)
5. This backend validates the API key, queries MongoDB, returns JSON
6. The JS library replaces the example DOM content with real data

---

## Technical Stack

- **Language**: PHP 8.3
- **Database**: MongoDB (database: `ococ_portal`)
- **API Style**: RESTful with versioned endpoints (`/v1/`)

### Current Configuration

- **Authentication**: API keys (currently accepts any non-empty key during development)
- **CORS**: Open (required for cross-origin browser requests)
- **Rate Limiting**: None (development stage)
- **Caching**: Disabled (development stage)
- **Tenancy**: Single-tenant (multi-tenant planned for future)

---

## API Endpoints

Base URL: `https://api.connexus.team`

### Root
- `GET /` - API information and available endpoints

### Version 1 (`/v1/`)

| Endpoint | Description |
|----------|-------------|
| `GET /v1/ping` | Health check |
| `GET /v1/events` | List all events |
| `GET /v1/events/:id` | Single event |
| `GET /v1/members` | Member directory (public profiles) |
| `GET /v1/members/:id` | Single member |
| `GET /v1/members?q=search` | Search members |
| `GET /v1/businesses` | Business listings |
| `GET /v1/businesses/:id` | Single business |
| `GET /v1/businesses?q=search&category=X` | Search/filter businesses |
| `GET /v1/discounts` | Public member discounts |
| `GET /v1/discounts/:id` | Single discount |
| `GET /v1/announcements` | Public announcements |
| `GET /v1/announcements/:id` | Single announcement |

### Pagination

All list endpoints support:
- `?limit=N` (default: 20, max: 100)
- `?offset=N` (default: 0)

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

---

## Database Collections

Shared with the dashboard/admin system (`ococ_portal` database):

| Collection | Purpose |
|------------|---------|
| `users` | Member profiles and directory |
| `events` | Calendar events |
| `discounts` | Member-offered discounts |
| `public_comments` | Public announcements |
| `groups` | Member groups |
| `group_announcements` | Group-specific announcements |
| `group_events` | Group-specific events |
| `group_documents` | Group file sharing |
| `api_keys` | API key management (planned) |

---

## File Structure

```
/var/www/api.connexus.team/
├── CLAUDE.md              # This file - AI context
├── README.md              # Developer documentation
├── composer.json          # PHP dependencies
├── composer.lock
├── .gitignore
├── public_html/           # Web root (Apache serves this)
│   ├── index.php          # Main router
│   ├── .htaccess          # URL rewriting, no-cache headers
│   └── v1/                # Version 1 endpoints
│       ├── ping.php
│       ├── events.php
│       ├── members.php
│       ├── businesses.php
│       ├── discounts.php
│       └── announcements.php
├── src/                   # Shared PHP code
│   ├── config.php         # Configuration constants
│   ├── db.php             # MongoDB connection
│   └── helpers.php        # Utility functions (CORS, JSON, pagination)
└── vendor/                # Composer dependencies (MongoDB library)
```

---

## Development Guidelines

1. **Keep it simple**: Minimal changes, no over-engineering
2. **No refactoring** unless explicitly requested
3. **Show changes before implementing**: Discuss approach first
4. **Git commits**: One-line summaries, no Claude attribution
5. **PHP style**: Match existing dashboard codebase conventions
6. **Security**: This is a read-only API; no write operations exposed

---

## Related Projects

### Private Repositories
- `/var/www/myococ.connexus.team/public_html/` - Dashboard & Admin (`myococ`)
- `/var/www/api.connexus.team/` - **This backend** (`api.connexus.team`)

### Public Repository
- `/var/www/ococsite.connexus.team/connexus_api/` - Client JS library (`connexus_public_api`)

### Reference Files
When working on this API, these files are relevant:
- `/var/www/myococ.connexus.team/public_html/api.php` - Existing internal API patterns
- `/var/www/myococ.connexus.team/public_html/config.php` - Database config & collection constants
- `/var/www/myococ.connexus.team/public_html/db.php` - MongoDB connection pattern
- `/var/www/ococsite.connexus.team/connexus_api/src/connexus-api.js` - Client library (what calls this API)

---

## Owner

- **User**: vince (has permissions on all directories and GitHub repositories)
- **GitHub Organization**: github.com/productdesignexperts/
