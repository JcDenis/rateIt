<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt\Module;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\rateIt\Module;

/**
 * @brief       rateIt module base for entries post types.
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
abstract class PostType extends Module
{
    /**
     * Entries post type.
     */
    abstract protected function postType(): string;

    /**
     * Public URL types of the post type single page.
     *
     * @return  array<int, string>
     */
    protected function singleUrlTypes(): array
    {
        return [$this->postType()];
    }

    public function contextId(): ?string
    {
        $ctx = App::frontend()->context();
        if ($ctx->exists('posts') && $ctx->posts instanceof MetaRecord && !$ctx->posts->isEmpty() && $ctx->posts->strField('post_type') === $this->postType()) {
            return (string) $ctx->posts->intField('post_id');
        }

        return null;
    }

    public function widgetId(): ?string
    {
        return self::isUrlType($this->singleUrlTypes()) ? $this->contextId() : null;
    }

    public function item(string $id): ?array
    {
        if (!is_numeric($id)) {
            return null;
        }

        $rs = App::blog()->getPosts([
            'post_id'    => (int) $id,
            'post_type'  => $this->postType(),
            'no_content' => true,
            'sql'        => App::task()->checkContext('FRONTEND') ? 'AND post_password IS NULL ' : '',
        ]);

        return $rs->isEmpty() ? null : [
            'title' => $rs->strField('post_title'),
            'url'   => App::blog()->url() . App::postTypes()->getPostPublicURL($this->postType(), Html::sanitizeURL($rs->strField('post_url')), false),
        ];
    }

    public function adminItem(string $id): array
    {
        $item = $this->item($id);

        return [
            'title' => $item['title'] ?? $id,
            'url'   => $item === null ? '' : App::postTypes()->getPostAdminURL($this->postType(), (int) $id, false),
        ];
    }

    /**
     * Check if rating block must be added to current entry content.
     *
     * @param   array<string, string>   $places     The options / URL types
     */
    protected function isPlace(array $places): bool
    {
        foreach ($places as $option => $url_type) {
            if ($this->option($option) && self::isUrlType([$url_type])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get item to rate after current entry content.
     */
    abstract public function entryId(): ?string;
}
