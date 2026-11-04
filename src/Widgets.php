<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt;

use Dotclear\App;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Text;
use Dotclear\Plugin\widgets\WidgetsElement;
use Dotclear\Plugin\widgets\WidgetsStack;

/**
 * @brief       rateIt widgets.
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Widgets
{
    /**
     * Rank widget wildcards.
     *
     * @var     string  WILDCARDS
     */
    private const WILDCARDS = '%rank%, %title%, %note%, %quotient%, %percent%, %count%, %totaltext%';

    /**
     * Widgets registration.
     */
    public static function init(WidgetsStack $w): void
    {
        $w
            ->create(
                'rateit',
                __('Rate it: rating'),
                self::vote(...),
                null,
                __('Rating form of current page item')
            )
            ->addTitle('')
            ->setting('show_fullnote', __('Show note next to rating form'), 1, 'check')
            ->setting('show_note', __('Show note'), 1, 'check')
            ->setting('show_vote', __('Show the count of votes'), 1, 'check')
            ->setting('show_higher', __('Show the highest note'), 1, 'check')
            ->setting('show_lower', __('Show the lowest note'), 1, 'check')
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $w
            ->create(
                'rateitrank',
                __('Rate it: top rating'),
                self::rank(...),
                null,
                __('List of top rated items')
            )
            ->addTitle(__('Top rated entries'))
            ->setting('type', __('Type:'), 'post', 'combo', My::modulesCombo())
            ->setting('limit', __('Length:'), 3, 'combo', [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 10 => 10, 15 => 15, 20 => 20])
            ->setting('sortby', __('Order by:'), 'rateit_avg', 'combo', [
                __('Note')                                 => 'rateit_avg',
                __('Votes')                                => 'rateit_total',
                __('Date')                                 => 'rateit_time',
                __('Positive votes (for simple/twin mode)') => 'rateit_positive',
            ])
            ->setting('sort', __('Sort:'), 'desc', 'combo', [
                __('Ascending')  => 'asc',
                __('Descending') => 'desc',
            ])
            ->setting('text', sprintf(__('Text (use wildcards %s):'), self::WILDCARDS), '%rank% %title% (%note%/%quotient%)')
            ->setting('titlelen', __('Title length (if truncate):'), 100)
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();
    }

    /**
     * Rating widget.
     */
    public static function vote(WidgetsElement $w): string
    {
        if (!My::isActive() || $w->isOffline()) {
            return '';
        }

        $module = null;
        $id     = null;
        foreach (My::activeModules() as $m) {
            if (($id = $m->widgetId()) !== null) {
                $module = $m;

                break;
            }
        }
        if ($module === null || $id === null) {
            return '';
        }

        $stats = Rates::stats($module->id(), $id);
        $style = My::style();
        $value = fn (string $field): string => Render::span($stats, $field, Render::format($stats, $field));

        $res = '';
        if ($w->get('show_fullnote')) {
            $res .= '<p class="rateit-summary">' . match ($style) {
                'classic' => $value('fullnote'),
                'twin'    => $value('mincount') . ' / ' . $value('maxcount'),
                default   => $value('maxcount'),
            } . '</p>';
        }

        $res .= Render::form($stats, Rates::voted($module->id(), $id), $style);

        $list = '';
        if ($w->get('show_note')) {
            $list .= '<li>' . __('Note:') . ' ' . $value('note') . '</li>';
        }
        if ($w->get('show_vote')) {
            $list .= '<li>' . __('Votes:') . ' ' . $value('total') . '</li>';
        }
        if ($w->get('show_higher')) {
            $list .= '<li>' . __('Higher:') . ' ' . $value('max') . '</li>';
        }
        if ($w->get('show_lower')) {
            $list .= '<li>' . __('Lower:') . ' ' . $value('min') . '</li>';
        }
        if ($list !== '') {
            $res .= '<ul>' . $list . '</ul>';
        }

        $title = self::str($w, 'title') !== '' ? self::str($w, 'title') : $module->title();

        return $w->renderDiv(
            (bool) $w->get('content_only'),
            'rateit rateit-widget ' . self::str($w, 'class'),
            ltrim(Render::anchor($module->id(), $id)),
            $w->renderTitle(Html::escapeHTML($title)) . $res
        );
    }

    /**
     * Top rating widget.
     */
    public static function rank(WidgetsElement $w): string
    {
        if (!My::isActive() || $w->isOffline() || !$w->checkHomeOnly(App::url()->getType())) {
            return '';
        }

        $module = My::activeModules()[self::str($w, 'type')] ?? null;
        if ($module === null) {
            return '';
        }

        $limit  = max(1, (int) self::str($w, 'limit'));
        $sortby = self::str($w, 'sortby');
        $params = [
            'rateit_type' => $module->id(),
            'order'       => $sortby . ' ' . (self::str($w, 'sort') === 'asc' ? 'asc' : 'desc'),
            'positive'    => $sortby === 'rateit_positive',
            // Some items may not be public
            'limit'       => $limit * 3,
        ];

        $rs       = Rates::getRates($params);
        $quotient = My::quotient();
        $digit    = My::digit();
        $cut      = max(1, (int) self::str($w, 'titlelen'));
        $text     = self::str($w, 'text');
        $rank     = 0;
        $res      = '';

        while ($rs->fetch() && $rank < $limit) {
            $item = $module->item($rs->strField('rateit_id'));
            if ($item === null) {
                continue;
            }

            $rank++;
            $title = Html::escapeHTML(mb_strlen($item['title']) > $cut ? Text::cutString($item['title'], $cut) . '...' : $item['title']);
            $avg   = Rates::num($rs, 'rateit_avg');
            $total = $rs->intField('rateit_total');

            $res .= '<li>' . str_replace(
                ['%rank%', '%title%', '%note%', '%quotient%', '%percent%', '%count%', '%totaltext%'],
                [
                    '<span class="rateit-rank">' . $rank . '</span>',
                    '<a href="' . Html::escapeHTML($item['url']) . '">' . $title . '</a>',
                    Rates::number($avg * $quotient, $digit),
                    (string) $quotient,
                    (string) floor($avg * 100),
                    (string) $total,
                    Render::plural($total),
                ],
                Html::escapeHTML($text)
            ) . '</li>';
        }

        if ($res === '') {
            return '';
        }

        return $w->renderDiv(
            (bool) $w->get('content_only'),
            'rateitpostsrank rateittype rateit-rank-' . $module->id() . ' ' . self::str($w, 'class'),
            '',
            (self::str($w, 'title') !== '' ? $w->renderTitle(Html::escapeHTML(self::str($w, 'title'))) : '') . '<ul>' . $res . '</ul>'
        );
    }

    /**
     * Get a widget setting as string.
     */
    private static function str(WidgetsElement $w, string $key): string
    {
        $value = $w->get($key);

        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }
}
