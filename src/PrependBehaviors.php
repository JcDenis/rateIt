<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt;

use ArrayObject;
use Dotclear\App;
use Dotclear\Database\Statement\SelectStatement;

/**
 * @brief       rateIt common behaviors.
 * @ingroup     rateIt
 *
 * Delete votes of deleted items.
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class PrependBehaviors
{
    public static function adminBeforePostDelete(int|string $post_id): void
    {
        self::adminBeforePostsDelete([$post_id]);
    }

    /**
     * @param   array<int, int|string>|ArrayObject<int, int|string>   $ids    The posts IDs
     */
    public static function adminBeforePostsDelete(array|ArrayObject $ids): void
    {
        $ids = array_map(intval(...), array_filter((array) $ids, is_numeric(...)));
        if ($ids === []) {
            return;
        }

        // Find post type of each post
        $sql = new SelectStatement();
        $rs  = $sql
            ->columns(['post_id', 'post_type'])
            ->from(App::db()->con()->prefix() . App::blog()::POST_TABLE_NAME)
            ->where('blog_id = ' . $sql->quote(App::blog()->id()))
            ->and('post_id' . $sql->in($ids))
            ->select();

        while ($rs !== null && $rs->fetch()) {
            // Modules ID are post types for post and eventhandler
            Rates::delete($rs->strField('post_type'), (string) $rs->intField('post_id'));
        }
    }

    public static function adminBeforeCommentDelete(int|string $comment_id): void
    {
        self::adminBeforeCommentsDelete([$comment_id]);
    }

    /**
     * @param   array<int, int|string>|ArrayObject<int, int|string>   $ids    The comments IDs
     */
    public static function adminBeforeCommentsDelete(array|ArrayObject $ids): void
    {
        foreach ((array) $ids as $id) {
            if (is_numeric($id)) {
                Rates::delete('comment', (string) (int) $id);
            }
        }
    }

    public static function cinecturlink2BeforeDelLink(int|string $link_id): void
    {
        Rates::delete('cinecturlink2', (string) (int) $link_id);
    }
}
