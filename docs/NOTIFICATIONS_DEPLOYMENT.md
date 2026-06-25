# Real-Time Notifications — Production Deployment

In-app notifications require **three background services** in addition to the Laravel HTTP app.

## Required services

| Service | Command | Purpose |
|---------|---------|---------|
| HTTP API | `php artisan serve` / PHP-FPM | REST API, preference storage |
| Queue worker | `php artisan queue:work --tries=3` | `DispatchArticlePublishedNotifications`, newsletter sends |
| Reverb | `php artisan reverb:start` | WebSocket broadcast to browsers |
| Scheduler | `php artisan schedule:work` | Scheduled articles, newsletter campaigns |

## Environment variables

### Backend (`.env`)

```env
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

REVERB_APP_ID=...
REVERB_APP_KEY=...      # Must match VITE_REVERB_APP_KEY
REVERB_APP_SECRET=...
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http      # https in production behind TLS terminator
```

### Frontend (`.env`)

```env
VITE_REVERB_APP_KEY=...   # Same as REVERB_APP_KEY
VITE_REVERB_HOST=ws.your-domain.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

## Broadcasting auth

Private channels authenticate via:

```
POST /api/broadcasting/auth
Authorization: Bearer {passport_token}
```

Channel used for user notifications: `private-App.Models.User.{userId}`  
Event name: `.notification.created`

## Smoke tests

1. **Reverb ping:** `GET /api/v1/realtime/ping`
2. **Frontend:** open `/ws-test` while logged in
3. **End-to-end:** publish a breaking-tagged article → opted-in user sees toast + bell count without refresh

## Database

Run migrations and backfill preferences for existing users:

```bash
php artisan migrate
php artisan notifications:backfill-preferences
```

## Troubleshooting

| Symptom | Check |
|---------|-------|
| No realtime toast | Reverb running? `BROADCAST_CONNECTION=reverb`? Frontend `VITE_REVERB_*` aligned? |
| Delayed notifications | Queue worker running? Jobs table has pending rows? |
| 403 on WebSocket auth | Valid Bearer token? User ID matches channel? |
| Preferences not saving | User has `notification-preferences.update` permission |
