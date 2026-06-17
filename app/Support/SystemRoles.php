<?php

namespace App\Support;

use App\Models\Role;

class SystemRoles
{
    /**
     * @return array<string, string> slug => display label
     */
    public static function protectedDefinitions(): array
    {
        return config('system_roles.protected', []);
    }

    /**
     * @return list<string>
     */
    public static function protectedSlugs(): array
    {
        return array_keys(self::protectedDefinitions());
    }

    public static function displayName(string $slug): string
    {
        $definitions = self::protectedDefinitions();

        return $definitions[$slug] ?? self::humanize($slug);
    }

    public static function isProtected(Role|string|null $role): bool
    {
        if ($role instanceof Role) {
            if ($role->is_protected) {
                return true;
            }

            $role = $role->name;
        }

        if (!is_string($role) || $role === '') {
            return false;
        }

        return in_array(strtolower($role), self::protectedSlugs(), true);
    }

    public static function humanize(string $slug): string
    {
        return ucwords(str_replace('_', ' ', $slug));
    }
}
