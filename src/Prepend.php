<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt;

use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;

/**
 * @brief       rateIt prepend class.
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Prepend
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::PREPEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // Public vote URL
        App::url()->register('rateit_vote', 'rateit', '^rateit$', FrontendUrl::vote(...));

        App::behavior()->addBehaviors([
            // Clean up votes of deleted items
            'adminBeforePostDelete'      => PrependBehaviors::adminBeforePostDelete(...),
            'adminBeforePostsDelete'     => PrependBehaviors::adminBeforePostsDelete(...),
            'adminBeforeCommentDelete'   => PrependBehaviors::adminBeforeCommentDelete(...),
            'adminBeforeCommentsDelete'  => PrependBehaviors::adminBeforeCommentsDelete(...),
            'cinecturlink2BeforeDelLink' => PrependBehaviors::cinecturlink2BeforeDelLink(...),
        ]);

        return true;
    }
}
