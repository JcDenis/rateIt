<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt\Module;

/**
 * @brief       rateIt entries module.
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Post extends PostType
{
    public function id(): string
    {
        return 'post';
    }

    public function name(): string
    {
        return __('Entries');
    }

    public function title(): string
    {
        return __('Rate this entry');
    }

    protected function postType(): string
    {
        return 'post';
    }

    public function options(): array
    {
        return [
            'single'   => __('Show rating on entry page'),
            'home'     => __('Show rating on entries of home page'),
            'category' => __('Show rating on entries of categories pages'),
            'tag'      => __('Show rating on entries of tags pages'),
        ];
    }

    public function entryId(): ?string
    {
        return $this->isPlace([
            'single'   => 'post',
            'home'     => 'default',
            'category' => 'category',
            'tag'      => 'tag',
        ]) ? $this->contextId() : null;
    }
}
