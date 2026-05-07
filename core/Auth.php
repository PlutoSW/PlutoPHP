<?php

namespace Pluto;


class Auth
{

    protected static mixed $user = null;


    public static function setUser(mixed $user): void
    {
        static::$user = $user;
    }


    public static function user(): mixed
    {
        if (empty(static::$user)) {
            static::$user = \request()->user;
        }
        return static::$user;
    }


    public static function check(string|array $permission): bool
    {

        if (empty($permission) || $permission === "") {
            return true;
        }
        $user = static::user();

        if (!$user) {
            return false;
        }

        if (isset($user->permissions->{"*"}) && $user->permissions->{"*"} === true) return \true;
        if (\is_array($permission)) {
            $userPermission = false;
            foreach ($permission as $value) {
                $userPermission = $user->permissions->{$value} ?? null;
                if ($userPermission) {
                    break;
                }
            }
        } else {
            $userPermission = $user->permissions->{$permission} ?? null;
        }
        if (!$userPermission) return false;
        if ($userPermission !== false || $userPermission !== 0) {
            return true;
        }

        return true;
    }
}
