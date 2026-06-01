<?php

namespace App\Enums;

enum PermissionEnum: string
{
    case CATEGORIES_VIEW = 'categories.view';
    case CATEGORIES_CREATE = 'categories.create';
    case CATEGORIES_UPDATE = 'categories.update';
    case CATEGORIES_DELETE = 'categories.delete';
    case CATEGORIES_RESTORE = 'categories.restore';
    case CATEGORIES_FORCE_DELETE = 'categories.force_delete';

    case ROLES_VIEW = 'roles.view';
    case ROLES_CREATE = 'roles.create';
    case ROLES_UPDATE = 'roles.update';
    case ROLES_DELETE = 'roles.delete';
    case ROLES_RESTORE = 'roles.restore';
    case ROLES_FORCE_DELETE = 'roles.force_delete';
}
