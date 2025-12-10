# Connexus Platform Architecture

> **Document Purpose**: This is the authoritative reference for understanding the Connexus platform architecture. Read this document completely before making any changes to the system.

---

## Executive Summary

Connexus is a chamber of commerce platform that allows organizations to manage members, events, business listings, and communications. The platform consists of multiple interconnected components across several repositories and servers.

The key feature is a **DOM template replacement system**: external websites can include a JavaScript library that fetches data from our API and dynamically replaces placeholder HTML content with real database content. This allows any website to easily add CMS-like functionality without backend changes.

---

## The Big Picture

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                                                                     │
│                              EXTERNAL USER'S WEBSITE                                │
│                              (e.g., localchamber.com)                               │
│                                                                                     │
│   ┌─────────────────────────────────────────────────────────────────────────────┐   │
│   │ <div class="events-container">                                              │   │
│   │   <div class="event-card">           ← Example/placeholder content          │   │
│   │     <h3 class="title">Sample Event</h3>                                     │   │
│   │     <p class="date">January 1, 2025</p>                                     │   │
│   │   </div>                                                                    │   │
│   │ </div>                                                                      │   │
│   │                                                                             │   │
│   │ <script src="https://ococsite.connexus.team/connexus_api/src/connexus-api.js">  │
│   │                                                                             │   │
│   │ ConnexusAPI.init({                                                          │   │
│   │   apiKey: 'their-api-key',                                                  │   │
│   │   events: {                                                                 │   │
│   │     container: '.events-container',                                         │   │
│   │     template: '.event-card',                                                │   │
│   │     mapping: { '.title': 'title', '.date': 'date' }                         │   │
│   │   }                                                                         │   │
│   │ });                                                                         │   │
│   └─────────────────────────────────────────────────────────────────────────────┘   │
│                                                                                     │
└─────────────────────────────────────────────────────────────────────────────────────┘
         │
         │ 1. Browser downloads JS from ococsite.connexus.team
         │ 2. JS executes and calls fetch() to api.connexus.team
         ▼
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                                                                     │
│                    PUBLIC CLIENT LIBRARY (connexus_public_api)                      │
│                                                                                     │
│   Repository: github.com/productdesignexperts/connexus_public_api                   │
│   Server Path: /var/www/ococsite.connexus.team/connexus_api                         │
│   Public URL: https://ococsite.connexus.team/connexus_api/src/connexus-api.js       │
│   Visibility: PUBLIC (open source)                                                  │
│                                                                                     │
│   Purpose:                                                                          │
│   - Vanilla JavaScript library users include in their websites                      │
│   - Handles API communication, DOM manipulation, template cloning                   │
│   - Maps CSS selectors to API data fields                                           │
│                                                                                     │
└─────────────────────────────────────────────────────────────────────────────────────┘
         │
         │ fetch('https://api.connexus.team/v1/events?api_key=xxx')
         ▼
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                                                                     │
│                      BACKEND API (api.connexus.team) ← THIS REPO                    │
│                                                                                     │
│   Repository: github.com/productdesignexperts/api.connexus.team                     │
│   Server Path: /var/www/api.connexus.team                                           │
│   Public URL: https://api.connexus.team                                             │
│   Visibility: PRIVATE (proprietary)                                                 │
│                                                                                     │
│   Purpose:                                                                          │
│   - PHP REST API that validates API keys and queries MongoDB                        │
│   - Returns JSON data for events, members, businesses, discounts, announcements     │
│   - Versioned endpoints (/v1/) for future compatibility                             │
│                                                                                     │
└─────────────────────────────────────────────────────────────────────────────────────┘
         │
         │ MongoDB queries
         ▼
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                                                                     │
│                              MONGODB DATABASE                                       │
│                              Database: ococ_portal                                  │
│                                                                                     │
│   Collections:                                                                      │
│   - users          (member profiles, directory)                                     │
│   - events         (calendar events)                                                │
│   - discounts      (member-offered discounts)                                       │
│   - public_comments (announcements)                                                 │
│   - groups         (member groups)                                                  │
│   - messages       (internal messaging)                                             │
│   - api_keys       (API key management - planned)                                   │
│                                                                                     │
└─────────────────────────────────────────────────────────────────────────────────────┘
         ▲
         │ Also accessed by dashboard/admin
         │
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                                                                     │
│                         DASHBOARD & ADMIN (myococ)                                  │
│                                                                                     │
│   Repository: github.com/productdesignexperts/myococ                                │
│   Server Path: /var/www/myococ.connexus.team/public_html                            │
│   Dashboard URL: https://myococ.connexus.team/dashboard                             │
│   Admin URL: https://ococsite.connexus.team/admin                                   │
│   Visibility: PRIVATE                                                               │
│                                                                                     │
│   Purpose:                                                                          │
│   - Member login, profile management, messaging                                     │
│   - Admin panel for approving events, managing members, etc.                        │
│   - Internal API (api.php) for dashboard operations                                 │
│                                                                                     │
└─────────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                                                                     │
│                         PUBLIC WEBSITE (ococ_site)                                  │
│                                                                                     │
│   Repository: github.com/productdesignexperts/ococ_site                             │
│   Server Path: /var/www/ococsite.connexus.team/public_html                          │
│   Public URL: https://ococsite.connexus.team                                        │
│   Visibility: PRIVATE (but serves public content)                                   │
│                                                                                     │
│   Purpose:                                                                          │
│   - Public-facing chamber of commerce website                                       │
│   - Hosts the connexus_api JS library via symlink                                   │
│                                                                                     │
│   Symlink:                                                                          │
│   /var/www/ococsite.connexus.team/public_html/connexus_api                          │
│       → /var/www/ococsite.connexus.team/connexus_api                                │
│                                                                                     │
└─────────────────────────────────────────────────────────────────────────────────────┘
```

---

## Repository Reference

| Repository | GitHub URL | Server Path | Public URL | Visibility |
|------------|------------|-------------|------------|------------|
| **api.connexus.team** | `productdesignexperts/api.connexus.team` | `/var/www/api.connexus.team` | https://api.connexus.team | **PRIVATE** |
| **connexus_public_api** | `productdesignexperts/connexus_public_api` | `/var/www/ococsite.connexus.team/connexus_api` | https://ococsite.connexus.team/connexus_api/src/connexus-api.js | **PUBLIC** |
| myococ | `productdesignexperts/myococ` | `/var/www/myococ.connexus.team/public_html` | https://myococ.connexus.team | PRIVATE |
| ococ_site | `productdesignexperts/ococ_site` | `/var/www/ococsite.connexus.team/public_html` | https://ococsite.connexus.team | PRIVATE |

---

## URL Reference

| URL | Purpose |
|-----|---------|
| https://api.connexus.team | Backend API (this repo) |
| https://api.connexus.team/v1/events | Events endpoint |
| https://api.connexus.team/v1/members | Members endpoint |
| https://api.connexus.team/v1/businesses | Businesses endpoint |
| https://api.connexus.team/v1/discounts | Discounts endpoint |
| https://api.connexus.team/v1/announcements | Announcements endpoint |
| https://ococsite.connexus.team/connexus_api/src/connexus-api.js | Public JS library |
| https://myococ.connexus.team/dashboard | Member dashboard |
| https://myococ.connexus.team/login.php | Member login |
| https://ococsite.connexus.team/admin | Admin panel |
| https://ococsite.connexus.team | Public website |

---

## Data Flow: How DOM Replacement Works

### Step-by-Step Process

1. **User Creates HTML Template**
   ```html
   <div class="events-list">
     <div class="event-item">
       <h3 class="event-title">Example Event Title</h3>
       <span class="event-date">January 1, 2025</span>
       <p class="event-desc">This is placeholder description text...</p>
     </div>
   </div>
   ```

2. **User Includes the JS Library**
   ```html
   <script src="https://ococsite.connexus.team/connexus_api/src/connexus-api.js"></script>
   ```

3. **User Configures the Library**
   ```javascript
   ConnexusAPI.init({
     apiKey: 'their-assigned-api-key',
     events: {
       container: '.events-list',      // Parent container
       template: '.event-item',        // Template to clone
       mapping: {                      // CSS selector → API field
         '.event-title': 'title',
         '.event-date': 'date',
         '.event-desc': 'description'
       }
     }
   });
   ```

4. **Library Fetches Data**
   - JS calls `https://api.connexus.team/v1/events?api_key=xxx`
   - Backend validates API key, queries MongoDB
   - Returns JSON: `{ "data": [...], "meta": {...} }`

5. **Library Replaces DOM Content**
   - Clones the template element for each data item
   - Maps API fields to DOM elements via configured selectors
   - Clears container and appends populated clones

6. **Result**: User's placeholder content is replaced with real database data

---

## API Response Format

All list endpoints return:

```json
{
  "data": [
    {
      "id": "abc123",
      "title": "Event Title",
      "date": "2025-01-15T00:00:00+00:00",
      ...
    }
  ],
  "meta": {
    "total": 100,
    "limit": 20,
    "offset": 0
  }
}
```

Single item endpoints return:

```json
{
  "data": {
    "id": "abc123",
    "title": "Event Title",
    ...
  }
}
```

Error responses:

```json
{
  "error": "Error message"
}
```

---

## Technical Details

### Backend API (This Repo)

- **Language**: PHP 8.3
- **Database**: MongoDB
- **Database Name**: `ococ_portal`
- **Web Server**: Apache with mod_rewrite
- **Dependencies**: `mongodb/mongodb` PHP library

### Current Development Status

- **Authentication**: API keys accepted (currently any non-empty key works during development)
- **CORS**: Open policy (allows any origin)
- **Rate Limiting**: None
- **Caching**: Disabled
- **Tenancy**: Single-tenant (one chamber)

### Future Considerations

- Proper API key validation against `api_keys` collection
- Rate limiting per API key
- Multi-tenant support (multiple chambers)
- Caching layer for production

---

## File Structure (This Repo)

```
/var/www/api.connexus.team/
├── ARCHITECTURE.md        # This document
├── CLAUDE.md              # AI context file
├── README.md              # Developer readme
├── composer.json
├── composer.lock
├── .gitignore
│
├── public_html/           # Apache document root
│   ├── index.php          # Main router
│   ├── .htaccess          # URL rewriting
│   └── v1/
│       ├── ping.php
│       ├── events.php
│       ├── members.php
│       ├── businesses.php
│       ├── discounts.php
│       └── announcements.php
│
├── src/
│   ├── config.php         # Constants and configuration
│   ├── db.php             # MongoDB connection
│   └── helpers.php        # CORS, JSON, pagination utilities
│
└── vendor/                # Composer dependencies
```

---

## Owner & Permissions

- **System User**: `vince`
- **Web Group**: `www-data`
- **GitHub Organization**: `productdesignexperts`

All repositories and server directories are owned/accessible by user `vince`.

---

## Server & Log Files Reference

### Apache Log Files

All logs are in `/var/log/apache2/`:

| Site | Access Log | Error Log |
|------|------------|-----------|
| **api.connexus.team** | `/var/log/apache2/api.connexus.team_access.log` | `/var/log/apache2/api.connexus.team_error.log` |
| **myococ.connexus.team** | `/var/log/apache2/myococ.connexus.team_access.log` | `/var/log/apache2/myococ.connexus.team_error.log` |
| **ococsite.connexus.team** | `/var/log/apache2/ococsite.connexus.team_access.log` | `/var/log/apache2/ococsite.connexus.team_error.log` |

### Checking Logs

```bash
# View recent errors for API
tail -50 /var/log/apache2/api.connexus.team_error.log

# View recent errors for dashboard/admin
tail -50 /var/log/apache2/myococ.connexus.team_error.log

# View recent errors for public site
tail -50 /var/log/apache2/ococsite.connexus.team_error.log

# Follow logs in real-time
tail -f /var/log/apache2/api.connexus.team_error.log
```

### Main Site URL

- **Main Platform**: https://connexus.team (redirects/aliases may apply)

---

## Complete URL Quick Reference

| URL | Purpose | Log Files |
|-----|---------|-----------|
| https://connexus.team | Main platform site | - |
| https://api.connexus.team | Backend API | `api.connexus.team_*.log` |
| https://api.connexus.team/v1/ping | API health check | `api.connexus.team_*.log` |
| https://api.connexus.team/v1/events | Events endpoint | `api.connexus.team_*.log` |
| https://api.connexus.team/v1/members | Members endpoint | `api.connexus.team_*.log` |
| https://api.connexus.team/v1/businesses | Businesses endpoint | `api.connexus.team_*.log` |
| https://api.connexus.team/v1/discounts | Discounts endpoint | `api.connexus.team_*.log` |
| https://api.connexus.team/v1/announcements | Announcements endpoint | `api.connexus.team_*.log` |
| https://ococsite.connexus.team | Public website | `ococsite.connexus.team_*.log` |
| https://ococsite.connexus.team/connexus_api/src/connexus-api.js | Public JS library | `ococsite.connexus.team_*.log` |
| https://ococsite.connexus.team/admin | Admin panel | `ococsite.connexus.team_*.log` |
| https://myococ.connexus.team | Member dashboard | `myococ.connexus.team_*.log` |
| https://myococ.connexus.team/login.php | Member login | `myococ.connexus.team_*.log` |
| https://myococ.connexus.team/dashboard | Dashboard home | `myococ.connexus.team_*.log` |

---

## Document History

| Date | Change |
|------|--------|
| 2025-12-10 | Initial creation of api.connexus.team backend |
| 2025-12-10 | Created this architecture document |
