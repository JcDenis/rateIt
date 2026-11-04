<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt\Module;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Plugin\rateIt\Module;

/**
 * @brief       rateIt links module (plugin cinecturlink2).
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Cinecturlink2 extends Module
{
    public function id(): string
    {
        return 'cinecturlink2';
    }

    public function name(): string
    {
        return __('Cinecturlink');
    }

    public function isAvailable(): bool
    {
        return App::plugins()->moduleExists('cinecturlink2');
    }

    public function options(): array
    {
        return [
            'widget' => __('Show rating on links of cinecturlink widget'),
        ];
    }

    public function contextId(): ?string
    {
        $ctx = App::frontend()->context();
        if ($ctx->exists('c2_entries') && $ctx->c2_entries instanceof MetaRecord && !$ctx->c2_entries->isEmpty()) {
            return (string) $ctx->c2_entries->intField('link_id');
        }

        return null;
    }

    public function item(string $id): ?array
    {
        if (!is_numeric($id)) {
            return null;
        }

        $sql = new SelectStatement();
        $rs  = $sql
            ->columns(['link_title'])
            ->from(App::db()->con()->prefix() . 'cinecturlink2')
            ->where('blog_id = ' . $sql->quote(App::blog()->id()))
            ->and('link_id = ' . (int) $id)
            ->select();

        return $rs === null || $rs->isEmpty() ? null : [
            'title' => $rs->strField('link_title'),
            'url'   => App::blog()->url() . App::url()->getURLFor('cinecturlink2', 'c2detail/' . $id),
        ];
    }
}
