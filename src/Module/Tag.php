<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt\Module;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Plugin\rateIt\Module;

/**
 * @brief       rateIt tags module (plugin tags).
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Tag extends Module
{
    public function id(): string
    {
        return 'tag';
    }

    public function name(): string
    {
        return __('Tags');
    }

    public function title(): string
    {
        return __('Rate this tag');
    }

    public function isAvailable(): bool
    {
        return App::plugins()->moduleExists('tags');
    }

    public function contextId(): ?string
    {
        $ctx = App::frontend()->context();
        if ($ctx->exists('meta') && $ctx->meta instanceof MetaRecord && !$ctx->meta->isEmpty() && $ctx->meta->strField('meta_type') === 'tag') {
            return $ctx->meta->strField('meta_id');
        }

        return null;
    }

    public function widgetId(): ?string
    {
        return self::isUrlType(['tag']) ? $this->contextId() : null;
    }

    public function item(string $id): ?array
    {
        $rs = App::meta()->getMetadata([
            'meta_type' => 'tag',
            'meta_id'   => $id,
            'limit'     => 1,
        ]);

        return $rs->isEmpty() ? null : [
            'title' => $id,
            'url'   => App::blog()->url() . App::url()->getURLFor('tag', rawurlencode($id)),
        ];
    }

    public function adminItem(string $id): array
    {
        $item = $this->item($id);

        return [
            'title' => $id,
            'url'   => $item === null ? '' : App::backend()->url()->get('admin.plugin.tags', ['m' => 'tag_posts', 'tag' => $id], '&'),
        ];
    }
}
