# Sub Menu

Admin-managed Sub Menu for the public site: **Trending**, **Most Read**, **Live Updates**, and **Editorial Picks**.

All section settings and pins are database-driven. Public payloads are cached (~5 minutes) and busted on every admin write / live toggle / scheduled expiry job.

---

## Public UI (homepage)

### Header quick links (main column filter)
Clicking Trending / Most Read / Live Updates / Editorial Picks navigates to:

`/?section=trending|most_read|live_updates|editorial_picks`

and replaces the **main** home feed (Sub Menu / most-read APIs). Sidebars are unchanged.

### Right sidebar (classic)
1. Most Read (period tabs + Load more via `/articles/most-read`)
2. Ad slot
3. Trending Now (**tags** via `/trending-tags`)

Live Updates / Editorial Picks are reached via the header filter above.

---

## Admin UI

Path: `/admin/sub-menu`  
Permission: `articles.list` (read) / `articles.update` (writes)

| Tab | What you configure |
|---|---|
| **Trending** | Enable, limit, trending window (hours), pinned slots, manual pins |
| **Most Read** | Enable, limit, default period, pinned slots, manual pins |
| **Live Updates** | Enable, limit, pinned slots, Start/End live, optional manual boosts |
| **Editorial Picks** | Enable, limit, pinned slots, curated picks + schedule |

---

## API

### Public
| Method | Path |
|---|---|
| `GET` | `/api/v1/sub-menu` |
| `GET` | `/api/v1/sub-menu/{section}` |

### Admin
| Method | Path | Permission |
|---|---|---|
| `GET` | `/api/v1/admin/sub-menu` | `articles.list` |
| `POST` | `/api/v1/admin/sub-menu/settings/{section}` | `articles.update` |
| `POST` | `/api/v1/admin/sub-menu/manual/{section}` | `articles.update` |
| `POST` | `/api/v1/admin/sub-menu/manual/{section}/reorder` | `articles.update` |
| `DELETE` | `/api/v1/admin/sub-menu/manual/{id}` | `articles.update` |
| `POST` | `/api/v1/admin/sub-menu/live/start/{articleId}` | `articles.update` |
| `POST` | `/api/v1/admin/sub-menu/live/end/{articleId}` | `articles.update` |

---

## Tables / jobs

- `sub_menu_settings` — one row per section key
- `sub_menu_featured_articles` — manual pins/picks
- `articles.is_live`, `live_started_at`, `live_ended_at`
- Job: `ProcessSubMenus` (every minute)

Cache keys: `sub_menu:sections:{section}:public`

Legacy tables `sidebar_section_settings` / `sidebar_featured_articles` are renamed by migration `2026_07_27_094823_rename_sidebar_tables_to_sub_menu_tables`.

---

## Automated tests

```bash
php artisan test --filter=SubMenu
php artisan test --filter=MostReadArticlesTest
```