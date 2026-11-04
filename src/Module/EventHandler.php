<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt\Module;

use Dotclear\App;

/**
 * @brief       rateIt events module (plugin eventHandler).
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class EventHandler extends PostType
{
    public function id(): string
    {
        return 'eventhandler';
    }

    public function name(): string
    {
        return __('Events');
    }

    public function title(): string
    {
        return __('Rate this event');
    }

    public function isAvailable(): bool
    {
        return App::plugins()->moduleExists('eventHandler');
    }

    protected function postType(): string
    {
        return 'eventhandler';
    }

    protected function singleUrlTypes(): array
    {
        return ['eventhandler_single'];
    }

    public function options(): array
    {
        return [
            'single' => __('Show rating on event page'),
            'list'   => __('Show rating on events of events list page'),
        ];
    }

    public function entryId(): ?string
    {
        return $this->isPlace([
            'single' => 'eventhandler_single',
            'list'   => 'eventhandler_list',
        ]) ? $this->contextId() : null;
    }
}
