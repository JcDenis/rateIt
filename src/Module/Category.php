<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt\Module;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Plugin\rateIt\Module;

/**
 * @brief       rateIt categories module.
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Category extends Module
{
    public function id(): string
    {
        return 'category';
    }

    public function name(): string
    {
        return __('Categories');
    }

    public function title(): string
    {
        return __('Rate this category');
    }

    public function contextId(): ?string
    {
        $ctx = App::frontend()->context();
        if ($ctx->exists('categories') && $ctx->categories instanceof MetaRecord && !$ctx->categories->isEmpty()) {
            return (string) $ctx->categories->intField('cat_id');
        }

        return null;
    }

    public function widgetId(): ?string
    {
        return self::isUrlType(['category']) ? $this->contextId() : null;
    }

    public function item(string $id): ?array
    {
        if (!is_numeric($id)) {
            return null;
        }

        $rs = App::blog()->getCategory((int) $id);

        return $rs->isEmpty() ? null : [
            'title' => $rs->strField('cat_title'),
            'url'   => App::blog()->url() . App::url()->getURLFor('category', $rs->strField('cat_url')),
        ];
    }

    public function adminItem(string $id): array
    {
        $item = $this->item($id);

        return [
            'title' => $item['title'] ?? $id,
            'url'   => $item === null ? '' : App::backend()->url()->get('admin.category', ['id' => $id], '&'),
        ];
    }
}
