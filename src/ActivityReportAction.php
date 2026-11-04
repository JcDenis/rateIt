<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt;

use Dotclear\Database\Cursor;
use Dotclear\Helper\Process\TraitProcess;
use Dotclear\Plugin\activityReport\Action;
use Dotclear\Plugin\activityReport\ActivityReport;
use Dotclear\Plugin\activityReport\Group;

/**
 * @brief       rateIt plugin activityReport class.
 * @ingroup     rateIt
 *
 * Add rating actions to the plugin activity report.
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ActivityReportAction
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(true);
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        $group = new Group(My::id(), My::name());

        # from BEHAVIOR rateItAfterSet in rateIt/src/Rates.php
        $group->add(new Action(
            'vote',
            __('New vote'),
            __('New vote of type "%s" on "%s" with note %s/%s'),
            'rateItAfterSet',
            function (Cursor $cur): void {
                $type   = $cur->getField('rateit_type');
                $id     = $cur->getField('rateit_id');
                $module = My::module(is_string($type) ? $type : '');
                $item   = $module?->item(is_string($id) ? $id : '');
                $note   = $cur->getField('rateit_note');
                $quot   = $cur->getField('rateit_quotient');

                ActivityReport::instance()->addLog(My::id(), 'vote', [
                    $module?->name() ?? (is_string($type) ? $type : ''),
                    $item['title'] ?? (is_string($id) ? $id : ''),
                    is_numeric($note) ? (string) $note : '',
                    is_numeric($quot) ? (string) $quot : '',
                ]);
            }
        ));

        ActivityReport::instance()->groups->add($group);

        return true;
    }
}
