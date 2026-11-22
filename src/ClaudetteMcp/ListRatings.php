<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt\ClaudetteMcp;

use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpTool;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;
use Dotclear\Plugin\rateIt\My;
use Dotclear\Plugin\rateIt\Rates;

/**
 * @brief       rateIt tool for the plugin ClaudetteMcp: list rated items.
 * @ingroup     rateIt
 *
 * ClaudetteMcp connects an AI assistant to the blog. Read only, like the admin page, without the voters.
 * These classes are only loaded by ClaudetteMcp, which is not required by rateIt.
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ListRatings extends McpTool
{
    /**
     * Orders, by name.
     *
     * @var     array<string, string>   ORDERS
     */
    private const ORDERS = [
        'note'       => 'rateit_avg',
        'votes'      => 'rateit_total',
        'last_vote'  => 'rateit_time',
    ];

    public function name(): string
    {
        return 'list_ratings';
    }

    public function title(): string
    {
        return 'List rated items';
    }

    public function description(): string
    {
        return 'List the items rated by visitors of the blog (plugin rateIt): entries, comments, categories, tags... ' .
            'with their average note (out of "quotient"), their number of votes and of positive votes, best first by default. Voters are never identified.';
    }

    public function properties(): array
    {
        return [
            'type' => [
                'type'        => 'string',
                'enum'        => array_keys(My::modules()),
                'description' => 'Limit to a type of rated items.',
            ],
            'order' => [
                'type'        => 'string',
                'enum'        => array_keys(self::ORDERS),
                'description' => 'Order, highest first, default: note.',
            ],
            'limit' => [
                'type'        => 'integer',
                'description' => 'Items per page, 1 to 100, default: 20.',
            ],
            'page' => [
                'type'        => 'integer',
                'description' => 'Page number, default: 1.',
            ],
        ];
    }

    /**
     * Like in backend: ratings are for content administrators.
     */
    public function available(): bool
    {
        return $this->can(App::auth()::PERMISSION_CONTENT_ADMIN);
    }

    public function call(array $args): array
    {
        $modules = My::modules();
        $type    = $this->str($args, 'type');
        $order   = $this->str($args, 'order', 'note');
        $limit   = $this->int($args, 'limit', 20, 1, 100);
        $page    = $this->int($args, 'page', 1, 1);

        if ($type !== '' && !isset($modules[$type])) {
            throw new McpToolException('Unknown type, use one of: ' . implode(', ', array_keys($modules)));
        }
        if (!isset(self::ORDERS[$order])) {
            throw new McpToolException('Unknown order, use one of: ' . implode(', ', array_keys(self::ORDERS)));
        }

        $params = [
            'rateit_type' => $type !== '' ? $type : array_keys($modules),
            'order'       => self::ORDERS[$order] . ' desc',
            'limit'       => [($page - 1) * $limit, $limit],
        ];

        $rs    = Rates::getRates($params);
        $items = [];
        while ($rs->fetch()) {
            $item_type = $rs->strField('rateit_type');
            $item_id   = $rs->strField('rateit_id');
            // Public title and address (admin addresses do not exist out of backend)
            $item = (isset($modules[$item_type]) ? $modules[$item_type]->item($item_id) : null) ?? ['title' => $item_id, 'url' => ''];

            $items[] = [
                'type'     => $item_type,
                'id'       => $item_id,
                'title'    => $item['title'],
                'url'      => $item['url'],
                'note'     => Rates::note(Rates::num($rs, 'rateit_avg')),
                'votes'    => $rs->intField('rateit_total'),
                'positive' => $rs->intField('rateit_positive'),
                'last'     => $rs->strField('rateit_time'),
            ];
        }

        return [
            'active'   => My::isActive(),
            'quotient' => My::quotient(),
            'votes'    => Rates::countVotes(),
            'total'    => Rates::getRates($params, true)->intField(0),
            'page'     => $page,
            'limit'    => $limit,
            'items'    => $items,
        ];
    }
}
