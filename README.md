Members module
==============

This module provides a shared membership system for your site: member
accounts, authentication, profiles, a public directory, tags, optional
profile moderation, and 1-to-1 private messaging. It ships both a
server-rendered site area (Bootstrap 5 templates) and a JSON API (JWT) for
mobile apps and SPAs. Other modules that need "a logged-in user" build on
top of it.

# Contents

 - [Requirements](#Requirements)
 - [Configuration](#Configuration)
 - [Features](#Features)
 - [How it works](#How-it-works)
 - [Routes](#Routes)
 - [Templates](#Templates)
 - [Services](#Services)
 - [URL generation](#Yrl-generation)
 - [API](#Api)
 - [Localization](#Localization)
 - [Data and storage](#data-and-storage)
 - [Notes](#notes)
 - [Known limitations](#known-limitations)

# Requirements

 - Bono CMS with the `Cms` module loaded (the module uses its language
   manager and mailer)
 - A working mailer configured in the `Cms` module (registration and
   recovery emails)
 - For the bundled templates: Bootstrap 5 and Bootstrap Icons on the page.
   For the bundled chat script: jQuery 3.x

# Configuration

Everything lives in `Config/module.config.php`.

## Moderation

    'moderation' => [
        'enabled' => true,
        'fields' => [
            'reserved'     => ['id', 'password', 'remove_avatar', 'email'],
            'skip'         => ['phone', 'password'],
            'translatable' => ['bio', 'title', 'name'],
            'base'         => ['address', 'avatar', 'company']
        ]
    ]

| Key            | Meaning                                                                          |
|----------------|----------------------------------------------------------------------------------|
| `enabled`      | Master switch. When `false`, every profile edit is applied immediately.          |
| `reserved`     | Fields that never enter the pipeline at all.                                     |
| `skip`         | Base fields applied immediately even when moderation is on.                      |
| `translatable` | Translated fields (per language) that are staged for review.                     |
| `base`         | Base (non-translated) fields that are staged for review.                         |

Important details:

 - Which fields a member may write at all is **hard-coded** in
   `ProfileChangeService`: base fields `phone`, `address`, `country`,
   `company`, `avatar`, and translation fields `name`, `title`, `bio`.
   Anything else submitted is silently dropped. The config buckets only
   decide whether an allowed field is staged or applied immediately; they
   cannot add new writable fields.
 - With the default config, `phone` and `country` are applied immediately
   (they are not in a staged bucket); `address`, `company`, `avatar`,
   `name`, `title` and `bio` are staged.
 - The email address and the password cannot be changed through the profile
   form. The password has its own path and never goes through moderation.

## API

    'api' => [
        'jwt_secret' => 'REPLACE-ME-with-a-long-random-string-at-least-32-chars',
        'jwt_ttl'    => 604800 // seconds, 7 days
    ]

If `jwt_secret` is empty, every protected API request is rejected with
`401`. If it is left as the shipped placeholder, every token is forgeable.
The secret is read from the module config only; there is no environment
variable support.

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
 - Translatable member data (name, title, bio) and tags, via the `Cms`
   language manager

## Member features

 - Confirmed and unconfirmed accounts. Unconfirmed accounts can log in, edit
   their profile and use chat, but do not appear in the public directory and
   have no public profile page. They cannot use password recovery.
 - Avatar with several pre-rendered sizes (64×64, 128×128, 256×256)
 - Translatable `name`, `title` and `bio`
 - Self-selected tags inside admin-defined tag groups
 - Personal dashboard with profile summary
 - After a forced login the member is sent back to the page they originally
   requested
 - Change history visible to administrators

## Chat features

 - Direct (1-to-1) conversations, resolved by member pair
 - Message inbox sorted by most recent activity
 - Unread message counter, per conversation and across all conversations
 - Idempotent message sending (a retried send with the same `client_id`
   does not duplicate)
 - Soft-deleted messages show as a tombstone
 - Polling-based delivery, with adaptive intervals
 - Optional "load earlier" pagination (API)

## Directory features

 - Paginated, filterable list of confirmed members, in random order by
   default
 - Filter by country (ISO 3166-1 alpha-2)
 - Filter by company (exact match)
 - Filter by tags (any-of semantics)
 - Web only: restrict to a set of member ids with `member_ids[]`
 - Public profile pages per member, with tags grouped by tag group

## Administration features

 - Member grid with pagination (unconfirmed members are highlighted)
 - Create members manually, edit, delete
 - View full change history per member
 - Review pending profile changes with an old/new diff and a preview of
   staged avatars
 - Manage tag groups and tags, with translations and an active/inactive flag
 - Deleting a member also removes their avatar files, translations, profile
   change submissions, tag selections and conversation participations
   (see [Notes](#notes) for what happens to their messages)

## Coming soon

 - Group conversations (the schema and mappers are already prepared)
 - Message edit / delete / reply endpoints (service and storage support
   exist, no route exposes them yet)
 - Roles and permissions rework
 - Notification system
 - Profile approvals toggle per member
 - Gamification hooks

# How it works

## Registration and confirmation

1. The visitor posts `translation[<langId>][name]`, `member[email]` and
   `member[password]`. Email must be unique, password at least 6
   characters, name at least 2.
2. The member is created **unconfirmed** and **unsubscribed**, with a fresh
   activation key, and a confirmation email is sent. The web registration
   logs (but does not surface) a mail failure.
3. The link in the email points at the **web** route
   `Members:Register@confirmAction`; there is no confirmation endpoint in
   the API.
4. A logged-in, unconfirmed member can request a new email through
   `Members:Register@resendAction` (web) or
   `POST /api/v1/members/register/resend` (API).

## Login and password recovery

 - Login is by **email** and password. (The form field is named
   `identifier`.)
 - Recovery sends a link to the account's email. The response is identical
   whether or not the email exists, to prevent account enumeration.
 - Opening the link **immediately resets the password** to a random
   8-character value and displays it on the page; the member then changes it
   from their profile. Only confirmed accounts can be recovered.

## Profile editing and moderation

A profile edit (`Members:Profile@updateAction`, `POST /api/v1/members/me/update`)
is processed field by field:

 1. Fields outside the writable allow-list are dropped.
 2. Fields whose value did not change are ignored.
 3. With moderation **off**, the rest is applied immediately.
 4. With moderation **on**, fields in the `base` / `translatable` buckets are
    staged as a single submission holding both the old and the new value of
    every field; the others are applied immediately.

The member sees one of three messages: *"Your profile has been updated."*,
*"Your changes were submitted for review."* or *"Some changes were applied
immediately; the rest are pending review."*

An administrator approves or rejects each submission (rejection takes an
optional reason). Approval applies the staged fields in a transaction;
rejection applies nothing and discards a staged avatar file. A member may
have several pending submissions at once; submitting again never replaces
an earlier one.

Staged avatars are stored in `/data/uploads/module/members/pending/` and
moved into the live avatar store on approval.

Edits made by an administrator in the admin form are written directly, bypass
moderation, and do not appear in the change history.

The tag selection is a direct write (never moderated), in both the web form
and the API. Submitted tag ids are sanitized against the set of **active**
tags.

## Languages and translations

`name`, `title` and `bio` are stored per language. Public reads (directory,
public profile, inbox) use an inner join on the active language, so a member
only appears for a language in which a translation row exists. A member
who registered only in the default language will not be listed when the
directory is requested in another language. The same applies to tags and
tag groups. Reading *your own* profile falls back to the base row when no
translation exists (name becomes empty and the display name falls back to
the email).

Web translation forms post `translation[<langId>][...]`. The API additionally
accepts `translation[<languageCode>][...]` and, on profile update, keeps only
the language selected by `?lang=`.

## Tags

Tags are organised in groups (`Members:Admin:TagGroup`, `Members:Admin:Tag`).
Both have a stable `code` (unique), a sort order and translations. A tag also
has an active flag: deactivating it hides it from the picker, the directory
filter and public profiles, but keeps the associations, so reactivating it
restores earlier selections. Deleting a group deletes its tags and every
member selection of them.

## Chat

 - A direct conversation is identified by the key `minId:maxId` (unique
   index), so the same pair always resolves to the same conversation, even
   under concurrent requests.
 - Message body is trimmed; empty bodies and bodies over 5000 characters are
   rejected.
 - Sending marks the conversation read for the sender. Fetching a thread or
   polling marks it read up to the newest returned message. The read cursor
   never moves backwards.
 - The inbox excludes archived conversations.
 - Page size defaults to 50 messages (maximum 100 in the service; the
   endpoints do not expose a limit parameter).
 - Message `type` (`text`, `image`, `file`, `system`), `reply_to_id`,
   edits, soft delete, muting and archiving exist in the schema and the
   service layer but are not yet reachable from any route.

# Routes

## Site (web)

| URL                                    | Action                       | Guard        |
|----------------------------------------|------------------------------|--------------|
| `/member/login`                        | `Auth@indexAction`           | guest only   |
| `/member/login/submit`                 | `Auth@loginAction`           | guest only   |
| `/member/logout`                       | `Auth@logoutAction`          | login        |
| `/member/register`                     | `Register@indexAction`       | guest only   |
| `/member/register/submit`              | `Register@saveAction`        | guest only   |
| `/member/register/success`             | `Register@successAction`     | guest only   |
| `/member/register/confirm/(:var)`      | `Register@confirmAction`     |              |
| `/member/register/resend`              | `Register@resendAction`      | login        |
| `/member/recovery`                     | `Recovery@indexAction`       |              |
| `/member/recovery/process`             | `Recovery@processAction`     | guest only   |
| `/member/recovery/success`             | `Recovery@successAction`     |              |
| `/member/recovery/confirm/(:var)`      | `Recovery@resetAction`       |              |
| `/member/profile`                      | `Profile@indexAction`        | login        |
| `/member/profile/edit`                 | `Profile@editAction`         | login        |
| `/member/profile/update`               | `Profile@updateAction`       | login        |
| `/members`                             | `Directory@indexAction`      |              |
| `/member/profile/(:var)`               | `Directory@profileAction`    |              |
| `/member/messages`                     | `Chat@inboxAction`           | login        |
| `/member/messages/with/(:var)`         | `Chat@withAction`            | login        |
| `/member/messages/converstation/(:var)`| `Chat@conversationAction`    | login        |
| `/member/messages/send`                | `Chat@sendAction`            | login        |
| `/member/messages/poll`                | `Chat@pollAction`            | login        |

"login" means `AuthRequiredMiddleware` (redirects to the login page and
remembers the requested URI; also logs out sessions whose member row was
deleted). "guest only" means `GuestOnlyMiddleware` (redirects logged-in
members to their profile). Always generate URLs through the action names
(see [URL generation](#url-generation)) rather than hard-coding paths.

Site forms are submitted by the theme's AJAX handler and answered with JSON
directives, always with HTTP 200: `{ "errors": ... }`, `{ "redirect": "..." }`
or `{ "refresh": true }`. The web chat endpoints answer `{ "error": "..." }`
(also HTTP 200) on failure.

## Administration

All under `/<admin>/module/members`:

| Path                                  | Action                      |
|---------------------------------------|-----------------------------|
| ``                                    | Member grid                 |
| `/add`, `/edit/(:var)`, `/save`, `/delete/(:var)` | Create / edit / delete a member |
| `/history/(:var)`                     | Change history of a member  |
| `/changes`                            | Pending changes             |
| `/changes/approve/(:var)`, `/changes/reject/(:var)` | Review a submission (POST) |
| `/tags`, `/tags/add`, `/tags/edit/(:var)`, `/tags/save`, `/tags/delete/(:var)` | Tag groups |
| `/tags/group/(:var)`, `/tags/group/(:var)/add` | Tags of a group    |
| `/tags/tag/edit/(:var)`, `/tags/tag/save`, `/tags/tag/delete/(:var)` | Edit a tag |

The admin menu entries are *View all members*, *Review pending changes* and
*Manage tags*.

# Templates

The module ships a complete set of Bootstrap 5 templates in
`View/Template/site/`, wrapped by `__layout__.phtml`. Email bodies live in
`View/Template/messages/`. The filenames below are the ones the controllers
look up.

## Variables available in every site template

Controllers add these to every page rendered on a GET request:

    $languages            // Language entities (active languages)
    $isLoggedIn           // bool
    $meId                 // int, current member id (0 when logged out)
    $unreadMessagesCount  // int, unread messages across all conversations

Each page also receives a `$page` entity (title and SEO metadata). The layout
reads `$page->getTitle()`, `getMetaDescription()`, `getMetaKeywords()` and
`getSeo()`, and expects a `$csrfToken` (emitted as a `csrf-token` meta tag,
which the chat script reads).

Note on the name `$member`: the `SiteBootstrapper` registers a **global**
`$member` (a `SiteService`, see [Services](#services)). The module's own
pages that deal with a member (dashboard, profile edit, public profile,
directory rows) override `$member` with a `MemberEntity`.

## Member login template

`member-login.phtml`. Receives `$page`. The form posts two fields:
`identifier` (the email address) and `password`.

## Member registration template

`member-register-form.phtml`. Receives `$page` and `$defaultLangId`. The form
posts `translation[<defaultLangId>][name]`, `member[email]` and
`member[password]`. On success the visitor is redirected to
`member-register-success.phtml` and a confirmation email is sent.

## Confirmation, recovery and success pages

| Template                          | Variables                                         |
|-----------------------------------|---------------------------------------------------|
| `member-register-success.phtml`   | `$page`                                           |
| `member-confirmation.phtml`       | `$page`, `$confirmed` (bool)                      |
| `member-recovery-form.phtml`      | `$page`; posts `email`                            |
| `member-recovery-success.phtml`   | `$page`                                           |
| `member-recovery-reset.phtml`     | `$page`, `$password` (the new password, or `false` when the key is invalid) |

Email templates (`messages/register.phtml` receives `$name` and `$link`;
`messages/recovery-confirm.phtml` receives `$link`).

## Member profile (dashboard) template

`member-dashboard.phtml`. Receives:

    $member       // MemberEntity of the logged-in member
    $tagGroups    // The member's selected tags, grouped by tag group
    $page         // Page entity

The `$member` entity provides:

    $member->getId();
    $member->getName();
    $member->getDisplayName();   // name, else email, else "#<id>"
    $member->getEmail();
    $member->getPhone();
    $member->getCompany();
    $member->getCountry();       // ISO 3166-1 alpha-2 code
    $member->getCountryName();   // human-readable name, or null
    $member->getAddress();
    $member->getTitle();
    $member->getBio();
    $member->getConfirmed();
    $member->getSubscriber();
    $member->getCreatedAt();
    $member->hasAvatar();
    $member->getAvatarUrl('256x256'); // 64x64, 128x128, 256x256 are generated

`MemberEntity` also defines the constants `AVATAR_THUMB` (`64x64`),
`AVATAR_SMALL` (`256x256`) and `AVATAR_PROFILE` (`256x256`).

## Member profile edit template

`member-profile.phtml`. It combines the editable profile fields and the tag
picker in a single form. It receives:

    $member       // Array of translation entities (one per language), or one entity
    $pending      // Pending submissions of this member (empty if moderation is off)
    $payload      // Tag picker payload: [ ['group' => ..., 'tags' => [...]], ... ]
    $selected     // Array of selected tag ids
    $page         // Page entity

The form posts `member[...]` base fields (`phone`, `country`, `company`,
`address`, `remove_avatar`, `password`), the avatar as the file field `file`,
`translation[<langId>][name|title|bio]` and `tags[]`. If moderation is
enabled, some changes are staged for review rather than applied immediately.

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

`$block['group']` carries `id`, `code`, `sort_order`, `name`, `description`;
each tag carries `id`, `group_id`, `code`, `sort_order`, `is_active`,
`label`. Escape these values in your own templates.

## Member public profile template

`member-public-profile.phtml`. Rendered for a confirmed member's public page.
It receives:

    $member       // MemberEntity
    $tagGroups    // Selected tags grouped by tag group
    $page         // Page entity

Only confirmed members have a public profile. Visiting the URL of an
unconfirmed member (or of a member with no translation in the active
language) returns "not found". Never print the email, phone or address in
this template.

## Member directory template

`member-directory.phtml`. It receives:

    $members       // Paginated list of confirmed MemberEntity objects
    $paginator     // Paginator instance
    $total         // Total number of matching members
    $countries     // Countries that have at least one confirmed member
    $companies     // Array of distinct company names
    $payload       // Tag picker payload
    $filterCountry // Currently applied country filter (or null)
    $filterCompany // Currently applied company filter (or null)
    $filterTags    // Currently applied tag ids

The page shows 21 members per page. A typical directory card renders the
member's display name, company, country and avatar. Use
`$member->getDisplayName()` so rows never render blank. Members are returned
in random order.

## Member inbox template

`member-inbox.phtml`. It receives:

    $conversations // Array of shaped conversation rows
    $page          // Page entity

Each conversation row contains:

    [
        'id'            => int,
        'type'          => 'direct' | 'group',
        'displayName'   => string,
        'other'         => null | ['id' => int, 'name' => string, 'avatar' => string|null],
        'lastMessage'   => null | ['text' => string, 'createdAt' => string, 'senderId' => int],
        'lastMessageAt' => string|null, // ISO 8601, UTC
        'messageCount'  => int,
        'unreadCount'   => int,
        'mutedUntil'    => string|null,
    ]

## Member conversation template

`member-conversation.phtml`. It renders a single chat thread. It receives:

    $conversationId // int
    $messages       // Raw message rows from the service
    $senders        // Lookup table of sender ids → member rows
    $meId           // The current member's id
    $otherMember    // MemberEntity of the other participant, or false
    $page           // Page entity

The chat form must carry the following `data-*` attributes for the bundled
`chat.jquery.js` to work without configuration:

    <form id="chat-form"
          data-conversation="<?= $conversationId; ?>"
          data-send-url="<?= $this->url('Members:Chat@sendAction'); ?>"
          data-poll-url="<?= $this->url('Members:Chat@pollAction'); ?>"
          data-me-id="<?= $meId; ?>"
          data-last-id="<?= $messages ? (int) end($messages)['id'] : 0; ?>"
          data-translations='{"[deleted]": "..."}'>
        <textarea name="body"></textarea>
        <button type="submit">Send</button>
    </form>

`data-translations` is optional (JSON; used for the `[deleted]` tombstone
label). The thread container must have the id `#thread`.

## Chat JavaScript

The module ships `Assets/chat.jquery.js`, which:

 - Auto-initializes on any `#chat-form` element
 - Sends with `POST` and polls with `GET` (`conversation_id`, `since`) against
   the form's `data-send-url` / `data-poll-url`
 - Sends the CSRF token from `<meta name="csrf-token">` as `csrf-token`
 - Pauses polling when the tab is hidden and catches up when it returns
 - Polls adaptively: 2 s for three polls after a send, 10 s while active,
   30 s after 60 s of silence, 60 s after a failed request
 - Uses a `crypto.randomUUID()` client id (with a fallback) for idempotent
   sends
 - Sends on Enter, inserts a new line on Shift+Enter

Include it after jQuery 3.x. In a module template use the asset helper:

    <?php $this->getPluginBag()->appendScript($this->moduleAsset('chat.jquery.js', 'Members')); ?>

To disable auto-init and construct instances manually, set
`window.Chat.autoInit = false` before loading the script. Selectors, styles,
intervals and timeout can be overridden through the constructor options.

The module also ships `Assets/site.style.css` (avatar sizes, thread height,
status icons), which the bundled layout appends automatically.

# Services

Registered by the module and fetched in controllers with
`$this->getModuleService('<name>')`:

| Name                   | Class                                    |
|------------------------|------------------------------------------|
| `memberManager`        | `Members\Service\MemberManager`          |
| `siteService`          | `Members\Service\SiteService`            |
| `profileChangeService` | `Members\Service\ProfileChangeService`   |
| `chatService`          | `Members\Service\ChatService`            |
| `tagGroupService`      | `Members\Service\TagGroupService`        |
| `tagService`           | `Members\Service\TagService`             |
| `memberTagService`     | `Members\Service\MemberTagService`       |
| `inputNormalizer`      | `Members\Service\InputNormalizer`        |

## Site service (global `$member` in templates)

`siteService` is exposed to every site template as the global `$member`
(unless a page overrides it with an entity, see above):

    // TRUE when a member is logged in.
    $member->isLoggedIn();

    // TRUE when the logged-in member has confirmed their email.
    $member->isConfirmed();

    // Returns the session member data as an array, or a single key
    // when one is passed: $member->getMember('id'), etc.
    // Session keys: id, name, email, confirmed.
    $member->getMember();

    // Fetches any member by id (as a MemberEntity) in the current language.
    // Falls back to the base row when no translation exists.
    $member->fetchMemberById($id);

## Member manager

Used by controllers for registration, login, recovery, profile reads and the
directory:

    $memberManager->login($email, $password);          // writes the session
    $memberManager->logout();
    $memberManager->isLoggedIn();
    $memberManager->isConfirmed();
    $memberManager->getMember($key = null);
    $memberManager->fetchById($id, $withTranslations = false);
    $memberManager->fetchByIdForCurrentLang($id);
    $memberManager->fetchPublicProfile($id);           // confirmed members only
    $memberManager->fetchFiltered($page, $perPage, $filters, $random = true);
    $memberManager->fetchAvailableCountries();
    $memberManager->fetchDistinctCompanies();
    $memberManager->updatePassword($id, $plain);       // bypasses moderation
    $memberManager->initiateRecovery($email);          // key or false
    $memberManager->resetPassword($key);               // new password or false
    $memberManager->confirm($key);

`fetchFiltered()` accepts the filters `country`, `company`, `tagIds` and
`memberIds`, all optional and AND-combined.

## Chat service

The chat service owns every rule of the chat pipeline. Controllers and API
endpoints talk only to it:

    // The member's inbox.
    $chatService->fetchInbox($memberId);

    // Total unread count across all conversations.
    $chatService->countUnreadForMember($memberId);

    // Resolve or create a direct conversation with another member.
    // Throws InvalidArgumentException when the two ids are the same.
    $chatService->findOrCreateDirect($memberId, $otherId);

    // Participant checks.
    $chatService->isParticipant($conversationId, $memberId);
    $chatService->findConversationForMember($conversationId, $memberId); // row or false
    $chatService->findOtherMember($conversationId, $memberId);

    // Fetch a thread (ascending by id).
    $chatService->fetchThread($conversationId, $memberId, $sinceId = 0, $limit = 50);

    // Fetch a thread and advance the read cursor.
    $chatService->fetchThreadAndMarkRead($conversationId, $memberId, $sinceId = 0, $limit = 50);

    // Older messages for "load earlier" (descending by id).
    $chatService->fetchBefore($conversationId, $memberId, $beforeId, $limit = 50);

    // Send a message. Pass a client_id for idempotent retries.
    // Throws InvalidArgumentException (not a participant) or
    // RuntimeException (empty / too long).
    $chatService->sendMessage($conversationId, $senderId, $body, $clientId = null);

    // Resolve sender rows for a batch of messages: [memberId => row].
    $chatService->resolveSenders($messages);

    // Not routed yet.
    $chatService->softDeleteMessage($messageId, $memberId);
    $chatService->markReadUpTo($conversationId, $memberId, $messageId);

## Tag services

`memberTagService` is used by the profile edit template and the directory to
populate the tag picker:

    // The picker payload: [ ['group' => row, 'tags' => [row, ...]], ... ]
    // Active tags only; empty groups are skipped.
    $memberTagService->fetchPickerPayload();

    // The ids of the tags a member has selected.
    $memberTagService->fetchSelectedIds($memberId);

    // The tags a member has selected (active only), grouped by tag group,
    // ready for a public profile page.
    $memberTagService->fetchGroupedByMember($memberId);

    // Drops every id that is not an active tag, then replaces the member's selection.
    $memberTagService->sanitizeSelection($tagIds);
    $memberTagService->saveSelection($memberId, $safeTagIds);

`tagGroupService` and `tagService` provide the admin read/write layer
(`fetchAll`, `fetchById`, `save`, `deleteById`, ...).

## Profile change service

Owns the moderation pipeline: `updateProfile()`, `submit()`, `approve()`,
`reject()`, `fetchPending()`, `fetchPendingForMember()`,
`fetchHistoryForMember()`, `countPending()`, `isModerationEnabled()`.

# URL generation

Always generate links from the action names:

    <a href="<?= $this->url('Members:Directory@indexAction'); ?>">Member directory</a>
    <a href="<?= $this->url('Members:Directory@profileAction', [$memberId]); ?>">Public profile</a>

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
conversation id. Messaging yourself is rejected with a flash message.

## Profile and account pages

    <a href="<?= $this->url('Members:Profile@indexAction'); ?>">My account</a>
    <a href="<?= $this->url('Members:Profile@editAction'); ?>">Edit my profile</a>
    <a href="<?= $this->url('Members:Auth@indexAction'); ?>">Log in</a>
    <a href="<?= $this->url('Members:Register@indexAction'); ?>">Register</a>
    <a href="<?= $this->url('Members:Auth@logoutAction'); ?>">Log out</a>

# API

The module exposes a JSON API under `/api/v1/members/` for mobile apps and
SPA clients. It uses JWT tokens instead of cookies.

## Before you enable the API

Replace the placeholder `api.jwt_secret` in `Config/module.config.php`
with a long random string (at least 32 characters). If the secret is left
as the shipped placeholder, every token is forgeable.

## Authentication

Protected endpoints expect a standard bearer token:

    Authorization: Bearer <token>

Tokens are issued by the login endpoint and are valid for `api.jwt_ttl`
seconds (7 days by default). The token carries the member id (`sub`) and name.
There is no refresh flow; the client logs in again when a token expires.

On every protected request the middleware checks the signature, the expiry
and that the member still exists. Because tokens are stateless, a password
change does **not** invalidate tokens that were already issued; only
expiry or deleting the member does.

The API never sets cookies and never redirects. Clients must treat any
`3xx` response as a bug. A missing or invalid token yields `401` with
`{ "error": "Unauthorized" }`.

## Language

Every endpoint accepts an optional `?lang=xx` query parameter (a language
code). Translated fields (member names, titles, bios, tag labels) are
returned in that language. Omitting it uses the site's active language. See
[Languages and translations](#languages-and-translations) for the effect on
listings.

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
or not the email matches an account, to prevent account enumeration. The
email contains a link to the web reset page (see
[Login and password recovery](#login-and-password-recovery)).

**Parameters**

| Name    | Type   | Required | Notes                 |
|---------|--------|----------|-----------------------|
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
  "avatar": "https://example.com/data/uploads/module/members/avatars/42/256x256/abc.jpg"
}
```

`avatar` is `null` when the member has none.

**Errors:** `401` missing, expired, forged token, or the member row was
deleted.

**Endpoint:** `GET /api/v1/members/auth/whoami`

### Register a new member

Create a new member account. The account is unconfirmed until the
confirmation link is clicked. Public. The response does not contain a token;
the member logs in afterwards.

**Parameters**

| Name                          | Type   | Required | Notes                                           |
|-------------------------------|--------|----------|-------------------------------------------------|
| `translation[<langId>][name]` | string | yes      | Min length 2. `<langId>` may also be a language code (`translation[en][name]`) |
| `member[email]`               | string | yes      | Valid, unique email                             |
| `member[password]`            | string | yes      | Min length 6                                    |

**Response 201**

```json
{ "message": "Registration successful. Please check your email to confirm your account." }
```

**Errors:** `422` validation errors, in the standard error shape; `500`
the member could not be saved.

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
    "login": null,
    "email": "jane@example.com",
    "confirmed": true,
    "company": "Acme",
    "phone": "+1 555 0100",
    "country": "us",
    "address": "123 Main St",
    "title": "Engineer",
    "bio": "I build things.",
    "avatar": "https://example.com/data/uploads/module/members/avatars/42/256x256/abc.jpg"
  },
  "tags": {
    "selected": [1, 5, 9],
    "available": [
      {
        "group": { "id": 1, "code": "interests", "name": "Interests", "description": null },
        "tags": [
          { "id": 1, "label": "Hiking" },
          { "id": 2, "label": "Reading" }
        ]
      }
    ]
  },
  "pendingChanges": [
    {
      "id": 17,
      "member_id": 42,
      "status": "pending",
      "payload": "{\"base\":{\"company\":{\"old\":\"\",\"new\":\"Acme\"}},\"translations\":{}}",
      "reviewed_at": null,
      "rejection_reason": null,
      "created_at": "2026-01-14 09:00:00"
    }
  ]
}
```

 - `login` is a legacy field and is always `null`; the schema has no login
   column. Log in with the email address.
 - `country` is the two-letter code as stored (the framework's country list
   uses lowercase codes).
 - Group and tag objects contain further columns (`sort_order`, `code`,
   `is_active`, `lang_id`, ...); clients should only rely on the fields
   shown.
 - `pendingChanges` holds the raw submission rows. `payload` is a JSON
   **string** containing the old and new value of every staged field. The
   list is empty when moderation is disabled.

**Errors:** `401` no token, `404` member not found.

**Endpoint:** `GET /api/v1/members/me`

### Update my profile

Update the authenticated member's profile. Fields go through the moderation
pipeline when moderation is enabled, otherwise they are applied directly.
Protected.

**Parameters**

| Name                                | Type   | Required | Notes                                                           |
|-------------------------------------|--------|----------|-----------------------------------------------------------------|
| `translation[<langId>][name]`       | string | yes      | Min length 2. Moderated by default                              |
| `translation[<langId>][title]`      | string | no       | Moderated by default                                            |
| `translation[<langId>][bio]`        | string | no       | Moderated by default                                            |
| `member[phone]`                     | string | no       | Applied immediately, never staged                               |
| `member[country]`                   | string | no       | Two-letter ISO 3166-1 code; applied immediately by default      |
| `member[company]`                   | string | no       | Moderated by default                                            |
| `member[address]`                   | string | no       | Moderated by default                                            |
| `member[remove_avatar]`             | flag   | no       | Removes the avatar (moderated by default)                       |
| `tags[]`                            | int[]  | no       | Sanitized against the active-tag set; never moderated           |
| `file`                              | file   | no       | Multipart avatar upload; must be an image                       |

`<langId>` may be a language id or a language code. Only the row for the
language selected by `?lang=` (or the active language) is accepted; other
rows are ignored. The email address cannot be changed here. Any other field
is ignored.

**Response 200**

```json
{
  "message": "Your changes were submitted for review.",
  "user": { "...same shape as GET /me..." },
  "pendingChanges": [ { "id": 18, "status": "pending", "created_at": "..." } ]
}
```

`user` always reflects the **live** profile, not staged values.

`message` is one of (translated to the request language):

- `"Your profile has been updated."` — moderation off, or nothing staged
- `"Your changes were submitted for review."` — everything staged
- `"Some changes were applied immediately; the rest are pending review."` — mixed

**Errors:** `401` no token, `422` validation errors.

**Endpoint:** `POST /api/v1/members/me/update`

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
  "avatar": "https://example.com/data/uploads/module/members/avatars/42/256x256/abc.jpg"
}
```

If moderation is enabled, `avatar` still points at the **previous** avatar
(or is `null`) until the change is approved. Rely on `pendingChanges` from
the profile endpoint to show "avatar pending review."

**Errors:** `401` no token, `422` missing or non-image file.

**Endpoint:** `POST /api/v1/members/me/avatar`

### Change my password

Change the authenticated member's password. Bypasses moderation (as on the
web). Protected.

**Parameters**

| Name       | Type   | Required | Notes        |
|------------|--------|----------|--------------|
| `password` | string | yes      | Min length 6 |

**Response 204** — no content on success. (Do not rely on a response body.)

**Errors:** `401` no token, `422` validation errors.

**Endpoint:** `POST /api/v1/members/me/password`

### List members

Paginated, filtered list of **confirmed** members, 20 per page. Public.
Members are returned in **random order**, so consecutive pages are not
guaranteed to be disjoint.

**Parameters**

| Name      | Type   | Required | Notes                                 |
|-----------|--------|----------|---------------------------------------|
| `page`    | int    | no       | Default 1                             |
| `country` | string | no       | Two-letter ISO 3166-1 code            |
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
      "country": "us",
      "bio": "I build things.",
      "avatar": "https://example.com/data/uploads/module/members/avatars/42/256x256/abc.jpg"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 137,
    "totalPages": 7
  },
  "filters": {
    "applied": { "country": "us", "company": null, "tags": [] },
    "available": {
      "countries": ["United States", "United Kingdom"],
      "companies": ["Acme", "Globex"],
      "tags": [
        { "group": { "id": 1, "name": "Interests" }, "tags": [{ "id": 1, "label": "Hiking" }] }
      ]
    }
  }
}
```

`filters.available` is returned on every request so the client can render
the filter UI without a second round-trip. Only countries and companies
that have at least one confirmed member are listed. Email, phone and address
are never included.

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
    "country": "us",
    "bio": "I build things.",
    "avatar": "https://example.com/data/uploads/module/members/avatars/42/256x256/abc.jpg"
  },
  "tags": [
    {
      "group": { "id": 1, "name": "Interests" },
      "tags": [{ "id": 1, "label": "Hiking" }]
    }
  ]
}
```

**Errors:** `400` missing `id`, `404` no such member, member is
unconfirmed, or the member has no translation in the requested language.

**Endpoint:** `GET /api/v1/members/profile?id=42`

### List my conversations (inbox)

The authenticated member's inbox, newest activity first. Protected.

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

Without `before`, returns up to 50 messages in **ascending** id order,
starting from the oldest, and marks the thread read. With `before`, returns up
to 50 messages older than that id in **descending** id order and does **not**
advance the read cursor.

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
| `client_id`       | string | no       | UUID (max 36 chars); idempotent send |

**Response 201**

```json
{ "id": 101 }
```

If `client_id` matches an existing message in the same conversation, the
original id is returned and no new row is inserted. Use this for safe
retries on flaky networks.

**Errors:** `401` no token, `403` not a participant, `422` missing
`conversation_id` / `body` (validation shape), or the body is empty after
trimming or too long (`{ "error": "..." }`, in English).

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
to messages with `_id > since` in ascending order. Advances the read cursor
to the newest returned message. Protected.

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

Note that the **web** poll endpoint (`/member/messages/poll`) is a separate
route used by `chat.jquery.js`: it takes `conversation_id` instead of `id`
and answers `{ "messages": [...] }` instead of `{ "data": [...] }`.

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
    "member[email]": ["This email is already taken"],
    "member[password]": ["..."]
  }
}
```

Error strings returned by chat sending and a few other endpoints are
English and are not translated.

## Status codes

| Code | Meaning                                                |
|------|--------------------------------------------------------|
| 200  | OK                                                     |
| 201  | Created (registration, message send)                   |
| 202  | Accepted (password recovery request)                   |
| 204  | No content (password change)                           |
| 400  | Malformed request (missing required query/body param)  |
| 401  | Missing, expired, forged JWT; or member deleted        |
| 403  | Authenticated but not allowed (not a participant)      |
| 404  | Resource not found, or hidden by confirmation state    |
| 409  | Conflict (resend when already confirmed)               |
| 422  | Validation failure                                     |
| 500  | Unhandled server error                                 |

# Localization

All user-facing strings are translation keys written in English. The module
ships `Translations/ru/messages.php` and `Translations/uz/messages.php`; add
another language by creating `Translations/<code>/messages.php` returning a
`key => translation` array. Validation attribute labels are translated too
(for example `Conversation_id`).

# Data and storage

Tables (all prefixed through the framework's table prefix):

| Table                                              | Purpose                                         |
|----------------------------------------------------|-------------------------------------------------|
| `bono_module_members`                              | Base member row                                 |
| `bono_module_members_translations`                 | `name`, `title`, `bio` per language             |
| `bono_module_members_changes`                      | Moderation submissions (JSON payload, status)   |
| `bono_module_members_conversations`                | Conversations (`direct_key` unique)             |
| `bono_module_members_conversation_participants`    | Participants, read cursor, mute and archive     |
| `bono_module_members_messages`                     | Messages (`client_id` unique per conversation)  |
| `bono_module_members_tag_groups` / `_translations` | Tag groups                                      |
| `bono_module_members_tags` / `_translations`       | Tags                                            |
| `bono_module_members_member_tags`                  | Member ↔ tag selections                         |

Files:

 - `/data/uploads/module/members/avatars/<memberId>/<WxH>/<file>` — live avatars
 - `/data/uploads/module/members/pending/` — avatars awaiting moderation

`jwt_version`, `last_seen_at` and `is_banned` exist on the members table for
future use; nothing reads them yet.

# Notes

 - Unconfirmed members can log in, edit their profile, and use chat, but
   are not listed in the public directory and do not have a public profile
   page. They cannot use password recovery. (The hint in the admin form that
   they "cannot log in" is inaccurate.)
 - Deleting a member permanently removes their profile, translations, avatar
   files, profile changes, tag selections and conversation memberships.
   Messages they sent are **kept**, with the sender cleared (they render with
   `user: null` in the API). Back up first.
 - The tag picker only shows active tags. Deactivating a tag hides it from
   the form and from public profiles, but keeps the association, so
   reactivating it restores previous selections.
 - Profile moderation is optional. When it is off, edits are applied
   immediately and the pending-changes section of the admin area is empty.
 - Registration and recovery links use a random key stored on the member row
   (`activation_key`) that is not cleared after use and has no expiry. Treat
   old emails accordingly.
 - The bundled `chat.jquery.js` requires jQuery 3.x. It is optional — you
   can build your own client against the API or the controller actions.

# Known limitations

Behaviors of this version that integrators should be aware of:

 - **Avatar changes are handled only by the moderation pipeline.** With
   `moderation.enabled` set to `false`, an uploaded avatar is currently not
   processed (neither on the web nor via the API).
 - **Thread endpoints return the oldest 50 messages** when no `before` /
   `since` is given, so a conversation longer than 50 messages needs polling
   with `since` or a different loading strategy on the client.
 - The country lists returned by `fetchAvailableCountries()` and
   `filters.available.countries` are plain name lists rather than
   `code => name` maps, so a country filter built from them cannot map back
   to codes.
 - Message editing, deleting, replies, muting and archiving are not exposed
   through any route.
 - Members without a translation in the requested language are hidden from
   directory listings, public profiles and the admin grid for that language.
 - Tokens cannot be revoked before they expire.
 - The web chat route is spelled `/member/messages/converstation/(:var)`
   (sic). Generate it through `Members:Chat@conversationAction` and it
   keeps working if the spelling is fixed.