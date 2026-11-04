<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt;

use Dotclear\App;
use Dotclear\Core\Backend\Listing\Pager;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Form\Caption;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Table;
use Dotclear\Helper\Html\Form\Tbody;
use Dotclear\Helper\Html\Form\Td;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Th;
use Dotclear\Helper\Html\Form\Thead;
use Dotclear\Helper\Html\Form\Tr;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Process\TraitProcess;
use Exception;

/**
 * @brief       rateIt backend manage page.
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Manage
{
    use TraitProcess;

    /**
     * Items per page.
     *
     * @var     int     NB_PER_PAGE
     */
    private const NB_PER_PAGE = 30;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::MANAGE));
    }

    /**
     * Get a request parameter.
     */
    private static function req(string $key): string
    {
        return isset($_REQUEST[$key]) && is_string($_REQUEST[$key]) ? $_REQUEST[$key] : '';
    }

    /**
     * Get current part.
     */
    private static function part(): string
    {
        return self::req('part') === 'detail' && self::req('type') !== '' && self::req('id') !== '' ? 'detail' : 'items';
    }

    /**
     * Get current list filters.
     *
     * @return  array{type: string, order: string, page: int}
     */
    private static function filters(): array
    {
        $type  = self::req('type');
        $order = self::req('order');

        return [
            'type'  => isset(My::modules()[$type]) ? $type : '',
            'order' => in_array($order, ['rateit_time', 'rateit_avg', 'rateit_total'], true) ? $order : 'rateit_time',
            'page'  => max(1, (int) self::req('page')),
        ];
    }

    public static function process(): bool
    {
        if (!self::status() || $_POST === []) {
            return self::status();
        }

        try {
            // Delete votes of selected items
            if (!empty($_POST['items_delete'])) {
                $items = is_array($_POST['items'] ?? null) ? array_filter($_POST['items'], is_string(...)) : [];
                foreach ($items as $item) {
                    [$type, $id] = array_pad(explode(':', $item, 2), 2, '');
                    if ($type !== '' && $id !== '') {
                        Rates::delete($type, $id);
                    }
                }

                Notices::addSuccessNotice(__('Votes of selected items have been deleted.'));
                My::redirect(array_filter(self::filters(), fn ($v): bool => $v !== ''));
            }

            // Delete votes of selected voters
            if (!empty($_POST['voters_delete']) && self::part() === 'detail') {
                $voters = is_array($_POST['voters'] ?? null) ? array_values(array_filter($_POST['voters'], is_string(...))) : [];
                if ($voters !== []) {
                    Rates::delete(self::req('type'), self::req('id'), $voters);
                }

                Notices::addSuccessNotice(__('Selected votes have been deleted.'));
                My::redirect(['part' => 'detail', 'type' => self::req('type'), 'id' => self::req('id')]);
            }
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        return true;
    }

    public static function render(): void
    {
        if (!self::status()) {
            return;
        }

        if (self::part() === 'detail') {
            self::renderDetail();

            return;
        }

        $filters = self::filters();
        $params  = [
            'rateit_type' => $filters['type'] !== '' ? $filters['type'] : array_keys(My::modules()),
            'order'       => $filters['order'] . ' desc',
            'limit'       => [($filters['page'] - 1) * self::NB_PER_PAGE, self::NB_PER_PAGE],
        ];

        $rs    = Rates::getRates($params);
        $count = Rates::getRates($params, true)->intField(0);

        Page::openModule(
            My::name(),
            Page::jsLoad('js/_posts_list.js')
        );

        echo
        Page::breadcrumb([
            Html::escapeHTML(App::blog()->name()) => '',
            My::name()                            => '',
        ]) .
        Notices::getNotices();

        if (!My::isActive()) {
            echo (new Note())
                ->class('warning')
                ->text(__('Rating is disabled on this blog. Enable it from plugin configuration.'))
                ->render();
        }

        $total = Rates::countVotes();
        echo (new Note())
            ->text(match ($total) {
                0       => __('There is no vote at this time.'),
                1       => __('There is only one vote at this time.'),
                default => sprintf(__('There is a total of %s votes on this blog.'), $total),
            })
            ->render();

        // Filters
        echo (new Form('rateit-filters'))
            ->method('get')
            ->action('index.php')
            ->fields([
                (new Para())->class('form-buttons')->items([
                    (new Select('type'))
                        ->items(array_merge([__('All') => ''], My::modulesCombo()))
                        ->default($filters['type'])
                        ->label(new Label(__('Type:'), Label::IL_TF)),
                    (new Select('order'))
                        ->items([
                            __('Last vote') => 'rateit_time',
                            __('Note')      => 'rateit_avg',
                            __('Votes')     => 'rateit_total',
                        ])
                        ->default($filters['order'])
                        ->label(new Label(__('Order by:'), Label::IL_TF)),
                    (new Submit(['filter_submit'], __('Apply'))),
                    (new Hidden(['process'], 'Plugin')),
                    (new Hidden(['p'], My::id())),
                ]),
            ])
            ->render();

        if ($rs->isEmpty()) {
            echo (new Note())
                ->class('info')
                ->text(__('There is no rating for this request at this time.'))
                ->render();
            Page::helpBlock(My::id());
            Page::closeModule();

            return;
        }

        $quotient = My::quotient();
        $digit    = My::digit();
        $user_tz  = is_string($user_tz = App::auth()->getInfo('user_tz')) ? $user_tz : 'UTC';
        $rows     = [];
        while ($rs->fetch()) {
            $type   = $rs->strField('rateit_type');
            $id     = $rs->strField('rateit_id');
            $module = My::module($type);
            $item   = $module?->adminItem($id) ?? ['title' => $id, 'url' => ''];

            $rows[] = (new Tr())->class('line')->items([
                (new Td())->class('nowrap')->items([
                    (new Checkbox(['items[]'], false))
                        ->value(Html::escapeHTML($type . ':' . $id))
                        ->title(__('Select this item')),
                ]),
                (new Td())->class('maximal')->items([
                    (new Link())
                        ->href(My::manageUrl(['part' => 'detail', 'type' => $type, 'id' => $id]))
                        ->title(__('Detail'))
                        ->text(Html::escapeHTML($item['title'])),
                    $item['url'] !== '' ?
                        (new Link())
                            ->href(Html::escapeHTML($item['url']))
                            ->class('outgoing')
                            ->title(__('Edit'))
                            ->text(' <img src="images/outgoing-link.svg" alt="">') :
                        (new Text()),
                ]),
                (new Td())->class('nowrap')->text(Html::escapeHTML($module?->name() ?? $type)),
                (new Td())->class(['nowrap', 'count'])->text(Rates::number(Rates::num($rs, 'rateit_avg') * $quotient, $digit) . '/' . $quotient),
                (new Td())->class(['nowrap', 'count'])->text((string) $rs->intField('rateit_total')),
                (new Td())->class(['nowrap', 'count'])->text((string) $rs->intField('rateit_positive')),
                (new Td())->class(['nowrap', 'count'])->text(Date::str(__('%Y-%m-%d %H:%M'), (int) strtotime($rs->strField('rateit_time') . ' UTC'), $user_tz)),
            ]);
        }

        $pager = new Pager($filters['page'], $count, self::NB_PER_PAGE, 10);

        echo
        $pager->getLinks() .
        (new Form('form-entries'))
            ->method('post')
            ->action(My::manageUrl(array_filter($filters, fn ($v): bool => $v !== '')))
            ->fields([
                (new Div())->class('table-outer')->items([
                    (new Table())->class('maximal')
                        ->caption(new Caption(sprintf(__('List of rated items (%s)'), $count)))
                        ->items([
                            (new Thead())->rows([(new Tr())->items([
                                (new Th())->scope('col')->colspan(2)->class('first')->text(__('Item')),
                                (new Th())->scope('col')->text(__('Type')),
                                (new Th())->scope('col')->text(__('Note')),
                                (new Th())->scope('col')->text(__('Votes')),
                                (new Th())->scope('col')->text(__('Positive')),
                                (new Th())->scope('col')->text(__('Last vote')),
                            ])]),
                            (new Tbody())->rows($rows),
                        ]),
                ]),
                (new Div())->class('two-cols')->items([
                    (new Para())->class(['col', 'checkboxes-helpers']),
                    (new Para())->class(['col', 'right', 'form-buttons'])->items([
                        (new Submit(['items_delete'], __('Delete votes of selected items')))->class('delete'),
                        App::nonce()->formNonce(),
                    ]),
                ]),
            ])
            ->render() .
        $pager->getLinks();

        Page::helpBlock(My::id());
        Page::closeModule();
    }

    /**
     * Render votes of an item.
     */
    private static function renderDetail(): void
    {
        $type   = self::req('type');
        $id     = self::req('id');
        $module = My::module($type);
        $item   = $module?->adminItem($id) ?? ['title' => $id, 'url' => ''];
        $stats  = Rates::stats($type, $id);

        Page::openModule(
            __('Detail') . ' - ' . My::name(),
            Page::jsLoad('js/_posts_list.js')
        );

        echo
        Page::breadcrumb([
            Html::escapeHTML(App::blog()->name()) => '',
            My::name()                            => My::manageUrl(),
            Html::escapeHTML($module?->name() ?? $type) . ' &ldquo;' . Html::escapeHTML($item['title']) . '&rdquo;' => '',
        ]) .
        Notices::getNotices();

        echo (new Para())->items([
            (new Text(null, sprintf(
                __('Note: %s - Votes: %s - Higher: %s - Lower: %s'),
                Render::format($stats, 'fullnote'),
                Render::format($stats, 'total'),
                Render::format($stats, 'max'),
                Render::format($stats, 'min')
            ) . ' ')),
            $item['url'] !== '' ?
                (new Link())->href(Html::escapeHTML($item['url']))->text(__('Edit item')) :
                (new Text()),
        ])->render();

        $user_tz = is_string($user_tz = App::auth()->getInfo('user_tz')) ? $user_tz : 'UTC';
        $rows    = [];
        $rs      = Rates::getVotes($type, $id);
        while ($rs->fetch()) {
            $voter  = $rs->strField('rateit_ip');
            $rows[] = (new Tr())->class('line')->items([
                (new Td())->class('nowrap')->items([
                    (new Checkbox(['voters[]'], false))
                        ->value(Html::escapeHTML($voter))
                        ->title(__('Select this vote')),
                ]),
                (new Td())->class('maximal')->text(Html::escapeHTML(mb_strlen($voter) > 16 ? mb_substr($voter, 0, 16) . '…' : $voter)),
                (new Td())->class(['nowrap', 'count'])->text(Rates::number(Rates::num($rs, 'rateit_note')) . '/' . Rates::number(Rates::num($rs, 'rateit_quotient'))),
                (new Td())->class(['nowrap', 'count'])->text(Date::str(__('%Y-%m-%d %H:%M'), (int) strtotime($rs->strField('rateit_time') . ' UTC'), $user_tz)),
            ]);
        }

        if ($rows === []) {
            echo (new Note())->class('info')->text(__('There is no vote for this item.'))->render();
        } else {
            echo (new Form('form-votes'))
                ->method('post')
                ->action(My::manageUrl(['part' => 'detail', 'type' => $type, 'id' => $id]))
                ->fields([
                    (new Div())->class('table-outer')->items([
                        (new Table())->class('maximal')
                            ->caption(new Caption(sprintf(__('Votes (%s)'), count($rows))))
                            ->items([
                                (new Thead())->rows([(new Tr())->items([
                                    (new Th())->scope('col')->colspan(2)->class('first')->text(__('Voter')),
                                    (new Th())->scope('col')->text(__('Note')),
                                    (new Th())->scope('col')->text(__('Date')),
                                ])]),
                                (new Tbody())->rows($rows),
                            ]),
                    ]),
                    (new Div())->class('two-cols')->items([
                        (new Para())->class(['col', 'checkboxes-helpers']),
                        (new Para())->class(['col', 'right', 'form-buttons'])->items([
                            (new Submit(['voters_delete'], __('Delete selected votes')))->class('delete'),
                            App::nonce()->formNonce(),
                        ]),
                    ]),
                ])
                ->render();
        }

        echo (new Para())->items([
            (new Link())->href(My::manageUrl())->class('back')->text(__('Back to rated items list')),
        ])->render();

        Page::helpBlock(My::id());
        Page::closeModule();
    }
}
