<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt;

use ArrayObject;
use Dotclear\App;

/**
 * @brief       rateIt frontend template tags.
 * @ingroup     rateIt
 *
 * Same tags as old versions:
 * - block rateIt (attributes type, style)
 * - block rateItIf (attributes has_vote, user_voted, type, style, operator)
 * - values rateItTitle, rateItLinker, rateItFullnote, rateItQuotient, rateItTotal,
 *   rateItMax, rateItMin, rateItMaxCount, rateItMinCount, rateItNote
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class FrontendTemplate
{
    /**
     * Template tags when plugin is not active.
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    public static function disable(ArrayObject $attr, string $content = ''): string
    {
        return '';
    }

    /**
     * Get string attribute.
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    private static function attr(ArrayObject $attr, string $name): string
    {
        return isset($attr[$name]) && is_string($attr[$name]) ? $attr[$name] : '';
    }

    /**
     * Block: rating item.
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    public static function rateIt(ArrayObject $attr, string $content): string
    {
        return '<?php if (' . Render::class . '::start(' . var_export(self::attr($attr, 'type'), true) . ', ' . var_export(self::attr($attr, 'style'), true) . ')) : ?>' .
            $content .
            '<?php ' . Render::class . '::end(); endif; ?>';
    }

    /**
     * Block: rating conditions.
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    public static function rateItIf(ArrayObject $attr, string $content): string
    {
        $if = [];
        foreach (['has_vote', 'user_voted'] as $name) {
            if (isset($attr[$name])) {
                $if[] = ((bool) $attr[$name] ? '' : '!') . Render::class . '::is(' . var_export($name, true) . ')';
            }
        }
        foreach (['type', 'style'] as $name) {
            $value = self::attr($attr, $name);
            if ($value !== '') {
                $not   = str_starts_with($value, '!');
                $if[]  = ($not ? '!' : '') . Render::class . '::is(' . var_export($name, true) . ', ' . var_export(ltrim($value, '!'), true) . ')';
            }
        }

        if ($if === []) {
            return $content;
        }

        $operator = App::frontend()->template()->getOperator(self::attr($attr, 'operator') ?: '&&');

        return '<?php if (' . implode(' ' . $operator . ' ', $if) . ') : ?>' . $content . '<?php endif; ?>';
    }

    /**
     * Value: rating title.
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    public static function rateItTitle(ArrayObject $attr): string
    {
        return '<?= ' . sprintf(App::frontend()->template()->getFilters($attr), Render::class . '::title()') . ' ?>';
    }

    /**
     * Value: HTML id attribute of rating block (ex: id="rateit-post-1").
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    public static function rateItAnchor(ArrayObject $attr): string
    {
        return '<?= ' . Render::class . '::currentAnchor() ?>';
    }

    /**
     * Value: rating form.
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    public static function rateItLinker(ArrayObject $attr): string
    {
        return '<?= ' . Render::class . '::linker() ?>';
    }

    /**
     * Value: note with quotient (ex: 4/5).
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    public static function rateItFullnote(ArrayObject $attr): string
    {
        return self::value($attr, 'fullnote');
    }

    /**
     * Value: quotient.
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    public static function rateItQuotient(ArrayObject $attr): string
    {
        return self::value($attr, 'quotient');
    }

    /**
     * Value: total number of votes.
     *
     * Attribute totaltext="1" displays text, with optional attributes none, one, more.
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    public static function rateItTotal(ArrayObject $attr): string
    {
        if (empty($attr['totaltext'])) {
            return self::value($attr, 'total');
        }

        $texts = [self::attr($attr, 'none'), self::attr($attr, 'one'), self::attr($attr, 'more')];

        return '<?= ' . Render::class . '::wrap(\'total\', ' .
            sprintf(App::frontend()->template()->getFilters($attr), Render::class . '::totalText(' . implode(', ', array_map(fn (string $t): string => var_export($t, true), $texts)) . ')') .
            ', ' . var_export($texts, true) . ') ?>';
    }

    /**
     * Value: higher note.
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    public static function rateItMax(ArrayObject $attr): string
    {
        return self::value($attr, 'max');
    }

    /**
     * Value: lower note.
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    public static function rateItMin(ArrayObject $attr): string
    {
        return self::value($attr, 'min');
    }

    /**
     * Value: number of positive votes.
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    public static function rateItMaxCount(ArrayObject $attr): string
    {
        return self::value($attr, 'maxcount');
    }

    /**
     * Value: number of negative votes.
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    public static function rateItMinCount(ArrayObject $attr): string
    {
        return self::value($attr, 'mincount');
    }

    /**
     * Value: average note.
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    public static function rateItNote(ArrayObject $attr): string
    {
        return self::value($attr, 'note');
    }

    /**
     * Generic value.
     *
     * @param   ArrayObject<string, mixed>  $attr
     */
    private static function value(ArrayObject $attr, string $name): string
    {
        return '<?= ' . Render::class . '::wrap(' . var_export($name, true) . ', ' .
            sprintf(App::frontend()->template()->getFilters($attr), Render::class . '::raw(' . var_export($name, true) . ')') .
            ') ?>';
    }
}
