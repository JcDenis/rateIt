<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt;

use Dotclear\App;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;

/**
 * @brief       rateIt public rendering.
 * @ingroup     rateIt
 *
 * Keeps the stack of rated items for template blocks.
 *
 * @phpstan-import-type RateItStats from Rates
 *
 * @phpstan-type RateItFrame array{
 *     module: Module,
 *     style: string,
 *     voted: bool,
 *     stats: RateItStats
 * }
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Render
{
    /**
     * Item given by a behavior.
     *
     * @var     null|array{0: string, 1: string}    $pending
     */
    private static ?array $pending = null;

    /**
     * Stack of items in template blocks.
     *
     * @var     array<int, RateItFrame>     $stack
     */
    private static array $stack = [];

    /**
     * Forms counter for unique HTML IDs.
     */
    private static int $count = 0;

    /**
     * Used items anchors.
     *
     * @var     array<string, bool>     $anchors
     */
    private static array $anchors = [];

    /**
     * Render rating template for an item.
     */
    public static function template(string $type, string $id): string
    {
        self::$pending = [$type, $id];
        $res           = App::frontend()->template()->getData('rateit.html');
        self::$pending = null;

        return $res;
    }

    /**
     * Start a rating block.
     *
     * @param   string  $type   Restrict to this module
     * @param   string  $style  Force this style
     */
    public static function start(string $type = '', string $style = ''): bool
    {
        if (!My::isActive()) {
            return false;
        }

        $module = null;
        $id     = null;
        if (self::$pending !== null) {
            if ($type === '' || $type === self::$pending[0]) {
                $module = My::activeModules()[self::$pending[0]] ?? null;
                $id     = self::$pending[1];
            }
        } else {
            foreach (My::activeModules() as $mid => $m) {
                if ($type === '' || $type === $mid) {
                    $id = $m->contextId();
                    if ($id !== null) {
                        $module = $m;

                        break;
                    }
                }
            }
        }

        if ($module === null || $id === null) {
            return false;
        }

        self::$stack[] = [
            'module' => $module,
            'style'  => in_array($style, My::STYLES, true) ? $style : My::style(),
            'voted'  => Rates::voted($module->id(), $id),
            'stats'  => Rates::stats($module->id(), $id),
        ];

        return true;
    }

    /**
     * End a rating block.
     */
    public static function end(): void
    {
        array_pop(self::$stack);
    }

    /**
     * Get current block.
     *
     * @return  null|RateItFrame
     */
    public static function current(): ?array
    {
        return self::$stack === [] ? null : self::$stack[count(self::$stack) - 1];
    }

    /**
     * Check a condition on current block.
     */
    public static function is(string $what, string $value = ''): bool
    {
        $frame = self::current();
        if ($frame === null) {
            return false;
        }

        return match ($what) {
            'has_vote'   => $frame['stats']['total'] > 0,
            'user_voted' => $frame['voted'],
            'type'       => $frame['module']->id() === $value,
            'style'      => $frame['style'] === $value,
            default      => false,
        };
    }

    /**
     * Get current block title.
     */
    public static function title(): string
    {
        $frame = self::current();

        return $frame === null ? '' : $frame['module']->title();
    }

    /**
     * Get current block raw value.
     */
    public static function raw(string $name): string
    {
        $frame = self::current();

        return $frame === null ? '' : self::format($frame['stats'], $name);
    }

    /**
     * Get current block total votes as text.
     */
    public static function totalText(string $none = '', string $one = '', string $more = ''): string
    {
        $frame = self::current();

        return $frame === null ? '' : self::plural($frame['stats']['total'], $none, $one, $more);
    }

    /**
     * Wrap a value of current block.
     *
     * @param   array<int, string>  $texts  Total texts (none, one, more)
     */
    public static function wrap(string $name, string $value, array $texts = []): string
    {
        $frame = self::current();

        return $frame === null ? '' : self::span($frame['stats'], $name, $value, $texts);
    }

    /**
     * Get HTML id attribute of an item (only once per page).
     */
    public static function anchor(string $type, string $id): string
    {
        $anchor = 'rateit-' . rawurlencode($type . '-' . $id);
        if (isset(self::$anchors[$anchor])) {
            return '';
        }
        self::$anchors[$anchor] = true;

        return ' id="' . Html::escapeHTML($anchor) . '"';
    }

    /**
     * Get current block anchor.
     */
    public static function currentAnchor(): string
    {
        $frame = self::current();

        return $frame === null ? '' : self::anchor($frame['stats']['type'], $frame['stats']['id']);
    }

    /**
     * Get current block rating form.
     */
    public static function linker(): string
    {
        $frame = self::current();

        return $frame === null ? '' : self::form($frame['stats'], $frame['voted'], $frame['style']);
    }

    /**
     * Format a value.
     *
     * @param   RateItStats     $stats
     */
    public static function format(array $stats, string $name): string
    {
        $number = fn (float $n): string => Rates::number($n, $stats['digit']);

        return match ($name) {
            'note'     => $number($stats['note']),
            'max'      => $number($stats['max']),
            'min'      => $number($stats['min']),
            'fullnote' => $number($stats['note']) . '/' . $stats['quotient'],
            'quotient' => (string) $stats['quotient'],
            'total'    => (string) $stats['total'],
            'maxcount' => (string) $stats['maxcount'],
            'mincount' => (string) $stats['mincount'],
            default    => '',
        };
    }

    /**
     * Get votes count as text.
     */
    public static function plural(int $total, string $none = '', string $one = '', string $more = ''): string
    {
        return match (true) {
            $total === 0 => sprintf(__($none !== '' ? $none : 'no rate'), $total),
            $total === 1 => sprintf(__($one !== '' ? $one : 'one rate'), $total),
            default      => sprintf(__($more !== '' ? $more : '%d rates'), $total),
        };
    }

    /**
     * Get a value wrapper.
     *
     * @param   RateItStats         $stats
     * @param   array<int, string>  $texts  Total texts (none, one, more)
     */
    public static function span(array $stats, string $name, string $value, array $texts = []): string
    {
        $data = '';
        if ($texts !== []) {
            $data = ' data-none="' . Html::escapeHTML(__($texts[0] ?: 'no rate')) . '"' .
                ' data-one="' . Html::escapeHTML(__($texts[1] ?: 'one rate')) . '"' .
                ' data-more="' . Html::escapeHTML(__($texts[2] ?: '%d rates')) . '"';
        }

        return '<span class="rateit-' . $name . '" data-rateit-item="' . Html::escapeHTML($stats['type'] . '-' . $stats['id']) . '" data-rateit-field="' . $name . '"' . $data . '>' . $value . '</span>';
    }

    /**
     * Get rating form.
     *
     * @param   RateItStats     $stats
     */
    public static function form(array $stats, bool $voted, string $style = ''): string
    {
        $style    = in_array($style, My::STYLES, true) ? $style : My::style();
        $quotient = $stats['quotient'];
        $uid      = 'rateit-' . (++self::$count);
        $dis      = $voted ? ' disabled' : '';
        $item     = Html::escapeHTML($stats['type'] . '-' . $stats['id']);

        $res = '<form class="rateit-linker rateit-' . $style . ($voted ? ' rateit-voted' : '') . '" method="post" action="' . Html::escapeHTML(App::blog()->url() . App::url()->getURLFor('rateit_vote')) . '" data-rateit-item="' . $item . '">' .
            '<input type="hidden" name="type" value="' . Html::escapeHTML($stats['type']) . '">' .
            '<input type="hidden" name="id" value="' . Html::escapeHTML($stats['id']) . '">' .
            '<input type="hidden" name="redir" value="' . Html::escapeHTML(Http::getSelfURI()) . '">';

        if ($style === 'classic') {
            $current = (int) round($stats['note']);
            $res .= '<span class="rateit-stars" role="radiogroup" aria-label="' . Html::escapeHTML(__('Note')) . '">';
            for ($i = $quotient; $i > 0; $i--) {
                $res .= '<input type="radio" name="note" id="' . $uid . '-' . $i . '" value="' . $i . '"' . ($current === $i ? ' checked' : '') . $dis . '>' .
                    '<label for="' . $uid . '-' . $i . '" title="' . $i . '/' . $quotient . '"><span>' . $i . '/' . $quotient . '</span></label>';
            }
            $res .= '</span>';
            if (!$voted) {
                $res .= '<button type="submit" class="rateit-submit">' . __('Vote') . '</button>';
            }
        } else {
            if ($style === 'twin') {
                $res .= '<button type="submit" class="rateit-notlike" name="note" value="1"' . $dis . '>' . Html::escapeHTML(My::message('notlike')) . '</button> ';
            }
            $res .= '<button type="submit" class="rateit-like" name="note" value="' . $quotient . '"' . $dis . '>' . Html::escapeHTML(My::message('like')) . '</button>';
        }

        return $res . '<span class="rateit-message" role="status"></span></form>';
    }
}
