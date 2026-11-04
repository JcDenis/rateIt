<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt\Module;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\rateIt\Module;

/**
 * @brief       rateIt comments module.
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Comment extends Module
{
    public function id(): string
    {
        return 'comment';
    }

    public function name(): string
    {
        return __('Comments');
    }

    public function title(): string
    {
        return __('Rate this comment');
    }

    public function options(): array
    {
        return [
            'auto' => __('Show rating after each comment'),
        ];
    }

    public function contextId(): ?string
    {
        $ctx = App::frontend()->context();
        if ($ctx->exists('comments') && $ctx->comments instanceof MetaRecord && !$ctx->comments->isEmpty()) {
            return (string) $ctx->comments->intField('comment_id');
        }

        return null;
    }

    public function item(string $id): ?array
    {
        if (!is_numeric($id)) {
            return null;
        }

        $rs = App::blog()->getComments([
            'comment_id' => (int) $id,
            'no_content' => true,
        ]);

        return $rs->isEmpty() ? null : [
            'title' => sprintf(__('%s on %s'), $rs->strField('comment_author'), $rs->strField('post_title')),
            'url'   => App::blog()->url() . App::postTypes()->getPostPublicURL($rs->strField('post_type'), Html::sanitizeURL($rs->strField('post_url')), false) . '#c' . $id,
        ];
    }

    public function adminItem(string $id): array
    {
        $item = $this->item($id);

        return [
            'title' => $item['title'] ?? $id,
            'url'   => $item === null ? '' : App::backend()->url()->get('admin.comment', ['comment_id' => $id], '&'),
        ];
    }
}
