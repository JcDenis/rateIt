<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt;

use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;
use Dotclear\Interface\Core\BlogWorkspaceInterface;
use Exception;

/**
 * @brief       rateIt install class.
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Install
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::INSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        try {
            // Same structure as old versions
            $s = App::db()->structure();
            $s->table(My::TABLE_NAME)
                ->field('blog_id', 'varchar', 32, false)
                ->field('rateit_id', 'varchar', 192, false)
                ->field('rateit_type', 'varchar', 16, false)
                ->field('rateit_note', 'float', 0, false)
                ->field('rateit_quotient', 'float', 0, false)
                ->field('rateit_ip', 'varchar', 48, false)
                ->field('rateit_time', 'timestamp', 0, false, 'now()')

                ->primary('pk_rateit', 'blog_id', 'rateit_type', 'rateit_id', 'rateit_ip')
                ->index('idx_rateit_blog_id', 'btree', 'blog_id')
                ->index('idx_rateit_rateit_type', 'btree', 'rateit_type')
                ->index('idx_rateit_rateit_id', 'btree', 'rateit_id')
                ->index('idx_rateit_rateit_ip', 'btree', 'rateit_ip');

            try {
                App::db()->structure()->synchronize($s);
            } catch (Exception $e) {
                // SQLite can not change existing fields,
                // this is not an issue if all fields exist.
                $fields = App::db()->con()->schema()->getColumns(App::db()->con()->prefix() . My::TABLE_NAME);
                foreach (['blog_id', 'rateit_id', 'rateit_type', 'rateit_note', 'rateit_quotient', 'rateit_ip', 'rateit_time'] as $field) {
                    if (!isset($fields[$field])) {
                        throw $e;
                    }
                }
            }

            // Upgrade settings from old versions
            self::growUp();

            // Settings
            $s = My::settings();
            $s->put('active', false, BlogWorkspaceInterface::NS_BOOL, 'Enable rating', false, true);
            $s->put('style', 'classic', BlogWorkspaceInterface::NS_STRING, 'Rating style', false, true);
            $s->put('quotient', 5, BlogWorkspaceInterface::NS_INT, 'Maximum note', false, true);
            $s->put('digit', 1, BlogWorkspaceInterface::NS_INT, 'Notes digits number', false, true);
            $s->put('ident', My::IDENT_IP, BlogWorkspaceInterface::NS_INT, 'Voters identification', false, true);
            $s->put('msg_like', '', BlogWorkspaceInterface::NS_STRING, 'Like button text', false, true);
            $s->put('msg_notlike', '', BlogWorkspaceInterface::NS_STRING, 'Do not like button text', false, true);
            $s->put('msg_thanks', '', BlogWorkspaceInterface::NS_STRING, 'Thanks message', false, true);
            $s->put('no_css', false, BlogWorkspaceInterface::NS_BOOL, 'Disable public CSS', false, true);
            $s->put('no_js', false, BlogWorkspaceInterface::NS_BOOL, 'Disable public JS', false, true);
            $s->put('modules', ['post'], BlogWorkspaceInterface::NS_ARRAY, 'Enabled modules', false, true);
            $s->put('opt_post_single', true, BlogWorkspaceInterface::NS_BOOL, 'Rating on entry page', false, true);

            return true;
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        return true;
    }

    /**
     * Upgrade settings from version 2.x (namespace rateit).
     */
    private static function growUp(): void
    {
        $old = App::version()->getVersion(My::id());
        if ($old === '' || version_compare($old, '2026', '>=')) {
            return;
        }

        $values = [
            'rateit_active'     => ['active', BlogWorkspaceInterface::NS_BOOL],
            'rateit_rating_style' => ['style', BlogWorkspaceInterface::NS_STRING],
            'rateit_quotient'   => ['quotient', BlogWorkspaceInterface::NS_INT],
            'rateit_digit'      => ['digit', BlogWorkspaceInterface::NS_INT],
            'rateit_userident'  => ['ident', BlogWorkspaceInterface::NS_INT],
            'rateit_dispubjs'   => ['no_js', BlogWorkspaceInterface::NS_BOOL],
            'rateit_dispubcss'  => ['no_css', BlogWorkspaceInterface::NS_BOOL],
            'rateit_poststpl'   => ['opt_post_single', BlogWorkspaceInterface::NS_BOOL],
            'rateit_homepoststpl' => ['opt_post_home', BlogWorkspaceInterface::NS_BOOL],
            'rateit_tagpoststpl' => ['opt_post_tag', BlogWorkspaceInterface::NS_BOOL],
            'rateit_categorypoststpl' => ['opt_post_category', BlogWorkspaceInterface::NS_BOOL],
            'rateit_commentstpl' => ['opt_comment_auto', BlogWorkspaceInterface::NS_BOOL],
            'rateit_cinecturlink2_widget' => ['opt_cinecturlink2_widget', BlogWorkspaceInterface::NS_BOOL],
            'rateit_eventsinglestpl' => ['opt_eventhandler_single', BlogWorkspaceInterface::NS_BOOL],
            'rateit_eventslisttpl' => ['opt_eventhandler_list', BlogWorkspaceInterface::NS_BOOL],
        ];
        // Old messages are kept only if they are not old english default
        $messages = [
            'rateit_msglike'    => ['msg_like', 'I like'],
            'rateit_msgnotlike' => ['msg_notlike', "I don't like"],
            'rateit_msgthanks'  => ['msg_thanks', 'Thank you for having voted'],
        ];
        $modules = [
            'rateit_post_active'          => 'post',
            'rateit_comment_active'       => 'comment',
            'rateit_category_active'      => 'category',
            'rateit_tag_active'           => 'tag',
            'rateit_cinecturlink2_active' => 'cinecturlink2',
            'rateit_eventhandler_active'  => 'eventhandler',
        ];

        $rs = App::blogs()->getBlogs();
        while ($rs->fetch()) {
            $blog_id = $rs->strField('blog_id');
            $from    = App::blogSettings()->createFromBlog($blog_id)->get('rateit');
            $to      = App::blogSettings()->createFromBlog($blog_id)->get(My::id());

            foreach ($values as $old_id => [$new_id, $type]) {
                if ($from->settingExists($old_id)) {
                    $value = $from->get($old_id);
                    $to->put($new_id, match ($type) {
                        BlogWorkspaceInterface::NS_BOOL => (bool) $value,
                        BlogWorkspaceInterface::NS_INT  => is_numeric($value) ? (int) $value : 0,
                        default                         => is_string($value) ? $value : '',
                    }, $type);
                }
            }

            foreach ($messages as $old_id => [$new_id, $default]) {
                if ($from->settingExists($old_id)) {
                    $value = $from->get($old_id);
                    $to->put($new_id, is_string($value) && $value !== $default ? $value : '', BlogWorkspaceInterface::NS_STRING);
                }
            }

            $active = [];
            foreach ($modules as $old_id => $module) {
                if ($from->get($old_id)) {
                    $active[] = $module;
                }
            }
            if ($from->settingExists('rateit_post_active')) {
                $to->put('modules', $active, BlogWorkspaceInterface::NS_ARRAY);
            }

            foreach ($from->dumpSettings() as $id => $setting) {
                if (str_starts_with((string) $id, 'rateit_')) {
                    $from->drop((string) $id);
                }
            }
        }
    }
}
