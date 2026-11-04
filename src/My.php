<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt;

use ArrayObject;
use Dotclear\App;
use Dotclear\Module\MyPlugin;

/**
 * @brief       rateIt My helper.
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class My extends MyPlugin
{
    /**
     * Votes database table name.
     *
     * @var     string  TABLE_NAME
     */
    public const TABLE_NAME = 'rateit';

    /**
     * Voted items cookie name (same as old versions).
     *
     * @var     string  COOKIE_VOTES
     */
    public const COOKIE_VOTES = 'rateItVotedList';

    /**
     * Voter ID cookie name.
     *
     * @var     string  COOKIE_VOTER
     */
    public const COOKIE_VOTER = 'rateItVoter';

    /**
     * Voter identification by IP.
     *
     * @var     int     IDENT_IP
     */
    public const IDENT_IP = 0;

    /**
     * Voter identification by IP and cookie.
     *
     * @var     int     IDENT_BOTH
     */
    public const IDENT_BOTH = 1;

    /**
     * Voter identification by cookie.
     *
     * @var     int     IDENT_COOKIE
     */
    public const IDENT_COOKIE = 2;

    /**
     * Rating styles.
     *
     * @var     array<int, string>  STYLES
     */
    public const STYLES = ['classic', 'twin', 'simple'];

    /**
     * Known modules.
     *
     * @var     null|array<string, Module>  $modules
     */
    private static ?array $modules = null;

    public static function checkCustomContext(int $context): ?bool
    {
        return match ($context) {
            // Add content admin perm to backend
            self::MANAGE, self::MENU => App::task()->checkContext('BACKEND')
                && App::blog()->isDefined()
                && App::auth()->check(App::auth()->makePermissions([
                    App::auth()::PERMISSION_CONTENT_ADMIN,
                ]), App::blog()->id()),

            default => null,
        };
    }

    /**
     * Check if rating is active on current blog.
     */
    public static function isActive(): bool
    {
        return App::blog()->isDefined() && (bool) self::settings()->getBool('active', false);
    }

    /**
     * Get rating style.
     */
    public static function style(): string
    {
        $style = self::settings()->getStr('style', false);

        return in_array($style, self::STYLES, true) ? $style : 'classic';
    }

    /**
     * Get maximum note.
     */
    public static function quotient(): int
    {
        $quotient = (int) self::settings()->getInt('quotient', false);

        return $quotient > 1 && $quotient <= 20 ? $quotient : 5;
    }

    /**
     * Get notes digits number.
     */
    public static function digit(): int
    {
        $digit = (int) self::settings()->getInt('digit', false);

        return $digit >= 0 && $digit <= 4 ? $digit : 1;
    }

    /**
     * Get voters identification mode.
     */
    public static function ident(): int
    {
        $ident = (int) self::settings()->getInt('ident', false);

        return in_array($ident, [self::IDENT_IP, self::IDENT_BOTH, self::IDENT_COOKIE], true) ? $ident : self::IDENT_IP;
    }

    /**
     * Get a message setting.
     *
     * @param   string  $name   like, notlike or thanks
     */
    public static function message(string $name): string
    {
        $msg = trim(self::settings()->getStr('msg_' . $name, false));

        return $msg !== '' ? $msg : match ($name) {
            'like'    => __('I like'),
            'notlike' => __("I don't like"),
            default   => __('Thank you for having voted'),
        };
    }

    /**
     * Get styles combo.
     *
     * @return  array<string, string>
     */
    public static function stylesCombo(): array
    {
        return [
            __('Classic: visitor gives a note to an item') => 'classic',
            __('Twin: visitor says if he likes or not an item') => 'twin',
            __('Simple: visitor just says if he likes an item') => 'simple',
        ];
    }

    /**
     * Get voters identification combo.
     *
     * @return  array<string, string>
     */
    public static function identCombo(): array
    {
        return [
            __('IP address')        => (string) self::IDENT_IP,
            __('IP address and cookie') => (string) self::IDENT_BOTH,
            __('Cookie only')       => (string) self::IDENT_COOKIE,
        ];
    }

    /**
     * Get all known modules (available or not).
     *
     * @return  array<string, Module>
     */
    public static function modules(): array
    {
        if (self::$modules === null) {
            /**
             * @var ArrayObject<int, mixed>
             */
            $list = new ArrayObject([
                new Module\Comment(),
                new Module\Cinecturlink2(),
                new Module\EventHandler(),
                new Module\Post(),
                new Module\Category(),
                new Module\Tag(),
            ]);

            # --BEHAVIOR-- rateItModules -- ArrayObject
            App::behavior()->callBehavior('rateItModules', $list);

            self::$modules = [];
            foreach ($list as $module) {
                if ($module instanceof Module && $module->isAvailable()) {
                    self::$modules[$module->id()] = $module;
                }
            }
        }

        return self::$modules;
    }

    /**
     * Get active modules.
     *
     * @return  array<string, Module>
     */
    public static function activeModules(): array
    {
        return array_filter(self::modules(), fn (Module $module): bool => $module->isActive());
    }

    /**
     * Get a module.
     */
    public static function module(string $id): ?Module
    {
        return self::modules()[$id] ?? null;
    }

    /**
     * Get modules combo.
     *
     * @return  array<string, string>
     */
    public static function modulesCombo(bool $active_only = false): array
    {
        $res = [];
        foreach ($active_only ? self::activeModules() : self::modules() as $id => $module) {
            $res[$module->name()] = $id;
        }

        return $res;
    }
}
