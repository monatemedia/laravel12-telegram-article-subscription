# Product Requirements Document
## Article Publishing Platform with Telegram Bot
**Version 1.0 | February 2026**

---

## 1. Overview

A private article publishing platform built with Filament 4, backed by a Laravel 12 application, and delivered to end users via a Telegram bot. The platform allows a single administrator to write, upload, organize, and schedule articles, which are then made available to readers through Telegram.

---

## 2. Users & Access

### 2.1 Administrator

A single authenticated user who accesses the Filament dashboard to manage all content. There is no public-facing web interface. The administrator is responsible for uploading markdown files, editing content, organizing collections, scheduling publication, and managing all articles.

### 2.2 Readers

End users who interact with the platform exclusively through a Telegram bot. They do not log in and have no access to the Filament dashboard. Readers can browse articles and collections, read content within Telegram, and download articles as PDF files.

---

## 3. Data Model

### 3.1 Articles Table

| Field | Type | Description |
|---|---|---|
| `id` | bigint (PK) | Auto-incrementing primary key |
| `collection_id` | bigint (FK, nullable) | Foreign key to collections. Null if standalone article |
| `title` | string | Article title. Used to generate the slug and PDF filename |
| `slug` | string (unique) | URL-friendly version of the title, auto-generated on save |
| `body` | longtext | Full article content in Markdown format |
| `synopsis` | text | Short summary written by the administrator. Used in Telegram previews |
| `status` | enum | `draft` \| `scheduled` \| `published` |
| `published_at` | timestamp (nullable) | When the article goes live. Null for drafts. Future dates = scheduled |
| `order` | integer (nullable) | Position within a collection. Null for standalone articles |
| `pdf_path` | string (nullable) | Storage path to the generated PDF file |
| `created_at` | timestamp | Record creation time |
| `updated_at` | timestamp | Record last updated time |

### 3.2 Collections Table

| Field | Type | Description |
|---|---|---|
| `id` | bigint (PK) | Auto-incrementing primary key |
| `title` | string | Collection title |
| `synopsis` | text | Short summary of the collection, written by the administrator |
| `created_at` | timestamp | Record creation time |
| `updated_at` | timestamp | Record last updated time |

### 3.3 Key Rules

- A collection has no status or `published_at` of its own. It becomes visible in the Telegram feed as soon as it contains at least one published article.
- An article always owns its own `published_at`, regardless of whether it belongs to a collection. This allows articles to be added to a collection over months or years, each with their own publication date.
- A collection's effective **Last Updated At** date shown in the Telegram feed is derived dynamically as `MAX(published_at)` of all its published articles.
- Articles belonging to a collection do not appear as standalone entries in the Telegram feed. They are only accessible by tapping into the collection.
- Draft and scheduled articles are never visible to Telegram users.

---

## 4. Filament Dashboard

### 4.1 Article Resource

#### Upload

The administrator can select one or more `.md` files using the native Windows file picker and upload them in a single action. Each file is parsed and creates a new Article record with status set to `draft`. The title is derived from the filename (stripped of extension and slugified). The body is populated with the file contents.

#### Edit Form Fields

- **Title** — text input. Changing the title auto-updates the slug and renames the PDF file on next save.
- **Slug** — read-only, auto-generated from title.
- **Body** — markdown editor (editable within Filament).
- **Synopsis** — textarea, written manually by the administrator.
- **Status** — select: `Draft`, `Scheduled`, `Published`.
- **Published At** — datetime picker. Required when status is `Scheduled` or `Published`.
- **Collection** — select dropdown to assign the article to a collection (optional).
- **Order** — integer, set automatically via drag-and-drop in the collection view; editable as a fallback.

#### PDF Generation

A PDF is generated and saved to storage every time an article is saved. The PDF is named using the article's slug (e.g. `my-first-article.pdf`). If the title changes, the old PDF is deleted and a new one is saved under the updated slug. If a PDF already exists for the slug, it is overwritten.

### 4.2 Collection Resource

#### Create / Edit Form Fields

- **Title** — text input.
- **Synopsis** — textarea.

#### Article Management within a Collection

When viewing a collection in Filament, the administrator can see all articles assigned to that collection. Articles can be reordered using drag-and-drop, which updates the `order` field on each article. New articles are added to a collection by editing the article and selecting the collection from the dropdown.

### 4.3 Scheduling

Articles with a `published_at` timestamp in the future and a status of `scheduled` will not appear in the Telegram feed until that date and time has passed. A scheduled job runs periodically to check for articles whose `published_at` has been reached and makes them available. The administrator sets both the status and the datetime when scheduling.

---

## 5. Telegram Bot

### 5.1 Entry Point

A user starts a conversation with the bot by clicking a link (e.g. `t.me/yourbotname`). The bot responds with the main feed.

### 5.2 Main Feed

- Shows a unified, reverse-chronological list of published standalone articles and collections that have at least one published article.
- The effective date used for sorting is `published_at` for standalone articles, and `MAX(published_at)` of their articles for collections.
- Displays **10 entries per page**.
- Each entry shows: title, synopsis, and effective date.
- An **Older** button appears if there are more than 10 entries, allowing the user to paginate backward. A **Newer** button appears when not on the first page.

### 5.3 Collection Screen

- Shown when a user taps on a collection entry from the main feed.
- Displays the collection title, synopsis, and a numbered list of its published articles.
- Each article entry shows its number (order), title, and a **Read** button.
- Paginates at **15 articles per page** if the collection has more than 15 published articles.

### 5.4 Article Reading

When a user taps **Read** on any article (whether standalone or within a collection):

- Telegram has a 4,096 character limit per message. If the article body exceeds this limit, only the first chunk is displayed.
- After the first chunk, the user is presented with a **Continue Reading** button to receive the next chunk, and so on until the article is complete.
- Telegram's native Markdown/HTML formatting is applied to the article body for a clean reading experience.
- A **Download PDF** button is always available, which sends the pre-generated PDF file to the user in the chat.

---

## 6. PDF Behavior Summary

- PDFs are generated at save time, not on demand.
- Filename is based on the article slug: `{slug}.pdf`.
- When an article title is changed, the old PDF is deleted and replaced with a new one using the updated slug.
- PDFs are stored in application storage and served directly through Telegram when a user requests a download.

---

## 7. Technology Stack

| Concern | Technology |
|---|---|
| Backend | Laravel 12 (PHP) |
| Admin Dashboard | Filament 4 |
| Database | MySQL |
| File Storage | Laravel storage (local) |
| Telegram Integration | Telegram Bot API |
| PDF Generation | TBD (e.g. Browsershot, DomPDF, or wkhtmltopdf) |
| Scheduling | Laravel task scheduler |

---

## 8. Analytics

Every user interaction passes through the Telegram bot, making analytics straightforward to implement. Each bot event is logged to an `analytics_events` table as it occurs.

### 8.1 Events Table

| Field | Type | Description |
|---|---|---|
| `id` | bigint (PK) | Auto-incrementing primary key |
| `telegram_user_id` | bigint | The Telegram user's ID |
| `event_type` | enum | `article_read` \| `pdf_download` \| `collection_view` \| `continue_reading` \| `paginate` |
| `article_id` | bigint (FK, nullable) | The article involved, if applicable |
| `collection_id` | bigint (FK, nullable) | The collection involved, if applicable |
| `created_at` | timestamp | When the event occurred |

### 8.2 Tracked Events

- **article_read** — user taps Read on an article
- **continue_reading** — user requests the next chunk of an article
- **pdf_download** — user downloads a PDF
- **collection_view** — user taps into a collection
- **paginate** — user navigates to an older or newer page in the main feed or a collection

### 8.3 Dashboard

Analytics are surfaced in the Filament dashboard, showing metrics such as most-read articles, most-downloaded PDFs, and activity over time.

---

## 9. Out of Scope (v1)

- Multi-user authentication or roles
- Public web interface for readers
- Comments or reader interaction beyond reading and downloading
- Search functionality within the Telegram bot
