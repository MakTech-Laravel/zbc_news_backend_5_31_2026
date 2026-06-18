<?php

namespace App\Enums;

enum PermissionEnum: string
{
    case CATEGORIES_LIST = 'categories.list';
    case CATEGORIES_CREATE = 'categories.create';
    case CATEGORIES_SHOW = 'categories.show';
    case CATEGORIES_UPDATE = 'categories.update';
    case CATEGORIES_DELETE = 'categories.delete';
    case CATEGORIES_RESTORE = 'categories.restore';
    case CATEGORIES_FORCE_DELETE = 'categories.force-delete';

    case ROLES_LIST = 'roles.list';
    case ROLES_CREATE = 'roles.create';
    case ROLES_SHOW = 'roles.show';
    case ROLES_UPDATE = 'roles.update';
    case ROLES_DELETE = 'roles.delete';
    case ROLES_RESTORE = 'roles.restore';
    case ROLES_FORCE_DELETE = 'roles.force-delete';

    case ARTICLES_LIST = 'articles.list';
    case ARTICLES_TRASHED = 'articles.trashed';
    case ARTICLES_CREATE = 'articles.create';
    case ARTICLES_SHOW = 'articles.show';
    case ARTICLES_UPDATE = 'articles.update';
    case ARTICLES_DELETE = 'articles.delete';
    case ARTICLES_RESTORE = 'articles.restore';
    case ARTICLES_FORCE_DELETE = 'articles.force-delete';
    case ARTICLES_ACTIVITIES = 'articles.activities';
    case ARTICLES_STATS = 'articles.stats';

    case USER_READ_HISTORY = 'user.read-history';

    case TAGS_LIST = 'tags.list';
    case TAGS_CREATE = 'tags.create';
    case TAGS_SHOW = 'tags.show';
    case TAGS_UPDATE = 'tags.update';
    case TAGS_DELETE = 'tags.delete';
    case TAGS_RESTORE = 'tags.restore';
    case TAGS_FORCE_DELETE = 'tags.force-delete';

    case SAVE_ARTICLES_LIST = 'save-articles.list';
    case SAVE_ARTICLES_TOGGLE = 'save-articles.toggle';

    case SITE_SETTINGS_LIST = 'site-settings.list';
    case SITE_SETTINGS_UPDATE = 'site-settings.update';

    case PLANS_LIST = 'plans.list';
    case PLANS_TRASHED = 'plans.trashed';
    case PLANS_CREATE = 'plans.create';
    case PLANS_SHOW = 'plans.show';
    case PLANS_UPDATE = 'plans.update';
    case PLANS_DELETE = 'plans.delete';
    case PLANS_RESTORE = 'plans.restore';
    case PLANS_FORCE_DELETE = 'plans.force-delete';

    case NOTIFICATION_PREFERENCES_SHOW = 'notification-preferences.show';
    case NOTIFICATION_PREFERENCES_UPDATE = 'notification-preferences.update';

    case USER_NOTIFICATIONS_LIST = 'user-notifications.list';
    case USER_NOTIFICATIONS_MARK_READ = 'user-notifications.mark-read';
    case USER_NOTIFICATIONS_MARK_ALL_READ = 'user-notifications.mark-all-read';

    case COMMENTS_CREATE = 'comments.create';
    case COMMENTS_LIST = 'comments.list';
    case COMMENTS_APPROVE = 'comments.approve';
    case COMMENTS_REJECT = 'comments.reject';
    case COMMENTS_DELETE = 'comments.delete';

    case PERMISSIONS_LIST = 'permissions.list';

    case MEDIA_LIST = 'media.list';
    case MEDIA_CREATE = 'media.create';
    case MEDIA_SHOW = 'media.show';
    case MEDIA_DELETE = 'media.delete';
    case MEDIA_BULK_DELETE = 'media.bulk-delete';
    case MEDIA_SIGNED_PARAMS = 'media.signed-params';
    case MEDIA_TRANSFORM = 'media.transform';

    case USERS_LIST = 'users.list';
    case USERS_CREATE = 'users.create';
    case USERS_PROFILE = 'users.profile';
    case USERS_PROFILE_UPDATE = 'users.profile-update';
    case USERS_SHOW = 'users.show';
    case USERS_UPDATE = 'users.update';
    case USERS_DELETE = 'users.delete';
    case USERS_ARTICLE_ACTIVITIES = 'users.article-activities';
    case USERS_TWO_FACTOR_ENABLE = 'users.two-factor-enable';
    case USERS_READING_ANALYTICS = 'users.reading-analytics';
}
