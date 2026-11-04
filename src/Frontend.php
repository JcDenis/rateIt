<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt;

use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;

/**
 * @brief       rateIt frontend class.
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Frontend
{
    use TraitProcess;

    /**
     * Template values.
     *
     * @var     array<int, string>  VALUES
     */
    private const VALUES = [
        'rateItTitle', 'rateItAnchor', 'rateItLinker', 'rateItFullnote', 'rateItQuotient', 'rateItTotal',
        'rateItMax', 'rateItMin', 'rateItMaxCount', 'rateItMinCount', 'rateItNote',
    ];

    public static function init(): bool
    {
        return self::status(My::checkContext(My::FRONTEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // Widgets are registered even if plugin is not active (they check it)
        App::behavior()->addBehavior('initWidgets', Widgets::init(...));

        $tpl = App::frontend()->template();

        if (!My::isActive()) {
            $tpl->addBlock('rateIt', FrontendTemplate::disable(...));
            $tpl->addBlock('rateItIf', FrontendTemplate::disable(...));
            foreach (self::VALUES as $value) {
                $tpl->addValue($value, FrontendTemplate::disable(...));
            }

            return true;
        }

        $tpl->addBlock('rateIt', FrontendTemplate::rateIt(...));
        $tpl->addBlock('rateItIf', FrontendTemplate::rateItIf(...));
        foreach (self::VALUES as $value) {
            $tpl->addValue($value, [FrontendTemplate::class, $value]);
        }

        App::behavior()->addBehaviors([
            'publicBeforeDocumentV2'    => FrontendBehaviors::publicBeforeDocumentV2(...),
            'publicHeadContent'         => FrontendBehaviors::publicHeadContent(...),
            'publicEntryAfterContent'   => FrontendBehaviors::publicEntryAfterContent(...),
            'publicCommentAfterContent' => FrontendBehaviors::publicCommentAfterContent(...),
            'cinecturlink2WidgetLinks'  => FrontendBehaviors::cinecturlink2WidgetLinks(...),
        ]);

        return true;
    }
}
