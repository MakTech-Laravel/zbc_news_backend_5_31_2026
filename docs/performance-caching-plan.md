# Performance & Caching Plan

Branch: `perf-caching` (off `ssr-cleanup-sitemap`, both repos).

Derived from the Phase 0 discovery pass. Every claim below was verified against the code on
this branch — where something could **not** be verified from this session, it says so explicitly
rather than assuming. That distinction is carried through to the Phase 3 summary and must not
blur.

## Scope summary

| Area | Outcome | Why |
| --- | --- | --- |
| A. Redis client fix | Code change, 2 files | Real latent break; `predis` is the only installed client |
| B. Endpoint caching | Code change, 4 endpoints | All uncached, all confirmed hot |
| C. Frontend compression/headers | **Docs only, no code** | Already correct — verified by real GET |
| D. useMemo | **Dropped** | React Compiler already auto-memoizes |
| OPcache | **No change** | Already correct for container-per-deploy |

Two of the four things the project owner asked about (compression, OPcache) close out as
"already correct, here's the evidence" rather than as code changes.

## Out of scope (deliberately)

- **`getCategoryTree()` status filtering.** It filters roots by `parent_id` but never by
  `status`, while children are filtered client-side. Pre-existing and real, but it is a
  behavioral change needing its own task and review. Caching freezes the existing behavior for
  the TTL window — it does not make this worse, and it is not fixed here.
- `docker-compose.yml` / VM host nginx — not in this repo, handled separately.
- `backend/docker/php.ini`, both Dockerfiles — Phase 0 found no justification to touch them.

---

## A. Redis client fix

**Problem.** `predis/predis ^3.5` is the only Redis client installed. The Dockerfile has no
`pecl install redis` (no `pecl` line at all), so the phpredis C extension does not exist in the
production image. Two places name `phpredis` anyway:

| Location | Current | Change to |
| --- | --- | --- |
| `.env.example:93` | `REDIS_CLIENT=phpredis` | `REDIS_CLIENT=predis` |
| `config/database.php:148` | `env('REDIS_CLIENT', 'phpredis')` | `env('REDIS_CLIENT', 'predis')` |

**Both must change.** The config default is the important one: because it is the fallback,
an `.env` with no `REDIS_CLIENT` line still resolves to the missing extension. Fixing only
`.env.example` leaves the landmine armed for any environment whose `.env` omits the line.

Nothing routes through Redis today (`CACHE_STORE=database`), which is why this is latent rather
than an active outage. It breaks the moment anyone sets `CACHE_STORE=redis` — including as a
result of Phase B, if someone flips the driver expecting it to work.

**Why `predis`, not phpredis.** No Dockerfile change, no image rebuild, works with what is
already installed. phpredis is faster, but that speed delta is irrelevant next to "cache is off
entirely," and adding a C extension to a working image is a materially larger change than
editing two strings. Revisit only if Redis throughput ever measurably matters.

**Verification trap — important.** This dev machine *has* the `redis` extension loaded
(`php -m` lists it). A local `CACHE_STORE=redis` tinker test would therefore **pass while
production still fails**. Local green proves nothing here. This fix is verified by reading the
resolved config, not by a successful tinker round-trip:

```
php artisan config:show database.redis.client   # expect: predis
```

Verified locally: config resolves to `predis`. Requires VM: Redis actually reachable from the
real backend container.

## B. Endpoint caching

Four endpoints, all confirmed uncached, all confirmed hot from the production request log.

`site-settings` was on the original list but **is already done** — `SiteSettingsService` caches
with a 1h TTL and already invalidates on write (`:97`, `:103`). No work needed.

### Pattern to follow

Mirror `SitemapService` exactly — do not invent a second pattern:

- private `CACHE_*` key constants + private `TTL_*` constants on the service
- `Cache::remember(self::CACHE_KEY, self::TTL, fn () => ...)` on read
- a public `flushCache()` the write paths call

`SitemapService` pairs its cache with `sitemap:refresh` invalidation this way already.

**Do not use model observers.** `ArticleObserver` exists but its methods are deliberately empty,
and its docblock states lifecycle side-effects live in the service layer so data is complete.
Observer-based invalidation would contradict an existing, documented convention.

### B1. `categories` — TTL 1 hour

Backed by `CategoryService::getCategoryTree()` (roots + eager-loaded children). Admin-edited,
changes infrequently → long TTL is safe.

Invalidation goes in `CategoryService`, which has **7 write paths — every one needs the
`flushCache()` call**:

| Method | Line | Note |
| --- | --- | --- |
| `create` | 25 | |
| `update` | 44 | |
| **`reorder`** | **57** | **easy to miss** |
| **`moveToPosition`** | **76** | **easy to miss** |
| `delete` | 105 | |
| `restore` | 116 | |
| `forceDelete` | 127 | |

`reorder` and `moveToPosition` are called out because they are the two that do not look like
conventional writes — they mutate `sort_order` rather than creating/deleting rows, so they read
as reordering rather than editing. Both change the cached payload (the tree is ordered by
`sort_order`), and both are exactly the kind of path a reviewer skims past. **Missing either
means the admin drags a category, sees no change for an hour, and reports it as a reorder bug.**

Note: on this branch `reorder(array $orderedIds)` has no `$parentId` param (it is slightly
behind the categoryFixes work). Does not affect the plan.

### B2. `navigation/quick-links` — TTL 1 hour

`NavigationController::quickLinks` — single filtered/sorted query. Admin-edited, infrequent.

**No service layer exists** — `NavigationLinkController` writes directly to the model
(`create` :35, `update` :52, `delete` :59). Two options, decide at implementation:

- add the `Cache::forget` to those three controller actions (smaller, matches current shape), or
- extract a small service (more consistent with `CategoryService`, larger diff)

Recommend the former — this task is caching, not refactoring. All three paths need covering.

### B3. `ads/slots` — TTL 1 hour

`AdSlotController::index` (frontend) — single filtered query, `keyBy('slot_key')`. Admin-edited,
infrequent.

Writes are again controller-direct: `store` :33, `update` :53. **There is no delete path** —
store/update only, so only two call sites need invalidation.

### B4. `trending-tags` — TTL 10 minutes, no write-path invalidation

The odd one, and the most expensive of the four: `TagService::getTrendingTags()` does
`withCount('articles')` + `orderBy('articles_count')` + limit — an aggregate across the join.
Biggest win of the phase.

**Deliberately TTL-only.** Its data changes with *article* writes (publish/unpublish shifts the
counts), not tag writes — so hooking tag write paths would be invalidation theater: it would
look correct while missing every real cause of change. Hooking every article write path instead
would mean invalidating a whole-site aggregate on every publish, for data that is decorative
ranking, not correctness-critical.

10 minutes bounds the staleness. A tag trending 10 minutes late is invisible to users; a
per-article-write flush would cost more than the cache saves. This is the one endpoint where
short TTL is the *right* answer rather than a compromise.

Mirrors `SitemapService::TTL_NEWS = 600` for the same reason — a constantly-shifting aggregate.

### TTL summary

| Endpoint | TTL | Invalidation | Rationale |
| --- | --- | --- | --- |
| `categories` | 1h | 7 write paths in `CategoryService` | Admin-edited, infrequent |
| `navigation/quick-links` | 1h | 3 controller actions | Admin-edited, infrequent |
| `ads/slots` | 1h | 2 controller actions (no delete) | Admin-edited, infrequent |
| `trending-tags` | 10m | none — TTL only | Driven by article writes, not tag writes |

### Tests

Per endpoint, in `tests/Feature/` (PHPUnit — `CLAUDE.md` requires PHPUnit, not Pest, despite the
task brief saying Pest; existing suite confirms PHPUnit):

1. cache hit returns without re-querying (assert query count via `DB::listen` or a spy)
2. cache busts after the corresponding admin write path runs

For `categories`, test **all 7** write paths bust the cache — `reorder` and `moveToPosition`
especially. For `trending-tags`, no bust test (no write-path invalidation by design); test the
hit path and that the TTL constant is what's intended.

Run `php artisan test --compact` after each endpoint, not just at the end. Run
`vendor/bin/pint --dirty --format agent` before finalizing.

## C. Frontend compression & cache headers — documentation only

**No code change.** Phase 0 verified `@react-router/serve` already does the right thing.

This is the finding that would have been gotten backwards: **`curl -I` (HEAD) shows no
`Content-Encoding`**, which reads as "no compression at all." A real GET disproves it — the
brief's own `-I` instruction produces the misleading answer.

Raw output, hashed asset (`/assets/AboutUs-BUWRFymB.js`), real GET:

```
Cache-Control: public, max-age=31536000, immutable
Vary: Accept-Encoding
Content-Encoding: br
```

HTML document (`/`), real GET:

```
content-type: text/html; charset=utf-8
Vary: Accept-Encoding
Content-Encoding: br
(no Cache-Control)
```

Negotiation is correct in all three cases:

| Client sends | Server responds |
| --- | --- |
| `Accept-Encoding: gzip, br` | `Content-Encoding: br` |
| `Accept-Encoding: gzip` | `Content-Encoding: gzip` |
| `Accept-Encoding: identity` | uncompressed (`Content-Length: 10505`) |

**The Phase 2C danger case is already correct.** Hashed `/assets/*` get
`max-age=31536000, immutable`; the HTML document gets **no** `Cache-Control` — so it is not
aggressively cached and will not serve stale articles. That is the correct split, and it is the
one that would have been broken by "fixing" this. Adding a compression package would be
redundant at best.

Verified locally, with the raw output above.

## D. useMemo — dropped

React Compiler is **already enabled project-wide** — `vite.config.ts` runs
`reactCompilerPreset()`, confirmed in build output (a `compiler-runtime` chunk plus memo-cache
calls). It auto-memoizes at compile time, making hand-written `useMemo` largely redundant.

The named candidates do not survive scrutiny:

- **`categoryTree.ts`** — the premise was "rebuilt on every render." It is not. Every caller
  (`FrontendHeader:75`, `useCategoryLinks:26`, `SidebarNav:53`) calls it **once inside a fetch
  callback** and stores the result in `useState`. It runs once per fetch, not per render.
  Nothing to memoize. The functions are also O(n) over a handful of categories.
- **`ArticleGrid`** (89 lines) — one `.map()`, no derived computation. Trivially cheap.
- **`CategoryReorderList`** — already has a `useMemo` at `:167`.

The codebase already uses `useMemo`/`useCallback` across 47 files; it is not naive about this.
Adding more would be optimization theater, and Phase 2D's own profiler gate ("revert if it
doesn't measurably help") would reject these anyway.

## Verified locally vs. requires VM

Carried into the Phase 3 summary. The second column does not get rounded up to "done."

| Verified locally (with raw output) | Implemented correctly, **requires live VM** |
| --- | --- |
| Frontend `br`/`gzip` negotiation, `immutable` assets, uncached HTML | Host-nginx-layer gzip + cache headers |
| `database.redis.client` resolves to `predis` | Redis actually reachable from backend container |
| Endpoint cache hit/bust behavior (PHPUnit) | OPcache live hit-rate |
| React Compiler active in build output | Production `.env` actual driver values |

**The VM's real `backend/.env` is not readable from this session** and may have diverged from
`.env.example`. If `CACHE_STORE=redis` is already set there, that endpoint is failing *right
now* — worth checking first thing on the VM.

## OPcache — no change

`docker/php.ini:29-37` is already correct: `opcache.enable=1`, `validate_timestamps=0`. The
container-per-deploy reasoning holds — `Dockerfile:19` does `COPY . .` at build time, so every
deploy bakes fresh code into a new image and the cache cannot go stale across a deploy.

Local dev is unaffected, confirmed rather than assumed: php.ini reaches PHP only via the
Dockerfile's `COPY ./docker/php.ini`, so `php artisan serve` never reads it. No local-dev
friction. `opcache.enable_cli=0` also means artisan commands skip OPcache entirely.

Live hit-rate still needs the VM.

## Order of work

1. **A** — Redis fix (2 files) + `config:show` verification
2. **B4** — `trending-tags` (most expensive, simplest: TTL-only, no invalidation)
3. **B2/B3** — quick-links, ads/slots (2–3 call sites each)
4. **B1** — categories (7 write paths, highest risk of a miss)
5. **C** — fold the verification record above into the Phase 3 summary
6. Full `php artisan test --compact` + `vendor/bin/pint --dirty`

A first: it is the latent break, and B is what would trip it. B4 before B1 builds the test
pattern on the simplest case before the one with 7 invalidation points.

---

# As-built record (Phase 2 complete)

Everything below is what actually shipped, including two places where the plan above was wrong
and the implementation deviates from it deliberately.

## Deviation 1 — the public categories endpoint serves a flat list, not a tree

The plan (written from a Phase 0 read of `admin-sitemap-docker-ssr`) says `categories` is backed
by `CategoryService::getCategoryTree()`. **On `ssr-cleanup-sitemap` that method does not exist.**
The public endpoint calls `getAllCategories()` — a flat, ordered list; nesting is the frontend's
job. The plan's premise was branch-skewed; the caching target is `getAllCategories()`'s payload.

This also means the `getCategoryTree()` status-filtering inconsistency flagged as out-of-scope
in Phase 0 **does not exist on this branch either**. Nothing to avoid; it arrives with the
categoryFixes work.

## Deviation 2 — the categories cache wraps the controller, not the service method

`getAllCategories()` has **four callers**: the public endpoint plus three admin endpoints
(`index`, `reorder`, `move`). Caching inside the service method would have served admins their
own stale list immediately after an edit — the exact failure the cache is meant to prevent,
aimed at the people most likely to notice.

So the `Cache::remember` wraps the **public controller's** read
(`frontend/CategoryController::index`); admin reads stay live. The key and TTL still live on
`CategoryService` (`CACHE_PUBLIC`, `TTL_PUBLIC`) so invalidation and definition stay together.

## What shipped

| Area | Files | Verification |
| --- | --- | --- |
| A. Redis client | `config/database.php:148`, `.env.example:93` | `config:show` before/after |
| B1. categories | `CategoryService` (7 flushes), `frontend/CategoryController` | 10 tests + negative control |
| B2. quick-links | `NavigationLink`, `NavigationController`, `NavigationLinkController` (3 flushes) | 5 tests + negative control |
| B3. ads/slots | `AdSlot`, `frontend/AdSlotController`, `backend/AdSlotController` (2 flushes) | 5 tests + route-table guard |
| B4. trending-tags | `TagService` | 4 tests + control |
| C. frontend | none — documentation only | live curl, below |
| D. useMemo | none — dropped | — |

Final TTLs as built:

| Endpoint | TTL | Invalidation |
| --- | --- | --- |
| `categories` | 1h | 7 `CategoryService` write paths |
| `navigation/quick-links` | 1h | 3 `NavigationLinkController` actions |
| `ads/slots` | 1h | 2 `AdSlotController` actions (no delete path exists) |
| `trending-tags` | 10m | none — TTL only, by design |

## Verification method: every cache test was run against uncached code first

A cache test that passes without the cache proves nothing. Each was run as a control before its
fix, and each failed for the right reason:

- **B4** — 3 of 4 failed uncached (`Failed asserting that 1 is identical to 0`)
- **B2/B3** — the hit test failed uncached on both
- **B1** — the hit test failed uncached (`Failed asserting that 2 is identical to 0`)

Then **negative controls** — removing a flush from working code to prove the bust tests bite:

- **B2**: removing the `destroy()` flush →`test_destroy_busts_the_cache` failed with the deleted
  link still served (`Expected [] / Actual ['Politics']`).
- **B1**: removing **only** the `reorder()` and `moveToPosition()` flushes → **exactly those two
  tests failed**, the other 8 passed, showing the stale order actually served:

  ```
  test_reorder_busts_the_cache
    Expected: ['Sport', 'Politics']     ← admin reordered to this
    Actual:   ['Politics', 'Sport']     ← public endpoint still served the old order

  test_move_to_position_busts_the_cache
    Expected: ['Sport', 'Business', 'Politics']
    Actual:   ['Politics', 'Sport', 'Business']
  ```

  This is the concrete failure the plan predicted: an admin drags a category, the public site
  keeps the old order for an hour. Both flushes restored; all 10 pass.

`moveToPosition()` flushes independently even though it delegates to `reorder()` — the
delegation is an implementation detail, and it has an early return that skips `reorder()`
entirely.

## One test assertion was wrong and was corrected

`test_repeat_request_is_served_from_cache` first asserted **zero** queries on a warm request and
failed at 1. The query was `select * from "site_settings" limit 1` — not a categories query at
all, but the `Category` resource's SEO resolution, which `SiteSettingsService` caches separately
and `RefreshDatabase` wipes per test. The assertion now targets `article_categories`
specifically, and was re-confirmed to still fail without the cache — it measures what it claims.

## Test-suite guards left behind

Three tests pin decisions that would otherwise erode:

- `test_ad_slots_still_has_no_delete_path` — inspects the route table; fails if a DELETE route
  is added without invalidation, naming the fix in the message.
- `test_new_tag_is_not_visible_until_cache_expires` — pins that a tag write must **not** bust
  trending-tags, so "helpful" invalidation gets a failure explaining why it is wrong.
- `test_direct_model_writes_bypass_invalidation_by_design` — pins that invalidation hangs off
  `CategoryService`; a future write path bypassing it must flush explicitly.

## C. Frontend verification record (no code changed)

Re-confirmed live at Phase 2 close, on a fresh `npm run build && npm run start`, with a real GET
(not `curl -I` — HEAD hides `Content-Encoding` and is what makes this look broken):

```
GET /assets/AboutUs-BUWRFymB.js   (Accept-Encoding: gzip, br)
  Cache-Control: public, max-age=31536000, immutable
  Vary: Accept-Encoding
  Content-Encoding: br

GET /                             (Accept-Encoding: gzip, br)
  content-type: text/html; charset=utf-8
  Vary: Accept-Encoding
  Content-Encoding: br
  (no Cache-Control)
```

| Client sends | Server responds |
| --- | --- |
| `gzip, br` | `Content-Encoding: br` |
| `gzip` | `Content-Encoding: gzip` |
| `identity` | uncompressed (`Content-Length: 10505`) |

Hashed assets are immutable-cached; the HTML document is **not** cached — the correct split, and
the one that would have been broken by "fixing" it. Two of the project owner's four asks
(compression, browser caching at the app layer) close out here as already-correct, with evidence.

## Final state

- Backend: `php artisan test --compact` → **126 tests, 126 passed, 471 assertions** (from 106).
- `vendor/bin/pint --dirty` → **passed**.
- Frontend: no changes; `perf-caching` branch is identical to `ssr-cleanup-sitemap`.
