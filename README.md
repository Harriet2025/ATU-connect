# ATU Connect — Backend

Pure PHP 8+ / MySQL backend for ATU Connect, built with a hand-rolled MVC
architecture (no framework). Serves the existing HTML/CSS/JS frontend via
JSON APIs consumable with `fetch()`.

## Stack

- PHP 8+ (no Composer, no framework)
- MySQL 8 / MariaDB 10.4+ (PDO, prepared statements only)
- Apache (XAMPP) with `mod_rewrite`

## Folder structure

```
atu-connect/
  config/         app.php, database.php, constants.php
  controllers/    request handlers (Auth, Announcement, Assignment, Course,
                  Message, Notification, Profile, Dashboard, PasswordReset,
                  Document, Event)
  models/         BaseModel-backed data access classes, one per table
  views/          server-rendered fragments, if any
  core/           Router, Database, Auth, Session, Request, Response,
                  Validator, BaseController, BaseModel, ErrorHandler, Autoloader
  helpers/        FileUploader, NotificationDispatcher
  middleware/      AuthMiddleware, RoleMiddleware, CsrfMiddleware
  routes/         routes/api.php — all endpoint registrations
  uploads/
    assignments/       submitted + brief files
    profile_pictures/
    documents/          notice board documents
    logs/               error_YYYY-MM-DD.log, notifications_YYYY-MM-DD.log
  database/
    schema.sql      full DDL + seed data
  api/            reserved for standalone API docs/fixtures
  public/         Apache document root
    index.php       front controller
    cron_reminders.php  standalone script for assignment due-date reminders
    .htaccess       rewrites everything to index.php
  .htaccess       root-level fallback rewrite into /public
  index.html, css/, js/, images/, assets/, icons/   existing frontend
```

## Installation (XAMPP)

1. Copy the `atu-connect/` folder into `C:\xampp\htdocs\`.
2. Start Apache and MySQL from the XAMPP control panel.
3. Open phpMyAdmin (or the `mysql` CLI) and import `database/schema.sql`:
   ```
   mysql -u root -p < database/schema.sql
   ```
   This creates the `atu_connect` database, all tables, and seed data
   (roles + announcement categories).
4. Set your Apache **DocumentRoot** to `atu-connect/public` for the cleanest
   setup — or leave it at `htdocs` and rely on the root `.htaccess` fallback.
5. Environment overrides (optional) — set these as real environment
   variables or edit `config/database.php` / `config/app.php` directly:
   `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_ENV`,
   `APP_DEBUG`, `APP_URL`.
6. Visit `http://localhost/atu-connect/public/` — the root path itself
   returns a `404 Route not found` JSON response (no route is registered
   for `/`), which confirms the front controller is wired correctly. Use
   `/api/...` paths (see API Documentation below) to exercise the API.

## Database schema

See `database/schema.sql` for full DDL. Summary of core entities:

- **Identity**: `roles`, `permissions`, `role_permissions`, `users`
  (includes `phone`, `notify_email`, `notify_sms`, `notify_whatsapp` channel
  preference flags), `students`, `lecturers`, `departments`
- **Academics**: `courses`, `course_registrations`, `assignments`,
  `assignment_submissions`
- **Communication**: `announcements`, `announcement_categories`,
  `announcement_attachments`, `announcement_likes`, `announcement_comments`,
  `conversations`, `conversation_participants`, `messages`, `notifications`
- **Calendar**: `events`
- **Notice board**: `documents`
- **Security/audit**: `user_sessions`, `login_history`,
  `password_reset_tokens`, `activity_logs`
- **Config**: `system_settings`

All tables use `InnoDB`, `utf8mb4`, explicit foreign keys with appropriate
`ON DELETE` behavior, and indexes on frequently-filtered columns
(role, department, due date, publish date, pinned, read/unread).

## Core classes (already implemented)

| Class | Responsibility |
|---|---|
| `Core\Autoloader` | Maps `Namespace\Class` to file paths — no Composer |
| `Core\Database` | Singleton PDO connection (prepared statements enforced) |
| `Core\Router` | Registers `GET/POST/PUT/DELETE` routes with `{param}` support and per-route middleware |
| `Core\Request` | Parses JSON body / form body / query string uniformly |
| `Core\Response` | Consistent `{success, message, data}` JSON envelope |
| `Core\Session` | Secure session bootstrap, idle timeout, CSRF token helpers |
| `Core\Auth` | Login attempt, session-based auth state, role checks |
| `Core\Validator` | Rule-based input validation (`required`, `email`, `min`, `max`, `numeric`, `in`, `date`) |
| `Core\BaseController` | `requireAuth()`, `requireRole()`, `validate()` helpers for controllers |
| `Core\BaseModel` | Generic `find/all/where/create/update/delete` over PDO |
| `Core\ErrorHandler` | Converts PHP errors/exceptions to JSON + writes `uploads/logs/error_*.log` |

## Completed

- `middleware/`: `AuthMiddleware`, `RoleMiddleware`, `CsrfMiddleware`
- `helpers/FileUploader.php` — validated file upload with extension/size
  checks and unique filenames
- `helpers/NotificationDispatcher.php` — creates the in-app `notifications`
  row and, based on the target user's `notify_email`/`notify_sms`/
  `notify_whatsapp` flags, "dispatches" a reminder through each enabled
  channel
- `models/`: `User`, `Student` (+ `studentUserIdsInDepartment()`), `Lecturer`,
  `Department`, `Course`, `CourseRegistration`, `Assignment`,
  `AssignmentSubmission`, `Announcement`, `AnnouncementCategory`,
  `AnnouncementAttachment`, `Conversation`, `Message`, `Notification`,
  `Event`, `Document`
- `controllers/`: `AuthController`, `AnnouncementController`,
  `AssignmentController`, `CourseController`, `MessageController`,
  `NotificationController`, `DashboardController` (student/lecturer/admin),
  `ProfileController`, `PasswordResetController`, `DocumentController`,
  `EventController`
- Full `routes/api.php` registrations for every endpoint below, wired with
  `AuthMiddleware` (all routes except login/register/password reset) and
  `CsrfMiddleware` (all non-GET routes)

### Known TODOs / deviations

- `RoleMiddleware` exists per spec but is not wired into any route — the
  router instantiates middleware with no constructor args, so it cannot
  carry a per-route allowed-roles list. Fine-grained role checks are done
  in each controller action via `BaseController::requireRole()`.
- Announcement/assignment file attachments accept a single file per
  request (no multi-file batches).
- `NotificationDispatcher`'s email/SMS/WhatsApp sends are log-only stubs
  (write to `uploads/logs/notifications_YYYY-MM-DD.log`) pending real
  gateway credentials (e.g. Twilio, Hubtel, WhatsApp Cloud API) — no
  outbound network call is made in this prototype.
- University-wide announcements (`department_id = null`) do not mass-notify
  every student, to avoid an unbounded broadcast loop; only
  department-scoped announcements notify their department's students. A
  real deployment would push broadcasts through a queue/batch job.
- `AssignmentController::remindDueSoon()` is not wired to any route (no
  cron in plain PHP/XAMPP). Run it via `public/cron_reminders.php`:
  - Manually: `php.exe public\cron_reminders.php`
  - Scheduled: create a Windows Task Scheduler task that runs daily,
    Action = Start a program, Program = `C:\xampp\php\php.exe`, Arguments =
    `public\cron_reminders.php`, Start in = the `atu-connect` folder.

## API Documentation

All endpoints return the envelope `{ "success": bool, "message": string, "data": ... }`
(or `"errors"` instead of `"data"` on failure). Non-GET requests must include
the CSRF token as header `X-CSRF-Token`.

| Method | Path | Auth required | Description |
|---|---|---|---|
| POST | `/api/login` | No | Log in with identifier/email + password |
| POST | `/api/register` | No | Create a new account |
| POST | `/api/logout` | Yes | Destroy session |
| GET | `/api/me` | Yes | Current authenticated user |
| POST | `/api/password/forgot` | No | Request a password reset token (email lookup) |
| POST | `/api/password/reset` | No | Reset password using token + email |
| GET | `/api/announcements` | Yes | Feed (filters: `category`, `department`, `search`) |
| GET | `/api/announcements/{id}` | Yes | Single announcement + attachments/comments/likes |
| POST | `/api/announcements` | Yes (announcement roles) | Create announcement (+ optional attachment) |
| PUT | `/api/announcements/{id}` | Yes (author/admin) | Update announcement |
| DELETE | `/api/announcements/{id}` | Yes (author/admin) | Delete announcement |
| POST | `/api/announcements/{id}/like` | Yes | Toggle like |
| POST | `/api/announcements/{id}/comment` | Yes | Add comment |
| POST | `/api/announcements/{id}/pin` | Yes (announcement roles) | Toggle pin |
| GET | `/api/assignments` | Yes | List (filter: `course_id`) |
| POST | `/api/assignments` | Yes (lecturer) | Create assignment |
| GET | `/api/assignments/{id}` | Yes | Single assignment |
| PUT | `/api/assignments/{id}` | Yes (owner/admin) | Update assignment |
| DELETE | `/api/assignments/{id}` | Yes (owner/admin) | Delete assignment |
| GET | `/api/assignments/{id}/submissions` | Yes (lecturer/admin) | List submissions |
| POST | `/api/assignments/{id}/submit` | Yes (student) | Submit/replace a file |
| POST | `/api/submissions/{id}/grade` | Yes (lecturer/admin) | Grade a submission |
| GET | `/api/courses` | Yes | List courses |
| GET | `/api/courses/{id}` | Yes | Single course |
| POST | `/api/courses/{id}/register` | Yes (student) | Register for course |
| POST | `/api/courses/{id}/drop` | Yes (student) | Drop course |
| GET | `/api/messages` | Yes | List conversations |
| GET | `/api/messages/{id}` | Yes | Messages in a conversation |
| POST | `/api/messages` | Yes | Send message (`to_user_id` or `conversation_id` + `body`) |
| GET | `/api/notifications` | Yes | List notifications |
| POST | `/api/notifications/{id}/read` | Yes | Mark one as read |
| POST | `/api/notifications/read-all` | Yes | Mark all as read |
| DELETE | `/api/notifications/{id}` | Yes | Delete notification |
| GET | `/api/profile` | Yes | Current profile + role extension row |
| PUT | `/api/profile` | Yes | Update profile fields |
| POST | `/api/profile/picture` | Yes | Upload profile picture |
| GET | `/api/documents` | Yes | List notice board documents (filters: `category`, `search`, `pinned`) |
| GET | `/api/documents/{id}` | Yes | Single document |
| POST | `/api/documents` | Yes (announcement roles) | Upload a document |
| PUT | `/api/documents/{id}` | Yes (owner/admin) | Update document metadata |
| DELETE | `/api/documents/{id}` | Yes (owner/admin) | Delete document |
| POST | `/api/documents/{id}/pin` | Yes (announcement roles) | Toggle pin |
| GET | `/api/events` | Yes | List calendar events (filters: `from`, `to`, `type`, `department_id`) |
| GET | `/api/events/{id}` | Yes | Single event |
| POST | `/api/events` | Yes (announcement roles) | Create event |
| PUT | `/api/events/{id}` | Yes (owner/admin) | Update event |
| DELETE | `/api/events/{id}` | Yes (owner/admin) | Delete event |
| GET | `/api/dashboard/student` | Yes (student) | Student summary bundle |
| GET | `/api/dashboard/lecturer` | Yes (lecturer) | Lecturer summary bundle |
| GET | `/api/dashboard/admin` | Yes (admin) | Admin system-wide counts |

### Example: login

Request `POST /api/login`
```json
{ "identifier": "20231234", "password": "SecurePass123" }
```

Response `200 OK`
```json
{
  "success": true,
  "message": "Logged in successfully.",
  "data": {
    "user_id": 12,
    "full_name": "Ama Owusu",
    "email": "ama.owusu@atu.edu.gh",
    "identifier": "20231234",
    "role_id": 1,
    "role_name": "student",
    "department_id": 3,
    "is_active": 1
  }
}
```

### Example: register (student, with phone + notification channels)

Request `POST /api/register`
```json
{
  "full_name": "Ama Owusu",
  "email": "00934522B@st.atu.edu.gh",
  "identifier": "00934522B",
  "password": "SecurePass123",
  "role": "student",
  "phone": "0244123456",
  "notify_channels": ["email", "sms"]
}
```

Response `201 Created`
```json
{
  "success": true,
  "message": "Registration successful.",
  "data": {
    "user_id": 55,
    "full_name": "Ama Owusu",
    "email": "00934522B@st.atu.edu.gh",
    "identifier": "00934522B",
    "phone": "0244123456",
    "notify_email": 1,
    "notify_sms": 1,
    "notify_whatsapp": 0,
    "role_id": 1
  }
}
```

### Example: create announcement

Request `POST /api/announcements` (header `X-CSRF-Token: <token>`)
```json
{ "title": "Mid-sem exams timetable", "body": "Exams begin Monday...", "category_id": 6, "priority": "high" }
```

Response `201 Created`
```json
{
  "success": true,
  "message": "Announcement created.",
  "data": {
    "announcement_id": 41,
    "author_id": 5,
    "category_id": 6,
    "department_id": null,
    "title": "Mid-sem exams timetable",
    "body": "Exams begin Monday...",
    "priority": "high",
    "is_pinned": 0,
    "publish_at": "2026-07-12 09:00:00"
  }
}
```

### Example: student dashboard

Request `GET /api/dashboard/student`

Response `200 OK`
```json
{
  "success": true,
  "message": "OK",
  "data": {
    "announcements": [ { "announcement_id": 41, "title": "Mid-sem exams timetable" } ],
    "upcoming_assignments": [ { "assignment_id": 9, "title": "DBMS Project", "due_date": "2026-07-20 23:59:00" } ],
    "unread_notifications": 3,
    "unread_messages": 1,
    "upcoming_events": [ { "event_id": 2, "title": "Career Fair", "start_datetime": "2026-07-18 10:00:00" } ]
  }
}
```
