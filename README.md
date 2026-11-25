# Members module

Membership system for Bono CMS: member accounts, authentication, profiles,
a public directory, tags, optional profile moderation, 1-to-1 private
messaging, and an events extension with analytics. Ships a server-rendered
site area (Bootstrap 5) and a JSON API (JWT) for mobile apps and SPAs.

# Contents

- [Requirements](#requirements)
- [Configuration](#configuration)
- [Features](#features)
- [Site pages](#site-pages)
- [Administration](#administration)
- [Templates](#templates)
- [Events JavaScript](#events-javascript)
- [API](#api)
- [Localization](#localization)
- [Data and storage](#data-and-storage)
- [Known limitations](#known-limitations)

# Requirements

- Bono CMS with the `Cms` module loaded (language manager, mailer)
- A working mailer in the `Cms` module (registration and recovery emails)
- Bootstrap 5 and Bootstrap Icons on the page (bundled templates)
- jQuery 3.x (bundled chat and event scripts)
- Chart.js 4.x from CDN (event analytics charts)
- Leaflet 1.9.4 + Leaflet.markercluster 1.5.3 (event maps)

# Configuration

Everything lives in `Config/module.config.php`.

## Moderation

```php
'moderation' => [
    'enabled' => true,
    'fields'  => [
        'reserved'     => ['id', 'password', 'remove_avatar', 'email'],
        'skip'         => ['phone', 'password'],
        'translatable' => ['bio', 'title', 'name'],
        'base'         => ['address', 'avatar', 'company'],
    ],
],
```

| Key            | Meaning                                                        |
|----------------|----------------------------------------------------------------|
| `enabled`      | Master switch. When `false`, every profile edit applies immediately. |
| `reserved`     | Fields that never enter the pipeline.                          |
| `skip`         | Base fields applied immediately even when moderation is on.    |
| `translatable` | Translated fields staged for review.                           |
| `base`         | Base fields staged for review.                                 |

With the default config, `phone` and `country` apply immediately;
`address`, `company`, `avatar`, `name`, `title` and `bio` are staged for
admin approval. The email and password are never editable through the
profile form.

## Events extension

```php
'extensions' => [
    'events' => [
        'enabled'      => true,
        'ip_hash_salt' => 'REPLACE-ME-with-a-long-random-string',
    ],
],
```

| Key            | Meaning                                                        |
|----------------|----------------------------------------------------------------|
| `enabled`      | Master switch for the events feature.                          |
| `ip_hash_salt` | Salt for analytics deduplication. Replace in production.       |

## API

```php
'api' => [
    'jwt_secret' => 'REPLACE-ME-with-a-long-random-string-at-least-32-chars',
    'jwt_ttl'    => 604800, // 7 days
],
```

Replace `jwt_secret` before enabling the API. If it's empty or left as the
placeholder, every protected request is rejected or every token is forgeable.

# Features

## Members

- Registration with email confirmation
- Login / logout (session-based for the site, JWT for the API)
- Password recovery by email
- Editable profiles: avatar, name, title, company, country, address, bio
- Translatable profile fields (`name`, `title`, `bio`) per language
- Optional moderation: profile edits can require admin approval
- Public member directory, filterable by country, company and tags
- Public profile pages per confirmed member
- Tag system with admin-defined groups; members pick their own tags

## Chat

- 1-to-1 private messaging with polling-based delivery
- Inbox sorted by newest activity, unread counters
- Idempotent sends (safe retry with `client_id`)
- Soft-deleted messages render as a tombstone

## Events

- Members create events (pending moderation); admins approve / reject
- Admin-created events are published immediately, on behalf of the
  association, with no author
- Translatable event content: title, description, organizer per language
- Shared fields: registration link, location, coordinates, schedule
- Optional cover image (400×225, 800×450, 1600×900 + original)
- Map URL parsing (Google Maps, Yandex, OSM, 2GIS) to extract coordinates
- Public catalog, detail page with map, click-tracked "Sign up" button
- Per-event analytics: 14-day trend, traffic sources, conversion rate
- Global admin dashboard: KPIs, top events, top authors
- Leaflet map with clustered markers, geolocation and radius filter

# Site pages

| URL                                    | Page                          | Auth     |
|----------------------------------------|-------------------------------|----------|
| `/member/login`                        | Login                         | Guest    |
| `/member/register`                     | Registration                  | Guest    |
| `/member/recovery`                     | Password recovery             | Public   |
| `/member/profile`                      | Member dashboard              | Login    |
| `/member/profile/edit`                 | Edit profile + tags           | Login    |
| `/members`                             | Public directory              | Public   |
| `/member/profile/(:var)`               | Public profile                | Public   |
| `/member/messages`                     | Chat inbox                    | Login    |
| `/member/messages/conversation/(:var)` | Chat thread                   | Login    |
| `/events`                              | Events catalog + map          | Public   |
| `/events/(:var)`                       | Event detail                  | Public   |
| `/events/(:var)/go`                    | Click tracker → redirect      | Public   |
| `/member/events`                       | My events dashboard           | Login    |
| `/member/events/create`                | Create event form             | Login    |
| `/member/events/edit/(:var)`           | Edit event form               | Login    |
| `/member/events/analytics/(:var)`      | Event analytics               | Login    |

"Login" = redirected to the login page if not authenticated.
"Guest" = redirected to the profile if already logged in.

# Administration

All under `/<admin>/module/members`:

| Path                     | Purpose                              |
|--------------------------|--------------------------------------|
| `/`                      | Members grid                         |
| `/add`, `/edit/(:var)`   | Create / edit a member               |
| `/changes`               | Review pending profile changes       |
| `/history/(:var)`        | Change history for a member          |
| `/tags`                  | Tag groups                           |
| `/tags/group/(:var)`     | Tags inside a group                  |
| `/events`                | Events grid                          |
| `/events/create`         | Create an association event          |
| `/events/edit/(:var)`    | Edit any event                       |
| `/events/analytics`      | Global analytics dashboard           |
| `/events/analytics/(:var)` | Per-event analytics               |

# Templates

The module ships templates in `View/Template/site/` (wrapped by
`__layout__.phtml`) and `View/Template/admin/`. Email bodies live in
`View/Template/messages/`. Override any of them in your theme.

## Variables available in every site template

```
$languages            // Active language entities
$isLoggedIn           // bool
$meId                 // Current member id (0 when logged out)
$unreadMessagesCount  // Unread messages across conversations
```

`$languages` is also available in every admin template.

## MemberEntity helpers

```php
$member->getId();
$member->getName();
$member->getDisplayName();       // name → email → "#<id>"
$member->getEmail();
$member->getPhone();
$member->getCompany();
$member->getCountry();           // ISO 3166-1 alpha-2
$member->getCountryName();       // human-readable, or null
$member->getAddress();
$member->getTitle();
$member->getBio();
$member->getConfirmed();
$member->hasAvatar();
$member->getAvatarUrl('256x256');
```

Avatar size constants: `AVATAR_THUMB` (`64x64`), `AVATAR_SMALL` (`256x256`),
`AVATAR_PROFILE` (`256x256`).

## EventEntity helpers

```php
$event->getId();
$event->getLangId();
$event->getTitle();
$event->getDescription();
$event->getOrganizer();          // translatable, or null
$event->getOrganizerName();      // organizer → creator name → ""
$event->getLink();
$event->getLocationName();
$event->getLatitude();
$event->getLongitude();
$event->getStartsAt();           // "Y-m-d H:i:s"
$event->getEndsAt();
$event->getStatus();             // pending | approved | rejected
$event->getCreatorName();        // null for association events
$event->getCover();
$event->getViewsCount();
$event->getClicksCount();

// Display helpers
$event->getDisplayTitle();       // title, else "#<id>"
$event->getShortDescription(150);
$event->getFormattedDate();      // "Nov 22, 2026"
$event->getFormattedTime();      // "18:55"
$event->getFormattedStartDateTime();
$event->getFormattedEndTime();
$event->getStartMonth();         // "NOV"
$event->getStartDay();           // 22
$event->getDirectionsUrl();

// Predicates
$event->isPast();
$event->isOfficial();            // creator_id is null
$event->hasLink();
$event->hasLocation();
$event->hasCoordinates();
$event->hasDescription();
$event->hasOrganizer();
$event->hasCreator();
$event->hasCover();
$event->getCoverUrl(EventEntity::COVER_CARD);  // THUMB, CARD, FULL

// Status display
$event->getStatusBadgeClass();   // warning | success | danger
$event->getStatusTranslationKey();
$event->getConversionRate();
```

Cover size constants: `COVER_THUMB` (`400x225`), `COVER_CARD` (`800x450`),
`COVER_FULL` (`1600x900`).

## Event form templates

Both event forms (admin and member) share the same three top-level tabs:

| Tab        | Fields                                                        |
|------------|---------------------------------------------------------------|
| Main       | Language tabs (Title, Description, Organizer), Registration link, Cover image |
| Location   | Location name, Map URL, Coordinates                           |
| Schedule   | Starts at, Ends at                                            |

Language tabs are nested inside **Main**. Content fields post as
`translation[<langId>][title|description|organizer]`. Shared fields post as
`event[...]`. The cover uploads as `file`.

Both forms carry `enctype="multipart/form-data"` and `novalidate`.
The schedule inputs are `datetime-local` (`Y-m-d\TH:i`); the templates
normalize the entity's `Y-m-d H:i:s` values before rendering.

# Events JavaScript

Three assets, all auto-initializing on DOM ready:

| Asset               | Selector                  | Purpose                        |
|---------------------|---------------------------|--------------------------------|
| `event-form.js`     | `[data-event-form]`       | Map URL parser + validation gate |
| `event-analytics.js`| `[data-event-analytics]`  | Chart.js renderer               |
| `event-map.js`      | `[data-event-map]` / `[data-event-location-map]` | Leaflet maps |

Include after jQuery 3.x:

```php
<?php $this->getPluginBag()->appendScript($this->moduleAsset('event-form.js', 'Members')); ?>
```

`event-form.js` requires `data-parse-url` and `novalidate` on the form. It
parses map URLs into coordinates and gates form submission so the browser
doesn't silently block on a required field inside a hidden tab.

`event-analytics.js` requires Chart.js 4.x and reads `data-daily-stats` /
`data-source-stats` JSON attributes.

`event-map.js` requires Leaflet. `EventMap` fetches
`/api/v1/members/events/render/map` and renders clustered markers.
`SingleEventMap` reads `data-latitude`, `data-longitude`,
`data-location-name` from its container.

To disable auto-init, set `window.EventForm.autoInit = false` (etc.) before
loading the script.

# API

JSON API under `/api/v1/members/`. Uses JWT bearer tokens instead of
cookies. Every endpoint accepts an optional `?lang=xx` parameter to select
the language for translated fields.

## Authentication

Protected endpoints expect:

```
Authorization: Bearer <token>
```

Tokens are issued by the login endpoint, valid for `api.jwt_ttl` seconds.
No refresh flow — re-login when expired. A missing or invalid token yields
`401 { "error": "Unauthorized" }`.

## Endpoints

### Log in

`POST /api/v1/members/auth/login` — public.

| Name         | Type   | Required | Notes           |
|--------------|--------|----------|-----------------|
| `identifier` | string | yes      | Email address   |
| `password`   | string | yes      | Plain-text      |

**200:**
```json
{
  "token": "eyJhbGci...",
  "user": { "id": 42, "name": "Jane Doe", "email": "jane@example.com", "confirmed": true }
}
```

**Errors:** `400` missing credentials, `401` wrong identifier or password.

### Request a password recovery email

`POST /api/v1/members/auth/recovery` — public.

| Name    | Type   | Required |
|---------|--------|----------|
| `email` | string | yes      |

**202:**
```json
{ "message": "If an account exists with that email, a reset link has been sent." }
```

Response is identical whether or not the email matches (prevents enumeration).

### Validate the current token

`GET /api/v1/members/auth/whoami` — protected.

**200:**
```json
{ "id": 42, "name": "Jane Doe", "email": "jane@example.com", "confirmed": true, "avatar": "https://..." }
```

**Errors:** `401`.

### Register a new member

`POST /api/v1/members/register` — public.

| Name                          | Type   | Required | Notes                          |
|-------------------------------|--------|----------|--------------------------------|
| `translation[<langId>][name]` | string | yes      | Min 2 chars. `<langId>` may be a code (`en`) |
| `member[email]`               | string | yes      | Valid, unique                  |
| `member[password]`            | string | yes      | Min 6 chars                    |

**201:**
```json
{ "message": "Registration successful. Please check your email to confirm your account." }
```

**Errors:** `422` validation, `500` save failure.

### Resend confirmation email

`POST /api/v1/members/register/resend` — protected.

**200:**
```json
{ "message": "A new confirmation email has been sent." }
```

**Errors:** `401`, `409` already confirmed.

### Get my profile

`GET /api/v1/members/me` — protected.

**200:**
```json
{
  "user": {
    "id": 42, "name": "Jane Doe", "login": null, "email": "jane@example.com",
    "confirmed": true, "company": "Acme", "phone": "+1 555 0100",
    "country": "us", "address": "123 Main St", "title": "Engineer",
    "bio": "I build things.", "avatar": "https://..."
  },
  "tags": {
    "selected": [1, 5, 9],
    "available": [{ "group": {...}, "tags": [{ "id": 1, "label": "Hiking" }] }]
  },
  "pendingChanges": [{ "id": 17, "member_id": 42, "status": "pending", "payload": "...", "created_at": "..." }]
}
```

**Errors:** `401`, `404`.

### Update my profile

`POST /api/v1/members/me/update` — protected. Multipart.

| Name                                | Type   | Required | Notes                              |
|-------------------------------------|--------|----------|------------------------------------|
| `translation[<langId>][name]`       | string | yes      | Min 2 chars                        |
| `translation[<langId>][title]`      | string | no       |                                    |
| `translation[<langId>][bio]`        | string | no       |                                    |
| `member[phone]`                     | string | no       | Applied immediately                |
| `member[country]`                   | string | no       | ISO 3166-1 alpha-2                 |
| `member[company]`                   | string | no       |                                    |
| `member[address]`                   | string | no       |                                    |
| `member[remove_avatar]`             | flag   | no       |                                    |
| `tags[]`                            | int[]  | no       |                                    |
| `file`                              | file   | no       | Avatar image                       |

Only the language selected by `?lang=` (or the active language) is accepted.

**200:**
```json
{ "message": "Your changes were submitted for review.", "user": {...}, "pendingChanges": [...] }
```

**Errors:** `401`, `422`.

### Upload an avatar

`POST /api/v1/members/me/avatar` — protected. Multipart.

| Name   | Type | Required |
|--------|------|----------|
| `file` | file | yes      |

**200:**
```json
{ "message": "Your changes were submitted for review.", "avatar": "https://..." }
```

**Errors:** `401`, `422`.

### Change my password

`POST /api/v1/members/me/password` — protected.

| Name       | Type   | Required | Notes    |
|------------|--------|----------|----------|
| `password` | string | yes      | Min 6    |

**204** on success. **Errors:** `401`, `422`.

### List members

`GET /api/v1/members` — public. Paginated, 20 per page, random order.

| Name      | Type   | Required | Notes                    |
|-----------|--------|----------|--------------------------|
| `page`    | int    | no       | Default 1                |
| `country` | string | no       | ISO 3166-1 alpha-2       |
| `company` | string | no       | Exact match              |
| `tags[]`  | int[]  | no       | Any-of                   |
| `lang`    | string | no       |                          |

**200:**
```json
{
  "data": [{ "id": 42, "name": "Jane Doe", "title": "Engineer", "company": "Acme", "country": "us", "bio": "...", "avatar": "https://..." }],
  "meta": { "page": 1, "perPage": 20, "total": 137, "totalPages": 7 },
  "filters": { "applied": {...}, "available": { "countries": [...], "companies": [...], "tags": [...] } }
}
```

### Get a member's public profile

`GET /api/v1/members/profile?id=42` — public.

**200:**
```json
{ "user": { "id": 42, "name": "...", "title": "...", "company": "...", "country": "us", "bio": "...", "avatar": "..." }, "tags": [...] }
```

**Errors:** `400`, `404`.

### List my conversations (inbox)

`GET /api/v1/members/chats` — protected.

**200:**
```json
{ "data": [{ "id": 7, "type": "direct", "displayName": "John Smith", "other": {...}, "lastMessage": {...}, "lastMessageAt": "...", "messageCount": 42, "unreadCount": 2, "mutedUntil": null }] }
```

### Get a conversation thread

`GET /api/v1/members/chat/thread?id=7` — protected.

| Name     | Type | Required | Notes                    |
|----------|------|----------|--------------------------|
| `id`     | int  | yes      | Conversation id          |
| `before` | int  | no       | Load-earlier pagination  |

**200:** ascending message list, marks thread read.
**Errors:** `400`, `401`, `403`.

### Send a message

`POST /api/v1/members/chat/send` — protected.

| Name              | Type   | Required | Notes                          |
|-------------------|--------|----------|--------------------------------|
| `conversation_id` | int    | yes      |                                |
| `body`            | string | yes      | Max 5000 chars                 |
| `client_id`       | string | no       | UUID for idempotent retry      |

**201:** `{ "id": 101 }`. **Errors:** `401`, `403`, `422`.

### Start a conversation

`POST /api/v1/members/chat/start` — protected.

| Name        | Type | Required |
|-------------|------|----------|
| `member_id` | int  | yes      |

**200:** `{ "conversationId": 7 }`. **Errors:** `400`, `401`, `422`.

### Poll for new messages

`GET /api/v1/members/chat/poll?id=7&since=100` — protected.

**200:** messages newer than `since`, ascending. Advances the read cursor.

### Get the unread message count

`GET /api/v1/members/chat/unread` — protected.

**200:** `{ "count": 3 }`.

### List approved events

`GET /api/v1/members/events` — public. Paginated, 20 per page.

| Name   | Type   | Required |
|--------|--------|----------|
| `page` | int    | no       |
| `lang` | string | no       |

**200:**
```json
{
  "data": [{
    "id": 42,
    "cover": "https://.../800x450/abc.jpg",
    "title": "IT Alliance Breakfast",
    "description": "Monthly meetup",
    "organizer": "IT Alliance",
    "link": "https://example.com/register",
    "locationName": "Business Center",
    "latitude": 41.2995, "longitude": 69.2401,
    "startsAt": "2026-11-22 18:55:00", "endsAt": "2026-11-22 22:00:00",
    "status": "approved",
    "creatorName": "Jane Doe",
    "viewsCount": 120, "clicksCount": 15
  }],
  "meta": { "page": 1, "perPage": 20, "total": 42, "totalPages": 3 }
}
```

`cover` and `creatorName` are `null` when absent. Only events with a
translation for the requested language are returned.

### Get a single event

`GET /api/v1/members/events/view/:id` — public. Records a view.

**200:** `{ "data": { ...event... } }`. **Errors:** `404`.

### Record a click and return the link

`GET /api/v1/members/events/:id/click` — public.

**200:** `{ "link": "https://example.com/register" }`. **Errors:** `404`.

### Render events on a map

`GET /api/v1/members/events/render/map` — public. No pagination.

| Name    | Type   | Required | Notes                                       |
|---------|--------|----------|---------------------------------------------|
| `from`  | string | no       | `starts_at >= from` (MySQL datetime)         |
| `to`    | string | no       | `starts_at <= to`                            |
| `bbox`  | string | no       | `minLat,minLng,maxLat,maxLng`                |
| `limit` | int    | no       | Default 500, max 1000                        |
| `lang`  | string | no       | Language of returned titles                  |

**200:**
```json
{
  "data": [{ "id": 42, "title": "...", "latitude": 41.29, "longitude": 69.24, "location_name": "...", "starts_at": "2026-11-22 18:55:00", "url": "/events/42" }],
  "meta": { "total": 1, "limit": 500, "clipped": false }
}
```

**Note:** this endpoint returns `snake_case` keys and a reduced row — it
differs from every other events endpoint. `url` is a relative web path.
Malformed `bbox` or non-numeric `limit` are silently ignored.

### Create an event

`POST /api/v1/members/events/store` — protected. Multipart.

| Name                                       | Type   | Required | Notes                       |
|--------------------------------------------|--------|----------|-----------------------------|
| `translation[<langId>][title]`             | string | no       | Max 200                     |
| `translation[<langId>][description]`       | string | no       |                             |
| `translation[<langId>][organizer]`         | string | no       | Max 200                     |
| `translation[<langId>][lang_id]`           | int    | yes      | Same as `<langId>`          |
| `event[location_name]`                     | string | yes      | Max 255                     |
| `event[starts_at]`                         | string | yes      | `Y-m-d H:i:s`               |
| `event[link]`                              | string | no       | Max 500                     |
| `event[latitude]`                          | float  | no       |                             |
| `event[longitude]`                         | float  | no       |                             |
| `event[location_source]`                   | string | no       | `none` \| `manual` \| `url` |
| `event[ends_at]`                           | string | no       |                             |
| `file`                                     | file   | no       | Cover image                 |

**201:** `{ "id": 42, "message": "The event has been created and is pending review" }`.
**Errors:** `401`, `422`.

### List my events with summary

`GET /api/v1/members/me/events` — protected.

**200:**
```json
{ "data": [...], "summary": { "eventCount": 5, "totalViews": 420, "totalClicks": 63, "conversion": 15.0 }, "meta": {...} }
```

### Get analytics for my event

`GET /api/v1/members/me/events/:id/analytics` — protected.

**200:**
```json
{ "data": { "event": {...}, "views": 120, "clicks": 15, "conversion": 12.5, "dailyStats": [...], "sourceStats": [...] } }
```

**Errors:** `401`, `404`.

## Error shape

Non-validation errors:
```json
{ "error": "Human-readable message" }
```

Validation errors (422):
```json
{ "errors": { "member[email]": ["This email is already taken"] } }
```

## Status codes

| Code | Meaning                                                |
|------|--------------------------------------------------------|
| 200  | OK                                                     |
| 201  | Created                                                |
| 202  | Accepted (recovery request)                            |
| 204  | No content (password change)                           |
| 400  | Malformed request                                      |
| 401  | Missing / expired / forged JWT, or member deleted      |
| 403  | Not a participant                                      |
| 404  | Not found, or hidden by language / confirmation state  |
| 409  | Conflict (resend when already confirmed)               |
| 422  | Validation failure                                     |
| 500  | Unhandled server error                                 |

# Localization

User-facing strings are translation keys in English. Add a language by
creating `Translations/<code>/messages.php` returning a `key => translation`
array.

Content translations (member names, event titles, tag labels) live in
`_translations` tables and are managed through the forms, not message files.
Adding a language in the `Cms` module adds a tab to every multilingual form
automatically.

# Data and storage

| Table                                              | Purpose                              |
|----------------------------------------------------|--------------------------------------|
| `bono_module_members`                              | Base member row                      |
| `bono_module_members_translations`                 | `name`, `title`, `bio` per language  |
| `bono_module_members_changes`                      | Moderation submissions               |
| `bono_module_members_conversations`                | Chat conversations                   |
| `bono_module_members_conversation_participants`    | Participants, read cursor            |
| `bono_module_members_messages`                     | Chat messages                        |
| `bono_module_members_tag_groups` / `_translations` | Tag groups                           |
| `bono_module_members_tags` / `_translations`       | Tags                                 |
| `bono_module_members_member_tags`                  | Member ↔ tag selections              |
| `bono_module_members_events`                       | Events (shared fields, cover)        |
| `bono_module_members_events_translations`          | `title`, `description`, `organizer`  |
| `bono_module_members_event_analytics`              | Views and clicks                     |

Files:

- `/data/uploads/module/members/avatars/<memberId>/<WxH>/<file>` — avatars
- `/data/uploads/module/members/pending/` — avatars awaiting moderation
- `/data/uploads/module/members/events/covers/<eventId>/<WxH>/<file>` — covers

# Known limitations

- **Avatar changes require moderation.** With `moderation.enabled = false`,
  uploaded avatars are not processed.
- **Chat threads return the oldest 50 messages** by default.
- **Members without a translation** for the active language are hidden from
  the directory, public profiles and the admin grid.
- **Events without a translation** for the active language are hidden from
  the catalog, map, admin grid and "My events". The forms post every
  language tab, so this only matters when a new language is added later.
- **Blank language tabs** are saved as empty titles, not skipped.
- **Event covers bypass moderation** — they are written directly.
- **JWT tokens cannot be revoked** before expiry.
- **Event service error messages** are English. Translations ship in the
  message files but only apply if controllers pass them through
  `$this->translator->translate()`.
- **The map endpoint** returns `snake_case` keys unlike every other events
  endpoint, and silently ignores malformed `bbox` / `limit` parameters.