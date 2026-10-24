Members module
==============

This module provides a shared membership system for your site: member
accounts, authentication, profiles, a public directory, and 1-to-1 private
messaging. Other modules that need "a logged-in user" build on top of it.

# Features

## General features

 - Session-based login for the site, JWT-based login for the API
 - Registration with email confirmation
 - Password recovery by email
 - Editable member profiles with avatar, title, company, country and bio
 - Public member directory with filtering by country, company and tags
 - Tag system, grouped for the member-facing picker
 - Optional profile moderation (approve / reject edits before they go live)
 - 1-to-1 private chat with unread counts and an inbox
 - Full administration panel: members, tags, pending changes, change history

## Member features

 - Confirmed and unconfirmed accounts (unconfirmed accounts can log in and
   edit their profile, but do not appear in the public directory)
 - Avatar with several pre-rendered sizes (64×64, 128×128, 256×256)
 - Translatable `title` and `bio`
 - Self-selected tags inside admin-defined tag groups
 - Personal dashboard with profile summary
 - Change history visible to administrators

## Chat features

 - Direct (1-to-1) conversations, resolved by member pair
 - Message inbox sorted by most recent activity
 - Unread message counter, per conversation and across all conversations
 - Idempotent message sending (a retried send does not duplicate)
 - Soft-deleted messages show as a tombstone
 - Polling-based delivery, with adaptive intervals
 - Optional "load earlier" pagination

## Directory features

 - Paginated, filterable list of confirmed members
 - Filter by country (ISO 3166-1 alpha-2)
 - Filter by company
 - Filter by tags (any-of semantics)
 - Public profile pages per member, with tags grouped by tag group

## Administration features

 - Member grid with pagination
 - Create members manually, edit, delete
 - View full change history per member
 - Review pending profile changes with an old/new diff
 - Manage tag groups and tags, with translations and active/inactive flag
 - Cascade-aware deletion (removing a member removes their messages,
   profile changes and tag selections)

## Coming soon

 - Group conversations
 - Roles and permissions rework
 - Notification system
 - Profile approvals toggle per member
 - Gamification hooks

# Templates

All site-facing template files live in the current theme directory. The
filenames below are the ones the module looks up.

## Member login template

The file must be named `member-login.phtml`. It receives a `$page` entity
for SEO and title metadata. The login form posts to the login endpoint with
two fields: `identifier` (email) and `password`.

## Member registration template

The file must be named `member-register.phtml`. It receives a `$page`
entity. The form posts `member[name]`, `member[email]` and `member[password]`. On success the member is redirected to the
registration success page, and a confirmation email is sent.

## Member profile (dashboard) template

The file must be named `member-dashboard.phtml`. It receives:

    $member       // Member entity of the logged-in member
    $tagGroups    // The member's selected tags, grouped by tag group
    $page         // Page entity

Within this template, the `$member` entity provides:

    $member->getName();
    $member->getEmail();
    $member->getLogin();
    $member->getCompany();
    $member->getCountry();
    $member->getAddress();
    $member->getTitle();
    $member->getBio();
    $member->getConfirmed();
    $member->hasAvatar();
    $member->getAvatarUrl('256x256'); // 64x64, 128x128, 256x256 available

## Member profile edit template

The file must be named `member-profile.phtml`. It combines the editable
profile fields and the tag picker in a single form. It receives:

    $member       // Member entity
    $pending      // Array of pending moderation submissions (empty if moderation is off)
    $payload      // Tag picker payload: [ ['group' => ..., 'tags' => [...]], ... ]
    $selected     // Array of selected tag ids
    $page         // Page entity

The form posts `member[...]` fields, `data[translation][<langId>][...]`
fields, and `tags[]`. If moderation is enabled, some changes may be
staged for review rather than applied immediately.

**Tag picker**

The `$payload` variable is structured so you can render groups and their
tags in one pass:

    <?php foreach ($payload as $block): ?>
        <fieldset>
            <legend><?= $block['group']['name']; ?></legend>
            <?php foreach ($block['tags'] as $tag): ?>
                <label>
                    <input type="checkbox" name="tags[]"
                           value="<?= $tag['id']; ?>"
                           <?= in_array((int) $tag['id'], array_map('intval', $selected)) ? 'checked' : ''; ?>>
                    <?= $tag['label']; ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
    <?php endforeach; ?>

## Member public profile template

The file must be named `member-public-profile.phtml`. It is rendered for a
confirmed member's public page. It receives:

    $member       // Member entity (public fields only)
    $tagGroups    // Selected tags grouped by tag group
    $page         // Page entity

Only confirmed members have a public profile. Visiting the URL of an
unconfirmed member returns "not found".

## Member directory template

The file must be named `member-directory.phtml`. It receives:

    $members       // Paginated list of confirmed Member entities
    $paginator     // Paginator instance
    $total         // Total number of matching members
    $countries     // [code => name] map of ISO 3166-1 countries
    $companies     // Array of distinct company names
    $payload       // Tag picker payload
    $filterCountry // Currently applied country filter (or null)
    $filterCompany // Currently applied company filter (or null)
    $filterTags    // Currently applied tag ids

A typical directory row renders the member's display name, title, company,
country, bio and avatar. Use `$member->getDisplayName()` — it falls back to
login, then to a placeholder, so legacy rows never render blank.

## Member inbox template

The file must be named `member-inbox.phtml`. It receives:

    $conversations // Array of shaped conversation rows
    $page          // Page entity

Each conversation row contains:

    [
        'id'            => int,
        'type'          => 'direct' | 'group',
        'displayName'   => string,
        'other'         => null | ['id' => int, 'name' => string, 'avatar' => string|null],
        'lastMessage'   => null | ['text' => string, 'createdAt' => string, 'senderId' => int],
        'lastMessageAt' => string|null, // ISO 8601
        'messageCount'  => int,
        'unreadCount'   => int,
        'mutedUntil'    => string|null,
    ]

## Member conversation template

The file must be named `member-conversation.phtml`. It renders a single
chat thread. It receives:

    $conversationId // int
    $messages       // Raw message rows from the service
    $senders        // Lookup table of sender ids → member rows
    $meId           // The current member's id
    $otherMember    // Member entity of the other participant, or false
    $page           // Page entity

The chat form must carry the following `data-*` attributes for the
bundled `chat.jquery.js` to work without configuration:

    <form id="chat-form"
          data-conversation="<?= $conversationId; ?>"
          data-send-url="<?= $this->url('Members:Chat@sendAction'); ?>"
          data-poll-url="<?= $this->url('Members:Chat@pollAction'); ?>"
          data-me-id="<?= $meId; ?>"
          data-last-id="<?= $messages ? (int) end($messages)['id'] : 0; ?>">
        <textarea name="body"></textarea>
        <button type="submit">Send</button>
    </form>

## Chat JavaScript

The module ships `Assets/chat.jquery.js`, which:

 - Auto-initializes on any `#chat-form` element
 - Polls for new messages using the four `data-*` URLs
 - Pauses polling when the tab is hidden
 - Uses a `crypto.randomUUID()` client id for idempotent sends

Include it after jQuery 3.x:

    <script src="/modules/Members/Assets/chat.jquery.js"></script>

To disable auto-init and construct instances manually, set
`window.Chat.autoInit = false` before loading the script.

# Services

## Member service

The `$member` variable is available in every site template and represents
the currently logged-in member.

    // TRUE when a member is logged in.
    $member->isLoggedIn();

    // TRUE when the logged-in member has confirmed their email.
    $member->isConfirmed();

    // Returns the session member data as an array, or a single key
    // when one is passed: $member->getMember('id'), etc.
    $member->getMember();

## Chat service

The chat service is available through the module's service locator; site
templates that need the member's inbox should receive it via the
controller. It provides:

    // The member's inbox.
    $chatService->fetchInbox($memberId);

    // Total unread count across all conversations.
    $chatService->countUnreadForMember($memberId);

    // Resolve or create a direct conversation with another member.
    // Throws InvalidArgumentException when the two ids are the same.
    $chatService->findOrCreateDirect($memberId, $otherId);

    // Fetch a thread and advance the read cursor.
    $chatService->fetchThreadAndMarkRead($conversationId, $memberId, $sinceId = 0);

    // Send a message. Pass a client_id for idempotent retries.
    $chatService->sendMessage($conversationId, $senderId, $body, $clientId = null);

## Tag service

The tag service (`memberTagService`) is used by the profile edit template to
populate the tag picker. It provides:

    // The picker payload: [ ['group' => row, 'tags' => [row, ...]], ... ]
    $memberTagService->fetchPickerPayload();

    // The ids of the tags a member has selected.
    $memberTagService->fetchSelectedIds($memberId);

    // The tags a member has selected, grouped by tag group, ready for
    // a public profile page.
    $memberTagService->fetchGroupedByMember($memberId);

# URL Generation

## Member pages

    <a href="<?= $cms->createUrl(1, 'Members'); ?>">Member profile</a>

    <a href="<?= $cms->createUrl(1, 'Members (Directory)'); ?>">Member directory</a>

## Chat pages

    <a href="<?= $this->url('Members:Chat@inboxAction'); ?>">My messages</a>

    <a href="<?= $this->url('Members:Chat@conversationAction', [$conversationId]); ?>">
        Open conversation
    </a>

    <a href="<?= $this->url('Members:Chat@withAction', [$otherMemberId]); ?>">
        Message this member
    </a>

The `with` action resolves or creates the conversation and redirects to the
thread, so it is safe to link to from a profile page without knowing the
conversation id.

## Profile and account pages

    <a href="<?= $this->url('Members:Profile@indexAction'); ?>">My account</a>
    <a href="<?= $this->url('Members:Profile@editAction'); ?>">Edit my profile</a>
    <a href="<?= $this->url('Members:Auth@logoutAction'); ?>">Log out</a>

# API

The module exposes a JSON API under `/api/v1/members/` for mobile apps and
SPA clients. It uses JWT tokens instead of cookies.

## Before you enable the API

Replace the placeholder `api.jwt_secret` in `Config/module.config.php`
with a long random string. If the secret is left as the shipped
placeholder, every token is forgeable. See the developer documentation for
how to load it from an environment variable instead.

## Authentication

Protected endpoints expect a standard bearer token:

    Authorization: Bearer <token>

Tokens are issued by the login endpoint and are valid for `api.jwt_ttl`
seconds (7 days by default). There is no refresh flow; the client logs in
again when a token expires.

The API never sets cookies and never redirects. Clients must treat any
`3xx` response as a bug.

## Language

Every endpoint accepts an optional `?lang=xx` query parameter. Translated
fields (member titles, bios, tag labels) are returned in that language.
Omitting it uses the site's active language.

## Endpoints

Each endpoint is titled by its purpose; the HTTP method and URL are listed
at the end of its section.

### Log in

Authenticate and receive a JWT. Public.

**Parameters**

| Name         | Type   | Required | Notes                  |
|--------------|--------|----------|------------------------|
| `identifier` | string | yes      | Email address          |
| `password`   | string | yes      | Plain-text password    |

**Response 200**

```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 42,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "confirmed": true
  }
}
```

**Errors:** `400` missing credentials, `401` wrong identifier or password.

Unconfirmed members receive a token; `confirmed` is `false`. Clients should
show a "please confirm your email" banner and offer the
[resend confirmation](#resend-confirmation-email) endpoint.

**Endpoint:** `POST /api/v1/members/auth/login`

### Request a password recovery email

Send a password-recovery email. Public. The response is identical whether
or not the email matches an account, to prevent account enumeration.

**Parameters**

| Name    | Type   | Required | Notes                |
|---------|--------|----------|----------------------|
| `email` | string | yes      | Must be a valid email |

**Response 202**

```json
{ "message": "If an account exists with that email, a reset link has been sent." }
```

**Errors:** `400` invalid email format.

**Endpoint:** `POST /api/v1/members/auth/recovery`

### Validate the current token

Validate the current token and return fresh user data. Protected.

**Response 200**

```json
{
  "id": 42,
  "name": "Jane Doe",
  "email": "jane@example.com",
  "confirmed": true,
  "avatar": "https://example.com/data/uploads/module/members/avatars/42/128x128/abc.jpg"
}
```

**Errors:** `401` missing, expired, forged token, or the member row was
deleted.

**Endpoint:** `GET /api/v1/members/auth/whoami`

### Register a new member

Create a new member account. The account is unconfirmed until the
confirmation link is clicked. Public.

**Parameters**

| Name               | Type   | Required | Notes               |
|--------------------|--------|----------|---------------------|
| `member[name]`     | string | yes      | Min length 2        |
| `member[email]`    | string | yes      | Valid, unique email |
| `member[password]` | string | yes      | Min length 6        |

**Response 201**

```json
{ "message": "Registration successful. Please check your email to confirm your account." }
```

**Errors:** `422` validation errors, in the standard error shape.

A confirmation email is sent automatically. The link points at the **web**
confirmation route, not an API route — the API deliberately does not expose
a confirmation-by-key endpoint.

**Endpoint:** `POST /api/v1/members/register`

### Resend confirmation email

Re-send the confirmation email to the currently authenticated member.
Protected.

**Response 200**

```json
{ "message": "A new confirmation email has been sent." }
```

**Errors:** `401` no token, `409` account already confirmed.

**Endpoint:** `POST /api/v1/members/register/resend`

### Get my profile

Return the authenticated member's own profile, the tag picker payload, and
any pending moderation changes. Protected.

**Response 200**

```json
{
  "user": {
    "id": 42,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "confirmed": true,
    "company": "Acme",
    "phone": "+1 555 0100",
    "country": "US",
    "address": "123 Main St",
    "title": "Engineer",
    "bio": "I build things.",
    "avatar": "https://example.com/data/uploads/module/members/avatars/42/256x256/abc.jpg"
  },
  "tags": {
    "selected": [1, 5, 9],
    "available": [
      {
        "group": { "id": 1, "name": "Interests" },
        "tags": [
          { "id": 1, "label": "Hiking" },
          { "id": 2, "label": "Reading" }
        ]
      }
    ]
  },
  "pendingChanges": [
    { "id": 17, "status": "pending", "created_at": "2026-01-14 09:00:00" }
  ]
}
```

`pendingChanges` is empty when moderation is disabled.

**Errors:** `401` no token, `404` member not found.

**Endpoint:** `GET /api/v1/members/profile`

### Update my profile

Update the authenticated member's profile. Goes through the moderation
pipeline when moderation is enabled, otherwise applied directly. Protected.

**Parameters**

| Name                                 | Type   | Required | Notes                                      |
|--------------------------------------|--------|----------|--------------------------------------------|
| `member[name]`                       | string | yes      | Min length 2                               |
| `member[company]`                    | string | no       |                                            |
| `member[phone]`                      | string | no       | Applied immediately, never staged          |
| `member[address]`                    | string | no       |                                            |
| `member[country]`                    | string | no       | ISO 3166-1 alpha-2                         |
| `data[translation][<langId>][title]` | string | no       | Only the active `?lang=` row is accepted   |
| `data[translation][<langId>][bio]`   | string | no       | Only the active `?lang=` row is accepted   |
| `tags[]`                             | int[]  | no       | Sanitized against the active-tag set       |
| `file`                               | file   | no       | Multipart avatar upload; must be an image  |

**Response 200**

```json
{
  "message": "Your changes were submitted for review.",
  "user": { "...same shape as GET /profile..." },
  "pendingChanges": [ { "id": 18, "status": "pending", "created_at": "..." } ]
}
```

`message` is one of:

- `"Your profile has been updated."` — moderation off, or nothing staged
- `"Your changes were submitted for review."` — everything staged
- `"Some changes were applied immediately; the rest are pending review."` — mixed

**Errors:** `401` no token, `422` validation errors.

**Endpoint:** `POST /api/v1/members/profile/update`

### Upload an avatar

Upload a new avatar. Multipart form data. Protected.

**Parameters**

| Name   | Type | Required | Notes            |
|--------|------|----------|------------------|
| `file` | file | yes      | Must be an image |

**Response 200**

```json
{
  "message": "Your changes were submitted for review.",
  "avatar": "https://example.com/data/uploads/module/members/avatars/42/128x128/abc.jpg"
}
```

If moderation is enabled, `avatar` still points at the **previous** avatar
until the change is approved. Rely on `pendingChanges` to show "avatar
pending review."

**Errors:** `401` no token, `422` missing or non-image file.

**Endpoint:** `POST /api/v1/members/profile/avatar`

### Change my password

Change the authenticated member's password. Bypasses moderation (as on the
web). Protected.

**Parameters**

| Name       | Type   | Required | Notes        |
|------------|--------|----------|--------------|
| `password` | string | yes      | Min length 6 |

**Response 200**

```json
{ "success": true }
```

**Errors:** `401` no token, `422` validation errors.

**Endpoint:** `POST /api/v1/members/profile/password`

### List members

Paginated, filtered list of **confirmed** members. Public.

**Parameters**

| Name      | Type   | Required | Notes                                 |
|-----------|--------|----------|---------------------------------------|
| `page`    | int    | no       | Default 1                             |
| `country` | string | no       | ISO 3166-1 alpha-2                    |
| `company` | string | no       | Exact match                           |
| `tags[]`  | int[]  | no       | Members carrying any of these tag ids |
| `lang`    | string | no       | Language code                         |

**Response 200**

```json
{
  "data": [
    {
      "id": 42,
      "name": "Jane Doe",
      "title": "Engineer",
      "company": "Acme",
      "country": "US",
      "bio": "I build things.",
      "avatar": "https://example.com/data/uploads/module/members/avatars/42/128x128/abc.jpg"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 137,
    "totalPages": 7
  },
  "filters": {
    "applied": { "country": "US", "company": null, "tags": [] },
    "available": {
      "countries": { "US": "United States", "GB": "United Kingdom" },
      "companies": ["Acme", "Globex"],
      "tags": [
        { "group": { "id": 1, "name": "Interests" }, "tags": [{ "id": 1, "label": "Hiking" }] }
      ]
    }
  }
}
```

`filters.available` is returned on every request so the client can render
the filter UI without a second round-trip.

**Endpoint:** `GET /api/v1/members`

### Get a member's public profile

Public profile of a single **confirmed** member, with their selected tags
grouped by tag group. Public.

**Parameters**

| Name | Type | Required | Notes     |
|------|------|----------|-----------|
| `id` | int  | yes      | Member id |

**Response 200**

```json
{
  "user": {
    "id": 42,
    "name": "Jane Doe",
    "title": "Engineer",
    "company": "Acme",
    "country": "US",
    "bio": "I build things.",
    "avatar": "https://example.com/data/uploads/module/members/avatars/42/128x128/abc.jpg"
  },
  "tags": [
    {
      "group": { "id": 1, "name": "Interests" },
      "tags": [{ "id": 1, "label": "Hiking" }]
    }
  ]
}
```

**Errors:** `400` missing `id`, `404` no such member or member is
unconfirmed.

**Endpoint:** `GET /api/v1/members/profile?id=42`

### List my conversations (inbox)

The authenticated member's inbox. Protected.

**Response 200**

```json
{
  "data": [
    {
      "id": 7,
      "type": "direct",
      "displayName": "John Smith",
      "other": { "id": 43, "name": "John Smith", "avatar": "https://example.com/..." },
      "lastMessage": {
        "text": "See you tomorrow",
        "createdAt": "2026-01-14T09:12:00Z",
        "senderId": 43
      },
      "lastMessageAt": "2026-01-14T09:12:00Z",
      "messageCount": 42,
      "unreadCount": 2,
      "mutedUntil": null
    }
  ]
}
```

**Errors:** `401` no token.

**Endpoint:** `GET /api/v1/members/chats`

### Get a conversation thread

Fetch a conversation thread. Protected.

**Parameters**

| Name     | Type | Required | Notes                                                     |
|----------|------|----------|-----------------------------------------------------------|
| `id`     | int  | yes      | Conversation id                                           |
| `before` | int  | no       | Return messages older than this message id (load-earlier) |

Without `before`, returns the newest messages and marks the thread read.
With `before`, returns older messages and does **not** advance the read
cursor.

**Response 200**

```json
{
  "data": [
    {
      "_id": 100,
      "text": "Hello",
      "createdAt": "2026-01-14T09:00:00Z",
      "type": "text",
      "deleted": false,
      "editedAt": null,
      "replyToId": null,
      "clientId": null,
      "user": {
        "_id": 42,
        "name": "Jane Doe",
        "avatar": "https://example.com/..."
      }
    }
  ]
}
```

`user` is `null` when the sender's account has been deleted. Soft-deleted
messages appear with `"deleted": true` and `"text": ""`; clients should
render a tombstone.

**Errors:** `400` missing `id`, `401` no token, `403` not a participant.

**Endpoint:** `GET /api/v1/members/chat/thread?id=7`

### Send a message

Send a message. Protected.

**Parameters**

| Name              | Type   | Required | Notes                        |
|-------------------|--------|----------|------------------------------|
| `conversation_id` | int    | yes      |                              |
| `body`            | string | yes      | Trimmed, max 5000 characters |
| `client_id`       | string | no       | UUID; idempotent send        |

**Response 201**

```json
{ "id": 101 }
```

If `client_id` matches an existing message in the same conversation, the
original id is returned and no new row is inserted. Use this for safe
retries on flaky networks.

**Errors:** `400` missing fields, `401` no token, `403` not a participant,
`422` empty body after trimming or body too long.

**Endpoint:** `POST /api/v1/members/chat/send`

### Start a conversation

Resolve or create a direct conversation with another member. Protected.

**Parameters**

| Name        | Type | Required | Notes                 |
|-------------|------|----------|-----------------------|
| `member_id` | int  | yes      | The other member's id |

**Response 200**

```json
{ "conversationId": 7 }
```

The same pair always resolves to the same conversation id. Concurrent
requests are safe.

**Errors:** `400` missing `member_id`, `401` no token, `422` trying to
start a conversation with yourself.

**Endpoint:** `POST /api/v1/members/chat/start`

### Poll for new messages

Poll for new messages. Same response shape as the thread endpoint, filtered
to messages with `_id > since`. Advances the read cursor to the newest
returned message. Protected.

**Parameters**

| Name    | Type | Required | Notes                                             |
|---------|------|----------|---------------------------------------------------|
| `id`    | int  | yes      | Conversation id                                   |
| `since` | int  | no       | Return only messages with `_id` greater than this |

**Response 200**

```json
{
  "data": [
    { "_id": 101, "text": "Hi", "createdAt": "2026-01-14T09:15:00Z", "type": "text", "deleted": false, "editedAt": null, "replyToId": null, "clientId": null, "user": { "_id": 43, "name": "John Smith", "avatar": "..." } }
  ]
}
```

Polling is the only real-time mechanism; there is no WebSocket or SSE
endpoint. A sensible client pattern is burst after send (2–3 s), then
active (10 s), then idle (30 s after 60 s of silence), then back off to
60 s on failure. The bundled `chat.jquery.js` implements exactly this.

**Errors:** `400` missing `id`, `401` no token, `403` not a participant.

**Endpoint:** `GET /api/v1/members/chat/poll?id=7&since=100`

### Get the unread message count

Total unread count across all conversations, for badge rendering.
Protected.

**Response 200**

```json
{ "count": 3 }
```

**Errors:** `401` no token.

**Endpoint:** `GET /api/v1/members/chat/unread`

## Error shape

Non-validation errors use a single shape:

```json
{ "error": "Human-readable message" }
```

Validation errors (HTTP 422) use the framework's standard shape:

```json
{
  "errors": {
    "member[name]": ["This field is required"],
    "member[email]": ["This email is already taken"]
  }
}
```

## Status codes

| Code | Meaning                                                |
|------|--------------------------------------------------------|
| 200  | OK                                                     |
| 201  | Created (registration, message send)                   |
| 202  | Accepted (recovery, resend — asynchronous)             |
| 400  | Malformed request (missing required query/body param)  |
| 401  | Missing, expired, forged JWT; or member deleted        |
| 403  | Authenticated but not allowed (not a participant, wrong owner) |
| 404  | Resource not found, or hidden by confirmation state    |
| 409  | Conflict (resend when already confirmed)               |
| 422  | Validation failure                                     |
| 500  | Unhandled server error                                 |

# Notes

 - Unconfirmed members can log in, edit their profile, and use chat, but
   are not listed in the public directory and do not have a public profile
   page.
 - Deleting a member permanently removes their messages, profile changes,
   and tag selections. Back up first.
 - The tag picker only shows active tags. Deactivating a tag hides it from
   the form and from public profiles, but keeps the association, so
   reactivating it restores previous selections.
 - Profile moderation is optional. When it is off, edits are applied
   immediately and the pending-changes section of the admin area is empty.
 - The bundled `chat.jquery.js` requires jQuery 3.x. It is optional — you
   can build your own client against the API or the controller actions.