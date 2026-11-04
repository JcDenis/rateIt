<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt;

use Dotclear\App;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\rateIt\Module\PostType;

/**
 * @brief       rateIt frontend behaviors.
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class FrontendBehaviors
{
    /**
     * Add plugin templates path (themes can override rateit.html).
     */
    public static function publicBeforeDocumentV2(): void
    {
        App::frontend()->template()->setPath(
            App::frontend()->template()->getPath(),
            Path::real(My::path()) . DIRECTORY_SEPARATOR . 'default-templates'
        );
    }

    /**
     * Add plugin CSS and JS.
     */
    public static function publicHeadContent(): void
    {
        if (!My::settings()->getBool('no_css', false)) {
            echo My::cssLoad('frontend');
        }
        if (!My::settings()->getBool('no_js', false)) {
            echo
            Html::jsJson('rateit', [
                'thanks' => My::message('thanks'),
                'failed' => __('Rating failed, please try again later.'),
            ]) .
            My::jsLoad('frontend', null, false, true);
        }
    }

    /**
     * Rating after entries content.
     */
    public static function publicEntryAfterContent(): void
    {
        foreach (My::activeModules() as $id => $module) {
            if ($module instanceof PostType && ($item = $module->entryId()) !== null) {
                echo Render::template($id, $item);

                return;
            }
        }
    }

    /**
     * Rating after comments content.
     */
    public static function publicCommentAfterContent(): void
    {
        $module = My::activeModules()['comment'] ?? null;
        if ($module !== null && $module->option('auto') && ($item = $module->contextId()) !== null) {
            echo Render::template('comment', $item);
        }
    }

    /**
     * Rating on links of cinecturlink2 widget.
     */
    public static function cinecturlink2WidgetLinks(int|string $link_id): string
    {
        $module = My::activeModules()['cinecturlink2'] ?? null;
        if ($module === null || !$module->option('widget')) {
            return '';
        }

        return Render::form(Rates::stats('cinecturlink2', (string) $link_id), Rates::voted('cinecturlink2', (string) $link_id));
    }
}
