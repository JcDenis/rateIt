<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt;

use Dotclear\App;
use Dotclear\Helper\Network\Http;

/**
 * @brief       rateIt frontend URL handler.
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class FrontendUrl
{
    /**
     * Vote on an item.
     *
     * With javascript (fetch with json=1) it returns JSON,
     * else it redirects to the page of the form.
     */
    public static function vote(?string $args): void
    {
        $json = !empty($_POST['json']);

        if (!My::isActive() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            App::url()::p404();
        }

        $str  = fn (string $key): string => isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
        $type = $str('type');
        $id   = $str('id');
        $note = is_numeric($str('note')) ? (int) $str('note') : 0;

        $module = My::activeModules()[$type] ?? null;
        $error  = '';
        $voted  = false;

        if ($module === null || $id === '' || $module->item($id) === null) {
            $error = __('This item can not be rated.');
        } elseif ($note < 1 || $note > My::quotient()) {
            $error = __('Wrong note.');
        } elseif (Rates::voted($type, $id)) {
            $error = __('You have already voted.');
        } else {
            $voted = Rates::vote($type, $id, $note);
            if (!$voted) {
                $error = __('You have already voted.');
            }
        }

        if ($json) {
            $stats = $module === null ? null : Rates::stats($type, $id);
            $values = [];
            if ($stats !== null) {
                foreach (['note', 'max', 'min', 'fullnote', 'quotient', 'total', 'maxcount', 'mincount'] as $field) {
                    $values[$field] = Render::format($stats, $field);
                }
            }

            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store');
            echo json_encode([
                'ok'      => $voted,
                'message' => $voted ? My::message('thanks') : $error,
                'item'    => $type . '-' . $id,
                'total'   => $stats['total'] ?? 0,
                'values'  => $values,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            dotclear_exit();
        }

        // Redirect only to a blog URL
        $redir = $str('redir');
        if (!str_starts_with($redir, App::blog()->url()) || str_contains($redir, "\n") || str_contains($redir, "\r")) {
            $redir = App::blog()->url();
        }

        Http::redirect(strtok($redir, '#') . '#rateit-' . rawurlencode($type . '-' . $id));
    }
}
