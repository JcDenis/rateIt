<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt;

use Dotclear\App;

/**
 * @brief       rateIt module base class.
 * @ingroup     rateIt
 *
 * A module defines a type of rated items.
 * Third party plugins can add modules using behavior rateItModules.
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
abstract class Module
{
    /**
     * Module ID, used as rating type in database.
     */
    abstract public function id(): string;

    /**
     * Module name.
     */
    abstract public function name(): string;

    /**
     * Title of rating block.
     */
    public function title(): string
    {
        return __('Rate this');
    }

    /**
     * Check if module can be used (ex: required plugin exists).
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Check if module is enabled on current blog.
     */
    public function isActive(): bool
    {
        $modules = My::settings()->get('modules');

        return $this->isAvailable() && is_array($modules) && in_array($this->id(), $modules, true);
    }

    /**
     * Module options (boolean settings).
     *
     * @return  array<string, string>   Option ID / label
     */
    public function options(): array
    {
        return [];
    }

    /**
     * Get a module option value.
     */
    public function option(string $id): bool
    {
        return (bool) My::settings()->getBool('opt_' . $this->id() . '_' . $id, false);
    }

    /**
     * Get current item ID from public template context.
     */
    public function contextId(): ?string
    {
        return null;
    }

    /**
     * Get item ID for vote widget on current public page.
     */
    public function widgetId(): ?string
    {
        return null;
    }

    /**
     * Get public item informations.
     *
     * Returns null if item does not exist or is not public.
     *
     * @return  null|array{title: string, url: string}
     */
    abstract public function item(string $id): ?array;

    /**
     * Get item informations for backend.
     *
     * @return  array{title: string, url: string}
     */
    public function adminItem(string $id): array
    {
        return $this->item($id) ?? ['title' => $id, 'url' => ''];
    }

    /**
     * Check public URL type.
     *
     * @param   array<int, string>  $types  The URL types
     */
    protected static function isUrlType(array $types): bool
    {
        return in_array(App::url()->getType(), $types, true);
    }
}
