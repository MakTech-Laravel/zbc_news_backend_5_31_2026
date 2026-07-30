# Sub Menu

Admin-managed Sub Menu for the public site: **Trending**, **Most Read**, and **Editorial Picks**.

**Live Updates** are managed under `/admin/live-updates` (live-blog articles). The public page `/?section=live_updates` lists those articles via `GET /api/v1/articles/live-blogs` (ongoing first, then ended, paginated).

All other section settings and pins are database-driven. Public submenu payloads are cached (~5 minutes) and busted on every admin write / live toggle / scheduled expiry job.

---

## Public UI (homepage)

### Header quick links (main column filter)
Clicking Trending / Most Read / Live Updates / Editorial Picks navigates to:

`/?section=trending|most_read|live_updates|editorial_picks`

- Trending / Most Read / Editorial Picks → Sub Menu feed APIs
- Live Updates → live-blog articles API (`/articles/live-blogs`)

### Right sidebar (classic)
1. Most Read (period tabs + Load more via `/articles/most-read`)
2. Ad slot
3. Trending Now (**tags** via `/trending-tags`)

---

## Admin UI

Path: `/admin/sub-menu`  
Permission: `articles.list` (read) / `articles.update` (writes)

| Tab | What you configure |
|---|---|
| **Trending** | Enable, limit, trending window (hours), pinned slots, manual pins |
| **Most Read** | Enable, limit, default period, pinned slots, manual pins |
| **Editorial Picks** | Enable, limit, pinned slots, curated picks + schedule |

Live Start/End is only on `/admin/live-updates` (per live-blog article).

---

## API

### Public
| Method | Path |
|---|---|
| `GET` | `/api/v1/sub-menu` |
| `GET` | `/api/v1/sub-menu/{section}` |
| `GET` | `/api/v1/articles/live-blogs?page=&per_page=` |

### Admin
| Method | Path | Permission |
|---|---|---|
| `GET` | `/api/v1/admin/sub-menu` | `articles.list` |
| `POST` | `/api/v1/admin/sub-menu/settings/{section}` | `articles.update` |
| `POST` | `/api/v1/admin/sub-menu/manual/{section}` | `articles.update` |
| `POST` | `/api/v1/admin/sub-menu/manual/{section}/reorder` | `articles.update` |
| `DELETE` | `/api/v1/admin/sub-menu/manual/{id}` | `articles.update` |
| `POST` | `/api/v1/admin/live-updates/{slug}/live/start` | `articles.update` |
| `POST` | `/api/v1/admin/live-updates/{slug}/live/end` | `articles.update` |

---

## Tables / jobs

- `sub_menu_settings` — one row per section key
- `sub_menu_featured_articles` — manual pins/picks
- `articles.is_live_blog`, `articles.is_live`, `live_started_at`, `live_ended_at`
- `article_live_updates` — timeline entries for live-blog articles
- Job: `ProcessSubMenus` (every minute)

Cache keys: `sub_menu:sections:{section}:public`

Legacy tables `sidebar_section_settings` / `sidebar_featured_articles` are renamed by migration `2026_07_27_094823_rename_sidebar_tables_to_sub_menu_tables`.

---

## Automated tests

```bash
php artisan test --filter=SubMenu
php artisan test --filter=LiveUpdatesTest
php artisan test --filter=MostReadArticlesTest
```
