# ERP Laravel Functionality Explainer Agent

## Role

You are the functionality explainer agent for this Laravel ERP API project. Your job is to explain what the system does, how features connect, what permissions are required, and where the relevant code lives.

Answer in Romanian by default, unless the user asks for another language. Be concrete and source-grounded: mention controllers, services, routes, jobs, migrations, and tests when useful. Do not invent frontend behavior; this repository is primarily a Laravel API backend.

## Maintenance Rule

This file must be updated every time a new functionality, endpoint, scheduled job, domain workflow, notification, permission, table, or externally visible behavior is added or changed. The implementation agent must keep this explainer aligned with the current code before finishing the feature.

For project-wide implementation rules, use `docs/project-rules-agent.md`.

## Project Overview

This project is a modular Laravel ERP-style API with:

- multi-organization tenant isolation
- bearer-token authentication
- rights and groups based authorization
- users, administrators, clients, locations, and location groups
- subscriptions and subscription assignment lifecycle
- events, generated occurrences, and participants
- articles/announcements with audience segmentation and read receipts
- custom fields per entity type
- payments with provider callbacks and receipts
- SMS logging and SMS portal integration
- generic notification delivery layer for SMS, mail, and push
- audit/business activity logging
- scheduled jobs and console commands
- OpenAPI annotations for API documentation

## Core Architecture

### Routing

API routes are split by module and included from `routes/api.php`:

- `routes/user.php`
- `routes/subscription.php`
- `routes/event.php`
- `routes/article.php`
- `routes/custom-fields.php`
- `routes/sms.php`
- `routes/payment.php`

Most endpoints are protected by `auth.bearer`. Permission checks use the custom middleware `right:...`.

### Authentication

Authentication uses custom bearer tokens, not Laravel Sanctum.

Main files:

- `app/Users/Http/Controllers/Api/AuthController.php`
- `app/Users/Http/Middleware/AuthenticateBearerToken.php`
- `app/Users/Services/BearerTokenService.php`
- `app/Users/Models/PersonalAccessToken.php`
- `database/migrations/2026_05_15_000000_create_personal_access_tokens_table.php`

Flow:

1. `POST /api/login` validates email, organization, and password.
2. A personal access token is created and returned.
3. Authenticated endpoints require `Authorization: Bearer <token>`.
4. `POST /api/logout` revokes the current token.

The project also supports `GET /api/organizations/slug/{slug}` to resolve organization details before login.

### Authorization

Authorization is based on rights attached to groups, and groups attached to users.

Main files:

- `app/Users/Models/User.php`
- `app/Users/Models/Group.php`
- `app/Users/Models/Right.php`
- `app/Users/Http/Middleware/RequireRight.php`
- `app/Users/Services/OrganizationAccessService.php`
- `config/organization-access.php`

Important behavior:

- `right:a,b` means the user needs at least one of the listed rights.
- Rights can be disabled per organization through `OrganizationAccessService`.
- Admin-style APIs generally use rights such as `users.view`, `users.manage`, `subscriptions.manage`, `events.manage`, etc.

### Multi-Organization Isolation

Most business records have `organization_id`.

Main files:

- `database/migrations/2026_05_26_000010_add_organizations_layer.php`
- `app/Users/Models/Concerns/SetsOrganizationFromAuthenticatedUser.php`
- `app/Users/Models/Concerns/BelongsToAuthenticatedOrganization.php`
- `app/Users/Models/Organization.php`

Behavior:

- New records often inherit the authenticated user's organization automatically.
- Queries are scoped to the authenticated organization where models use the organization concern.
- Some internal jobs use `withoutGlobalScopes()` when they intentionally need cross-scope lookup.

## Functional Modules

### Users, Administrators, Clients

Main controller:

- `app/Users/Http/Controllers/Api/UserController.php`

Main routes:

- `GET /api/users`
- `GET /api/administrators`
- `GET /api/clients`
- `GET /api/users/search/user-code`
- `POST /api/users`
- `GET /api/users/{user}`
- `PUT/PATCH /api/users/{user}`
- `DELETE /api/users/{user}`
- `PATCH /api/users/subscription/{user}`
- `GET /api/users/{user}/activity`

Functional behavior:

- Users can be listed, filtered by search, viewed, created, updated, and deleted.
- Administrators are users with groups, excluding users who only have `profile.view`.
- Clients are users with no groups or only `profile.view`.
- Users can have groups, locations, subscriptions, notification consents, push tokens, and user codes.
- Users cannot delete their own account.
- User visibility is affected by location access scope.
- Subscription sync detaches old subscriptions, attaches new ones, calculates expiration, logs activity, and dispatches subscription activation notifications.

### Profile: `/me`

Main controller:

- `app/Users/Http/Controllers/Api/MeController.php`

Routes:

- `GET /api/me`
- `PATCH /api/me/password`
- `GET /api/me/custom-fields`
- `GET /api/me/events`
- `GET /api/me/subscriptions`

Functional behavior:

- Authenticated users can inspect their own profile.
- They can update their password.
- They can retrieve their custom fields, registered events, and subscriptions.

### Groups and Rights

Main controllers:

- `app/Users/Http/Controllers/Api/GroupController.php`
- `app/Users/Http/Controllers/Api/RightController.php`

Routes:

- `/api/groups`
- `/api/rights`

Functional behavior:

- Groups are organization-scoped and can contain rights.
- Rights are global after later migrations.
- Group create/update rejects disabled rights for the current organization.
- Rights can be listed, created, updated, and deleted by authorized users.

### Locations and Location Groups

Main controllers:

- `app/Users/Http/Controllers/Api/LocationController.php`
- `app/Users/Http/Controllers/Api/LocationGroupController.php`

Routes:

- `/api/locations`
- `/api/location-groups`

Functional behavior:

- Locations are organization-scoped.
- Location groups organize locations.
- Users can be attached to locations.
- Location access can restrict which users/data are visible.

### Subscriptions

Main controller:

- `app/Subscription/Http/Controllers/Api/SubscriptionController.php`

Main model:

- `app/Subscription/Models/Subscription.php`

Routes:

- `GET /api/subscriptions`
- `POST /api/subscriptions`
- `GET /api/subscriptions/{subscription}`
- `PUT/PATCH /api/subscriptions/{subscription}`
- `DELETE /api/subscriptions/{subscription}`
- `POST /api/subscriptions/{subscription}/restore`
- `PATCH /api/subscriptions/{subscription}/toggle-active`

Functional behavior:

- Subscriptions are organization-scoped.
- Subscriptions have name, description, price, currency, duration in days, maximum users, active flag, timestamps, and soft delete.
- `billing_interval` and `trial_days` were removed by migration `2026_05_18_000002_remove_billing_interval_and_trial_days_from_subscriptions_table.php`.
- Users are attached to subscriptions through `subscription_user`.
- `subscription_user` stores `start_date` and `expires_at`.
- Active user subscriptions are determined by active subscription status and date range.

### Events and Occurrences

Main controllers:

- `app/Events/Http/Controllers/Api/EventController.php`
- `app/Events/Http/Controllers/Api/EventOccurrenceController.php`
- `app/Events/Http/Controllers/Api/EventParticipantController.php`

Main services:

- `app/Events/Services/EventOccurrenceGeneratorService.php`
- `app/Events/Services/EventEligibilityService.php`

Routes:

- `GET /api/events`
- `POST /api/events`
- `GET /api/events/{event}`
- `PUT/PATCH /api/events/{event}`
- `DELETE /api/events/{event}`
- `GET /api/events/{event}/occurrences`
- `GET /api/event-occurrences/{occurrence}`
- `GET /api/event-occurrences/{occurrence}/participants`
- `POST /api/event-occurrences/{occurrence}/participants`
- `PUT/PATCH /api/event-occurrences/{occurrence}/participants/{user}`
- `DELETE /api/event-occurrences/{occurrence}/participants/{user}`

Functional behavior:

- Events can be one-time, weekly, or monthly.
- Creating an event generates initial occurrences.
- Updating schedule-related fields regenerates future open occurrences.
- Deleting an event removes future occurrences without participants and cancels future occurrences with participants.
- Events can require active subscriptions and/or payment.
- Events can require a specific subscription.
- Participants are attached through `event_occurrence_user`.
- Participant status can be managed.
- When schedule changes or an inactive event resumes, notifications are dispatched to affected participants.

### Articles and Announcements

Main controller:

- `app/Articles/Http/Controllers/Api/ArticleController.php`

Main model:

- `app/Articles/Models/Article.php`

Routes:

- `GET /api/articles`
- `POST /api/articles`
- `GET /api/articles/{article}`
- `PUT/PATCH /api/articles/{article}`
- `DELETE /api/articles/{article}`
- `GET /api/articles-feed`
- `POST /api/articles/{article}/view`

Functional behavior:

- Articles have title, description, status, publish date, expiration date, priority, audience segment, and author.
- Statuses: `draft`, `scheduled`, `published`, `expired`.
- Audience segments: `all_users`, `active_subscribers`, `expired_users`, `groups`, `locations`.
- Feed visibility is calculated per user using organization, publication status, dates, segment, group membership, location membership, and subscription status.
- `GET /api/articles-feed` records delivery receipts.
- `POST /api/articles/{article}/view` records view time.
- Receipts are stored in `article_user_receipts`.
- Scheduled job `TransitionArticlePublicationStatus` publishes scheduled articles and expires outdated articles.

### Custom Fields

Main controllers:

- `app/CustomFields/Http/Controllers/Api/CustomFieldController.php`
- `app/CustomFields/Http/Controllers/Api/CustomFieldValueController.php`

Main services:

- `app/CustomFields/Services/CustomFieldDefinitionService.php`
- `app/CustomFields/Services/CustomFieldValueService.php`

Routes:

- `GET /api/custom-fields`
- `POST /api/custom-fields`
- `GET /api/custom-fields/{customField}`
- `PUT/PATCH /api/custom-fields/{customField}`
- `DELETE /api/custom-fields/{customField}`
- `GET /api/{entityType}/{entityId}/custom-field-values`
- `POST /api/{entityType}/{entityId}/custom-field-values`

Functional behavior:

- Organizations can define custom fields for supported entity types.
- Custom fields are cached per organization and entity type.
- Values are stored separately and typed into dedicated value columns.
- The values API loads definitions and saved values for an entity.
- The save API validates/saves submitted custom field values.

### Payments

Main controller:

- `app/Payments/Http/Controllers/Api/PaymentController.php`

Main services:

- `app/Payments/Services/PaymentService.php`
- `app/Payments/Services/ReceiptService.php`

Routes:

- `GET /api/payments`
- `POST /api/payments`
- `PATCH /api/payments/{payment}/attach-model`
- `GET /api/payments/{payment}/receipt`
- `POST /api/payments/callback`

Functional behavior:

- Payments can be cash, card, or bank transfer.
- Payments can attach to subscription assignments or event participant assignments.
- Payable model types:
  - subscription assignment: `subscription_user`
  - event occurrence participant: `event_occurrence_user`
- Payment creation verifies the payable record belongs to the authenticated organization.
- Cash payments are immediately confirmed.
- Non-cash payments start as initiated and are updated by callback.
- Confirmed subscription payments activate the related subscription assignment and calculate expiration based on subscription duration.
- Confirmed payments receive a receipt number.
- Receipt download is allowed only for confirmed payments with receipt numbers.
- Callback processing is idempotent and handles terminal statuses.
- Callback signatures use `services.payments.callback_secret`.

### SMS

Main controller:

- `app/Sms/Http/Controllers/Api/SmsMessageController.php`

Main service:

- `app/Sms/Services/SmsPortalService.php`

Route:

- `GET /api/sms-messages`

Functional behavior:

- SMS messages are logged in `sms_messages`.
- SMS list supports filters by subscription, user, status, dates, and search.
- `SmsPortalService` sends messages to the configured SMS portal endpoint.
- It converts Romanian/non-ASCII text to plain ASCII when required by provider config.
- The older subscription expiration SMS job stores and updates `SmsMessage` rows.

### Notifications

Main files:

- `app/Notifications/Events/NotificationRequested.php`
- `app/Notifications/Listeners/QueueNotificationDeliveries.php`
- `app/Notifications/Jobs/SendNotificationDelivery.php`
- `app/Notifications/Services/NotificationSender.php`
- `app/Notifications/Jobs/DispatchSubscriptionLifecycleNotifications.php`
- `config/notifications.php`
- `database/migrations/2026_08_06_000001_create_notification_layer.php`

Functional behavior:

- Feature code dispatches `NotificationRequested`.
- The listener checks user consent for `sms`, `mail`, and `push`.
- For every allowed channel, it creates one `notification_deliveries` row.
- Unique key `event_key + user_id + channel` prevents duplicate sends for the same event/channel.
- New deliveries dispatch `SendNotificationDelivery`.
- `SendNotificationDelivery` creates `notification_attempts`, sends through `NotificationSender`, and updates delivery status.
- Failed sends are retried by Laravel queue using configured tries/backoff.
- Templates live in `config/notifications.php` and use placeholders like `:subscription`, `:expires_at`, `:event`, `:message`.

Known overlap:

- `DispatchSubscriptionLifecycleNotifications` is the new generic notification job.
- `App\Subscription\Jobs\SendExpiringSubscriptionSms` is an older SMS-specific job.
- Both are scheduled in `routes/console.php`, so subscription expiration can be handled by two systems if both remain active.

### Audit and Business Activity

Main files:

- `app/Users/Models/AuditLog.php`
- `app/Users/Services/BusinessActivityLogger.php`
- `app/Users/Models/Concerns/LogsModelChanges.php`
- `database/migrations/2026_05_26_000012_create_audit_logs_table.php`
- `database/migrations/2026_08_06_000001_extend_audit_logs_for_business_activity.php`

Functional behavior:

- Model changes are logged for models using `LogsModelChanges`.
- Business events include user creation/update/delete, subscription assignment/renewal/suspension, payment recorded, approval granted, card issued, and SMS sent.
- Sensitive fields such as passwords, tokens, CNP/personal numeric code, authorization values, and secrets are removed from logged payloads.
- User activity can be retrieved through `GET /api/users/{user}/activity`.

## Scheduled Jobs and Commands

Scheduled in `routes/console.php`:

- `DispatchSubscriptionLifecycleNotifications`: daily at 08:00, sends generic subscription lifecycle notifications.
- `SendExpiringSubscriptionSms`: daily, sends legacy SMS subscription expiration notices.
- `TransitionArticlePublicationStatus`: every minute, publishes scheduled articles and expires old articles.

Console commands:

- `CreateOrganizationAdmin`: creates an organization/admin bootstrap account and rights.
- `DeleteOrganisation`: deletes organization-owned data in a controlled order.
- `SeedUsers`: seeds users.
- `SendExpiringSubscriptionSms`: command wrapper for the expiring SMS job.

## Data Model Cheat Sheet

Important tables:

- `organizations`
- `users`
- `personal_access_tokens`
- `groups`
- `rights`
- `group_right`
- `group_user`
- `locations`
- `location_user`
- `location_groups`
- `subscriptions`
- `subscription_user`
- `events`
- `event_occurrences`
- `event_occurrence_user`
- `articles`
- `article_group`
- `article_location`
- `article_user_receipts`
- `custom_fields`
- `custom_field_values`
- `payments`
- `sms_messages`
- `audit_logs`
- `notification_deliveries`
- `notification_attempts`

## Explanation Style

When explaining a feature:

1. Start with what the feature does in business terms.
2. List the API endpoints involved.
3. Explain permissions.
4. Explain the main data tables.
5. Explain side effects: notifications, audit logs, jobs, payments, receipts.
6. Mention the exact files that implement it.
7. Mention edge cases and known risks if visible in the code.

Example response shape:

```text
Funcționalitatea X permite ...

Endpoint-uri:
- METHOD /api/...

Permisiuni:
- ...

Flux:
1. ...
2. ...
3. ...

Persistență:
- tabela ...

Cod relevant:
- path/to/file.php

Observații:
- ...
```

## Known Implementation Notes

- The project has OpenAPI annotations in each module under `OpenApi`.
- Most feature behavior is covered by feature tests under `tests/Feature`.
- Tests currently run through PHP/Laravel, likely inside the Docker `app` service.
- On Windows host sessions without `php` in PATH or Docker daemon access, tests cannot be run directly.
- Keep explanations aligned with the current migrations, not older test fixtures or stale comments.
