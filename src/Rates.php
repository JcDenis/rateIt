<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt;

use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Helper\Crypt;
use Dotclear\Helper\Network\Http;
use Exception;

/**
 * @brief       rateIt votes data layer.
 * @ingroup     rateIt
 *
 * Votes are stored in table rateit:
 * - rateit_type is the module ID,
 * - rateit_id is the rated item ID,
 * - rateit_note is the note (from 1 to rateit_quotient),
 * - rateit_quotient is the maximum note at the time of the vote,
 * - rateit_ip is the voter (hash of IP or random ID, plain IP for old votes).
 *
 * @phpstan-type RateItStats array{
 *     type: string,
 *     id: string,
 *     note: float,
 *     max: float,
 *     min: float,
 *     maxcount: int,
 *     mincount: int,
 *     total: int,
 *     quotient: int,
 *     digit: int
 * }
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Rates
{
    /**
     * Current voter ID set on this request.
     */
    private static string $voter = '';

    /**
     * Get a numeric field of a record.
     */
    public static function num(MetaRecord $rs, string|int $field): float
    {
        $value = $rs->f($field);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Format a number without useless digits.
     */
    public static function number(float $value, int $digit = 2): string
    {
        return rtrim(rtrim(number_format($value, $digit, '.', ''), '0'), '.');
    }

    /**
     * Get votes table name.
     */
    public static function table(): string
    {
        return App::db()->con()->prefix() . My::TABLE_NAME;
    }

    /**
     * Open a cursor on votes table.
     */
    public static function openCursor(): Cursor
    {
        return App::db()->con()->openCursor(self::table());
    }

    /**
     * Get hash of visitor IP.
     */
    public static function ipHash(): string
    {
        return 'ip:' . substr(Crypt::hmac(App::config()->masterKey(), Http::realIP() ?? ''), 0, 40);
    }

    /**
     * Get voter IDs that identify current visitor.
     *
     * @return  array<int, string>
     */
    public static function voters(): array
    {
        $voters = [];
        if (My::ident() !== My::IDENT_COOKIE) {
            $voters[] = self::ipHash();
            // Votes from old versions store plain IP
            if (($ip = Http::realIP()) !== null && $ip !== '') {
                $voters[] = $ip;
            }
        } elseif (($voter = self::cookieVoter()) !== '') {
            $voters[] = $voter;
        }

        return $voters;
    }

    /**
     * Get voter ID from cookie.
     */
    private static function cookieVoter(): string
    {
        if (self::$voter !== '') {
            return self::$voter;
        }

        $voter = $_COOKIE[My::COOKIE_VOTER] ?? '';

        return is_string($voter) && preg_match('/^id:[a-f0-9]{32}$/', $voter) ? $voter : '';
    }

    /**
     * Get items voted by current visitor from cookie.
     *
     * @return  array<int, string>
     */
    private static function cookieVotes(): array
    {
        $votes = $_COOKIE[My::COOKIE_VOTES] ?? '';

        return is_string($votes) && $votes !== '' ? explode(',', $votes) : [];
    }

    /**
     * Set a cookie.
     */
    private static function setCookie(string $name, string $value): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie($name, $value, [
            'expires'  => time() + 365 * 24 * 3600,
            'path'     => '/',
            'secure'   => str_starts_with(App::blog()->url(), 'https:'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Check if current visitor has voted for an item.
     */
    public static function voted(string $type, string $id): bool
    {
        if (My::ident() !== My::IDENT_IP && in_array($type . '-' . $id, self::cookieVotes(), true)) {
            return true;
        }

        $voters = self::voters();
        if ($voters === []) {
            return false;
        }

        $sql = new SelectStatement();
        $rs  = $sql
            ->column($sql->count('*'))
            ->from(self::table())
            ->where('blog_id = ' . $sql->quote(App::blog()->id()))
            ->and('rateit_type = ' . $sql->quote($type))
            ->and('rateit_id = ' . $sql->quote($id))
            ->and('rateit_ip' . $sql->in($voters))
            ->select();

        return $rs !== null && $rs->intField(0) > 0;
    }

    /**
     * Add a vote of current visitor.
     *
     * @return  bool    False if visitor has already voted
     */
    public static function vote(string $type, string $id, int $note): bool
    {
        if (self::voted($type, $id)) {
            return false;
        }

        $quotient = My::quotient();
        $note     = max(1, min($quotient, $note));

        if (My::ident() === My::IDENT_COOKIE) {
            $voter = self::cookieVoter();
            if ($voter === '') {
                $voter = self::$voter = 'id:' . bin2hex(random_bytes(16));
                self::setCookie(My::COOKIE_VOTER, $voter);
            }
        } else {
            $voter = self::ipHash();
        }

        $cur = self::openCursor();
        $cur->setField('blog_id', App::blog()->id());
        $cur->setField('rateit_type', $type);
        $cur->setField('rateit_id', $id);
        $cur->setField('rateit_ip', $voter);
        $cur->setField('rateit_note', $note);
        $cur->setField('rateit_quotient', $quotient);
        $cur->setField('rateit_time', date('Y-m-d H:i:s'));

        # --BEHAVIOR-- rateItBeforeSet -- Cursor
        App::behavior()->callBehavior('rateItBeforeSet', $cur);

        try {
            $cur->insert();
        } catch (Exception) {
            // Primary key: already voted
            return false;
        }

        if (My::ident() !== My::IDENT_IP) {
            $votes   = self::cookieVotes();
            $votes[] = $type . '-' . $id;
            self::setCookie(My::COOKIE_VOTES, implode(',', array_unique($votes)));
        }

        App::blog()->triggerBlog();

        # --BEHAVIOR-- rateItAfterSet -- Cursor
        App::behavior()->callBehavior('rateItAfterSet', $cur);

        return true;
    }

    /**
     * Convert a ratio (from 0 to 1) to a note.
     */
    public static function note(float $ratio): float
    {
        return round($ratio * My::quotient(), My::digit());
    }

    /**
     * Get statistics of an item.
     *
     * @return  RateItStats
     */
    public static function stats(string $type, string $id): array
    {
        $sql = new SelectStatement();
        $rs  = $sql
            ->columns(['rateit_note', 'rateit_quotient'])
            ->from(self::table())
            ->where('blog_id = ' . $sql->quote(App::blog()->id()))
            ->and('rateit_type = ' . $sql->quote($type))
            ->and('rateit_id = ' . $sql->quote($id))
            ->select();

        $sum = 0.0;
        $max = 0.0;
        $min = 1.0;
        $maxcount = $mincount = $total = 0;

        while ($rs !== null && $rs->fetch()) {
            $quotient = self::num($rs, 'rateit_quotient');
            $ratio    = $quotient > 0 ? self::num($rs, 'rateit_note') / $quotient : 0.0;
            $sum += $ratio;
            $max = max($max, $ratio);
            $min = min($min, $ratio);
            if ($ratio * 2 >= 1) {
                $maxcount++;
            } else {
                $mincount++;
            }
            $total++;
        }

        return [
            'type'     => $type,
            'id'       => $id,
            'note'     => $total > 0 ? self::note($sum / $total) : 0.0,
            'max'      => self::note($max),
            'min'      => $total > 0 ? self::note($min) : 0.0,
            'maxcount' => $maxcount,
            'mincount' => $mincount,
            'total'    => $total,
            'quotient' => My::quotient(),
            'digit'    => My::digit(),
        ];
    }

    /**
     * Get rated items.
     *
     * Parameters:
     * - rateit_type: string|array<int,string> types
     * - rateit_id: string item ID
     * - order: rateit_avg, rateit_total, rateit_time, rateit_positive (+ asc/desc)
     * - positive: bool only positive votes
     * - limit: int|array{0: int, 1: int}
     *
     * Returns record with fields:
     * rateit_type, rateit_id, rateit_avg (ratio), rateit_max, rateit_min,
     * rateit_total, rateit_positive, rateit_time
     *
     * @param   array<string, mixed>    $params     The parameters
     */
    public static function getRates(array $params = [], bool $count_only = false): MetaRecord
    {
        $ratio = 'rateit_note / rateit_quotient';

        $sql = new SelectStatement();
        $sql->from(self::table())
            ->where('blog_id = ' . $sql->quote(App::blog()->id()));

        if (!empty($params['rateit_type'])) {
            $types = is_array($params['rateit_type']) ? array_values(array_filter($params['rateit_type'], is_string(...))) : (is_string($params['rateit_type']) ? [$params['rateit_type']] : []);
            $sql->and('rateit_type' . $sql->in($types));
        }
        if (isset($params['rateit_id']) && is_string($params['rateit_id']) && $params['rateit_id'] !== '') {
            $sql->and('rateit_id = ' . $sql->quote($params['rateit_id']));
        }
        if (!empty($params['positive'])) {
            $sql->and('(' . $ratio . ') * 2 >= 1');
        }

        if ($count_only) {
            // Count distinct items
            $sub = (clone $sql)->columns(['rateit_type', 'rateit_id'])->group(['rateit_type', 'rateit_id'])->statement();

            return new MetaRecord(App::db()->con()->select('SELECT COUNT(*) FROM (' . $sub . ') AS rateit_items'));
        }

        $sql->columns([
            'rateit_type',
            'rateit_id',
            'SUM(' . $ratio . ') / COUNT(*) AS rateit_avg',
            'MAX(' . $ratio . ') AS rateit_max',
            'MIN(' . $ratio . ') AS rateit_min',
            'COUNT(*) AS rateit_total',
            'SUM(CASE WHEN (' . $ratio . ') * 2 >= 1 THEN 1 ELSE 0 END) AS rateit_positive',
            'MAX(rateit_time) AS rateit_time',
        ])
            ->group(['rateit_type', 'rateit_id']);

        $orders = ['rateit_avg', 'rateit_total', 'rateit_time', 'rateit_positive'];
        $order  = isset($params['order']) && is_string($params['order']) ? explode(' ', strtolower(trim($params['order']))) : [];
        $field  = in_array($order[0] ?? '', $orders, true) ? $order[0] : 'rateit_time';
        $dir    = ($order[1] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $sql->order($field . ' ' . $dir . ($field !== 'rateit_total' ? ', rateit_total DESC' : '') . ($field !== 'rateit_time' ? ', rateit_time DESC' : ''));

        if (isset($params['limit'])) {
            if (is_numeric($params['limit'])) {
                $sql->limit((int) $params['limit']);
            } elseif (is_array($params['limit']) && isset($params['limit'][0], $params['limit'][1]) && is_numeric($params['limit'][0]) && is_numeric($params['limit'][1])) {
                $sql->limit([(int) $params['limit'][0], (int) $params['limit'][1]]);
            }
        }

        return $sql->select() ?? MetaRecord::newFromArray([]);
    }

    /**
     * Get votes of an item.
     */
    public static function getVotes(string $type, string $id): MetaRecord
    {
        $sql = new SelectStatement();

        return $sql
            ->columns(['rateit_ip', 'rateit_note', 'rateit_quotient', 'rateit_time'])
            ->from(self::table())
            ->where('blog_id = ' . $sql->quote(App::blog()->id()))
            ->and('rateit_type = ' . $sql->quote($type))
            ->and('rateit_id = ' . $sql->quote($id))
            ->order('rateit_time DESC')
            ->select() ?? MetaRecord::newFromArray([]);
    }

    /**
     * Count all votes of current blog.
     */
    public static function countVotes(): int
    {
        $sql = new SelectStatement();
        $rs  = $sql
            ->column($sql->count('*'))
            ->from(self::table())
            ->where('blog_id = ' . $sql->quote(App::blog()->id()))
            ->select();

        return $rs === null ? 0 : $rs->intField(0);
    }

    /**
     * Delete votes.
     *
     * @param   array<int, string>  $voters     Limit to these voters
     */
    public static function delete(string $type, ?string $id = null, array $voters = []): void
    {
        $sql = new DeleteStatement();
        $sql->from(self::table())
            ->where('blog_id = ' . $sql->quote(App::blog()->id()))
            ->and('rateit_type = ' . $sql->quote($type));

        if ($id !== null) {
            $sql->and('rateit_id = ' . $sql->quote($id));
        }
        if ($voters !== []) {
            $sql->and('rateit_ip' . $sql->in($voters));
        }

        $sql->delete();
        App::blog()->triggerBlog();
    }
}
