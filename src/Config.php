<?php

declare(strict_types=1);

namespace Dotclear\Plugin\rateIt;

use Dotclear\App;
use Dotclear\Core\Backend\ModulesList;
use Dotclear\Core\Backend\Notices;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Number;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Process\TraitProcess;
use Dotclear\Interface\Core\BlogWorkspaceInterface;
use Exception;

/**
 * @brief       rateIt configuration class.
 * @ingroup     rateIt
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Config
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::CONFIG));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        if (empty($_POST['save'])) {
            return true;
        }

        $str  = fn (string $name): string => isset($_POST[My::id() . $name]) && is_string($_POST[My::id() . $name]) ? trim($_POST[My::id() . $name]) : '';
        $bool = fn (string $name): bool => !empty($_POST[My::id() . $name]);

        try {
            $s = My::settings();
            $s->put('active', $bool('active'), BlogWorkspaceInterface::NS_BOOL);
            $s->put('style', in_array($str('style'), My::STYLES, true) ? $str('style') : 'classic', BlogWorkspaceInterface::NS_STRING);
            $s->put('quotient', max(2, min(20, (int) $str('quotient'))), BlogWorkspaceInterface::NS_INT);
            $s->put('digit', max(0, min(4, (int) $str('digit'))), BlogWorkspaceInterface::NS_INT);
            $s->put('ident', in_array($str('ident'), My::identCombo(), true) ? (int) $str('ident') : My::IDENT_IP, BlogWorkspaceInterface::NS_INT);
            $s->put('msg_like', $str('msg_like'), BlogWorkspaceInterface::NS_STRING);
            $s->put('msg_notlike', $str('msg_notlike'), BlogWorkspaceInterface::NS_STRING);
            $s->put('msg_thanks', $str('msg_thanks'), BlogWorkspaceInterface::NS_STRING);
            $s->put('no_css', $bool('no_css'), BlogWorkspaceInterface::NS_BOOL);
            $s->put('no_js', $bool('no_js'), BlogWorkspaceInterface::NS_BOOL);

            $modules = [];
            foreach (My::modules() as $id => $module) {
                if ($bool('module_' . $id)) {
                    $modules[] = $id;
                }
                foreach (array_keys($module->options()) as $option) {
                    $s->put('opt_' . $id . '_' . $option, $bool('opt_' . $id . '_' . $option), BlogWorkspaceInterface::NS_BOOL);
                }
            }
            $s->put('modules', $modules, BlogWorkspaceInterface::NS_ARRAY);

            App::blog()->triggerBlog();

            Notices::addSuccessNotice(__('Configuration has been successfully updated.'));
            App::backend()->url()->redirect('admin.plugins', [
                'module' => My::id(),
                'conf'   => '1',
                'redir'  => !(App::backend()->__get('list') instanceof ModulesList) ? '' : App::backend()->__get('list')->getRedir(),
            ]);
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

        $s = My::settings();

        $check = fn (string $name, bool $value, string $label): Para => (new Para())->items([
            (new Checkbox(My::id() . $name, $value))
                ->value(1)
                ->label(new Label($label, Label::IL_FT)),
        ]);
        $input = fn (string $name, string $label, string $placeholder): Para => (new Para())->items([
            (new Input(My::id() . $name))
                ->size(40)
                ->maxlength(255)
                ->value(Html::escapeHTML($s->getStr($name, false)))
                ->placeholder(Html::escapeHTML($placeholder))
                ->label(new Label($label, Label::OL_TF)),
        ]);

        $modules = [];
        foreach (My::modules() as $id => $module) {
            $options = [];
            foreach ($module->options() as $option => $label) {
                $options[] = $check('opt_' . $id . '_' . $option, $module->option($option), $label);
            }
            $items = [$check('module_' . $id, $module->isActive(), sprintf(__('Enable rating of %s'), mb_strtolower($module->name())))];
            if ($options !== []) {
                $items[] = (new Div())->extra('style="margin-left:2em"')->items($options);
            }
            $modules[] = (new Div())->class('fieldset')->items($items);
        }

        echo
        (new Fieldset())
            ->legend(new Legend(__('Activation')))
            ->items([
                $check('active', My::isActive(), __('Enable rating on this blog')),
            ])
            ->render() .
        (new Fieldset())
            ->legend(new Legend(__('Rating')))
            ->items([
                (new Para())->items([
                    (new Select(My::id() . 'style'))
                        ->items(My::stylesCombo())
                        ->default(My::style())
                        ->label(new Label(__('Rating style:'), Label::OL_TF)),
                ]),
                (new Para())->items([
                    (new Number(My::id() . 'quotient', 2, 20, My::quotient()))
                        ->label(new Label(__('Note out of:'), Label::OL_TF)),
                ]),
                (new Para())->items([
                    (new Number(My::id() . 'digit', 0, 4, My::digit()))
                        ->label(new Label(__('Number of digits:'), Label::OL_TF)),
                ]),
                (new Note())
                    ->class('form-note')
                    ->text(__('Changing the maximum note does not change existing votes, notes are converted.')),
                (new Para())->items([
                    (new Select(My::id() . 'ident'))
                        ->items(My::identCombo())
                        ->default((string) My::ident())
                        ->label(new Label(__('Identify voters by:'), Label::OL_TF)),
                ]),
                (new Note())
                    ->class('form-note')
                    ->text(__('IP addresses are not stored, only a hash of them.')),
            ])
            ->render() .
        (new Fieldset())
            ->legend(new Legend(__('Messages')))
            ->items([
                $input('msg_like', __('Text of "like" button:'), __('I like')),
                $input('msg_notlike', __('Text of "don\'t like" button:'), __("I don't like")),
                $input('msg_thanks', __('Message of thanks:'), __('Thank you for having voted')),
                (new Note())
                    ->class('form-note')
                    ->text(__('Leave empty to use default translated texts.')),
            ])
            ->render() .
        (new Fieldset())
            ->legend(new Legend(__('Public side')))
            ->items([
                $check('no_css', (bool) $s->getBool('no_css', false), __('Disable public CSS')),
                $check('no_js', (bool) $s->getBool('no_js', false), __('Disable public javascript')),
                (new Note())
                    ->class('form-note')
                    ->text(__('Without javascript, the page is reloaded on vote. Template rateit.html can be copied to your theme to customize it.')),
            ])
            ->render() .
        (new Fieldset())
            ->legend(new Legend(__('Modules')))
            ->items([
                (new Text('p', __('Select types of items that can be rated:'))),
                ... $modules,
                (new Note())
                    ->class('form-note')
                    ->text(__('Rating can also be displayed with widget "Rate it: rating" or template tags.')),
            ])
            ->render();
    }
}
