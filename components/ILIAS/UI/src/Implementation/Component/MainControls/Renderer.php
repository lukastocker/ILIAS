<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\UI\Implementation\Component\MainControls;

use ILIAS\UI\Component;
use ILIAS\UI\Component\MainControls\Footer;
use ILIAS\UI\Component\MainControls\MainBar;
use ILIAS\UI\Component\MainControls\MetaBar;
use ILIAS\UI\Component\MainControls\Slate\Slate;
use ILIAS\UI\Component\Signal;
use ILIAS\UI\Implementation\Component\Button\Bulky as IBulky;
use ILIAS\UI\Implementation\Component\MainControls\Slate\Slate as ISlate;
use ILIAS\UI\Implementation\Render\AbstractComponentRenderer;
use ILIAS\UI\Implementation\Render\Template as UITemplateWrapper;
use ILIAS\UI\Renderer as RendererInterface;
use ILIAS\Data\URI;
use ILIAS\UI\Implementation\Render\ResourceRegistry;
use LogicException;

class Renderer extends AbstractComponentRenderer
{
    public const BLOCK_MAINBAR_ENTRIES = 'trigger_item';
    public const BLOCK_MAINBAR_TOOLS = 'tool_trigger_item';
    public const BLOCK_METABAR_ENTRIES = 'meta_element';

    private array $trigger_signals = [];

    /**
     * @inheritdoc
     */
    public function render(Component\Component $component, RendererInterface $default_renderer): string
    {
        $this->checkComponent($component);

        if ($component instanceof MainBar) {
            return $this->renderMainbar($component, $default_renderer);
        }
        if ($component instanceof MetaBar) {
            return $this->renderMetabar($component, $default_renderer);
        }
        if ($component instanceof Footer) {
            return $this->renderFooter($component, $default_renderer);
        }
        if ($component instanceof ModeInfo) {
            return $this->renderModeInfo($component, $default_renderer);
        }
        if ($component instanceof Component\MainControls\SystemInfo) {
            return $this->renderSystemInfo($component, $default_renderer);
        }
        throw new LogicException("Cannot render: " . get_class($component));
    }

    protected function calculateMainBarTreePosition($pos, $slate)
    {
        if (!$slate instanceof Slate && !$slate instanceof MainBar) {
            return $slate;
        }
        return $slate
            ->withMainBarTreePosition($pos)
            ->withMappedSubNodes(
                function ($num, $slate, $is_tool = false) use ($pos) {
                    if ($is_tool) {
                        $pos = 'T';
                    }
                    return $this->calculateMainBarTreePosition("$pos:$num", $slate);
                }
            );
    }

    protected function renderToolEntry(
        string $entry_id,
        string $mb_id,
        MainBar $component,
        UITemplateWrapper $tpl,
        RendererInterface $default_renderer
    ): string {
        $hidden = $component->getInitiallyHiddenToolIds();
        $close_buttons = $component->getCloseButtons();

        $is_removeable = array_key_exists($entry_id, $close_buttons);
        $is_hidden = in_array($entry_id, $hidden);

        if ($is_removeable) {
            $trigger_signal = $component->getTriggerSignal($mb_id, $component::ENTRY_ACTION_REMOVE);
            $this->trigger_signals[] = $trigger_signal;
            $btn_removetool = $close_buttons[$entry_id]
               ->withAdditionalOnloadCode(
                   fn($id) => "il.UI.maincontrols.mainbar.addPartIdAndEntry('$mb_id', 'remover', '$id', true);"
               )
                ->withOnClick($trigger_signal);

            $tpl->setCurrentBlock("tool_removal");
            $tpl->setVariable("REMOVE_TOOL", $default_renderer->render($btn_removetool));
            $tpl->parseCurrentBlock();
        }

        $is_removeable = $is_removeable ? 'true' : 'false';
        $is_hidden = $is_hidden ? 'true' : 'false';
        return "il.UI.maincontrols.mainbar.addToolEntry('$mb_id', $is_removeable, $is_hidden, '$entry_id');";
    }

    protected function renderMainbarEntry(
        array $entries,
        string $block,
        MainBar $component,
        UITemplateWrapper $tpl,
        RendererInterface $default_renderer
    ): void {
        $f = $this->getUIFactory();
        foreach ($entries as $k => $entry) {
            $button = $entry;
            $slate = null;
            $js = '';

            if ($entry instanceof Slate) {
                $slate = $entry;
                $mb_id = $entry->getMainBarTreePosition();
                $is_tool = $block === static::BLOCK_MAINBAR_TOOLS;
                if ($is_tool) {
                    $js = $this->renderToolEntry($k, $mb_id, $component, $tpl, $default_renderer);
                }

                $trigger_signal = $component->getTriggerSignal($mb_id, $component::ENTRY_ACTION_TRIGGER);
                $this->trigger_signals[] = $trigger_signal;
                $button = $f->button()->bulky($entry->getSymbol(), $entry->getName(), '#')
                    ->withOnClick($trigger_signal);
            } else {
                //add Links/Buttons as toplevel entries
                $pos = array_search($k, array_keys($entries));
                $mb_id = '0:' . $pos;
                $is_tool = false;
            }

            $button = $button->withAdditionalOnLoadCode(
                function ($id) use ($js, $mb_id, $k, $is_tool): string {
                    $add_as_tool = $is_tool ? 'true' : 'false';
                    $js .= "
                        il.UI.maincontrols.mainbar.addPartIdAndEntry('$mb_id', 'triggerer', '$id', $add_as_tool);
                        il.UI.maincontrols.mainbar.addMapping('$k','$mb_id');
                    ";
                    return $js;
                }
            )->withAriaRole(IBulky::MENUITEM);

            $tpl->setCurrentBlock($block);
            $tpl->setVariable("BUTTON", $default_renderer->render($button));
            $tpl->parseCurrentBlock();

            if ($slate) {
                $entry = $entry->withAriaRole(ISlate::MENU);

                $tpl->setCurrentBlock("slate_item");
                $tpl->setVariable("SLATE", $default_renderer->render($entry));
                $tpl->parseCurrentBlock();
            }
        }
    }

    protected function renderMainbar(MainBar $component, RendererInterface $default_renderer): string
    {
        $f = $this->getUIFactory();
        $tpl = $this->getTemplate("tpl.mainbar.html", true, true);

        $tpl->setVariable("ARIA_LABEL", $this->txt('mainbar_aria_label'));
        $more_btn_label = $this->txt('mainbar_more_label');
        /**
         * @var $more_slate Slate
         */
        $more_slate = $f->mainControls()->slate()->combined(
            $more_btn_label,
            $f->symbol()->glyph()->more()
        );
        $more_slate = $more_slate->withAriaRole(ISlate::MENU);
        $component = $component->withAdditionalEntry(
            '_mb_more_entry',
            $more_slate
        );
        $component = $this->calculateMainBarTreePosition("0", $component);

        $mb_entries = [
            static::BLOCK_MAINBAR_ENTRIES => $component->getEntries(),
            static::BLOCK_MAINBAR_TOOLS => $component->getToolEntries()
        ];

        foreach ($mb_entries as $block => $entries) {
            $this->renderMainbarEntry(
                $entries,
                $block,
                $component,
                $tpl,
                $default_renderer
            );
        }

        //tools-section trigger
        if (count($component->getToolEntries()) > 0) {
            $btn_tools = $component->getToolsButton()
                ->withOnClick($component->getToggleToolsSignal());

            $tpl->setCurrentBlock("tools_trigger");
            $tpl->setVariable("BUTTON", $default_renderer->render($btn_tools));
            $tpl->parseCurrentBlock();
        }

        //disengage all, close slates
        $btn_disengage = $f->button()->bulky($f->symbol()->glyph()->collapseHorizontal("#"), $this->txt('close'), "#")
            ->withOnClick($component->getDisengageAllSignal());
        $tpl->setVariable("CLOSE_SLATES", $default_renderer->render($btn_disengage));


        $id = $this->bindMainbarJS($component);
        $tpl->setVariable('ID', $id);

        return $tpl->get();
    }

    protected function renderMetabar(MetaBar $component, RendererInterface $default_renderer): string
    {
        $f = $this->getUIFactory();
        $tpl = $this->getTemplate("tpl.metabar.html", true, true);
        $active = '';
        $signals = [
            'entry' => $component->getEntryClickSignal(),
            'close_slates' => $component->getDisengageAllSignal()
        ];
        $entries = $component->getEntries();

        $more_label = $this->txt('show_more');
        $more_symbol = $f->symbol()->glyph()->disclosure()
            ->withCounter($f->counter()->novelty(0))
            ->withCounter($f->counter()->status(0));
        /**
         * @var $more_slate Slate
         */
        $more_slate = $f->mainControls()->slate()->combined($more_label, $more_symbol);
        $more_slate = $more_slate->withAriaRole(ISlate::MENU);
        $entries[] = $more_slate;

        $this->renderTriggerButtonsAndSlates(
            $tpl,
            $default_renderer,
            $signals['entry'],
            static::BLOCK_METABAR_ENTRIES,
            $entries,
            $active
        );

        $component = $component->withOnLoadCode(
            function ($id) use ($signals) {
                $entry_signal = $signals['entry'];
                $close_slates_signal = $signals['close_slates'];
                return "
					il.UI.maincontrols.metabar.init('$id');
                    il.UI.maincontrols.metabar.get('$id').registerSignals(
						'$entry_signal',
						'$close_slates_signal',
					);
                    il.UI.maincontrols.metabar.get('$id').init();
                    window.addEventListener(
                        'resize',
                        ()=>{il.UI.maincontrols.metabar.get('$id').init()}
                    );
				";
            }
        );
        $tpl->setVariable('ARIA_LABEL', $this->txt('metabar_aria_label'));

        $id = $this->bindJavaScript($component);
        $tpl->setVariable('ID', $id);
        return $tpl->get();
    }

    protected function renderModeInfo(ModeInfo $component, RendererInterface $default_renderer): string
    {
        $tpl = $this->getTemplate("tpl.mode_info.html", true, true);
        $tpl->setVariable('MODE_TITLE', $component->getModeTitle());
        $base_URI = $component->getCloseAction()->getBaseURI();
        $query = $component->getCloseAction()->getQuery();
        $action = $base_URI . '?' . $query;
        $close = $this->getUIFactory()->symbol()->glyph()->close($action);
        $tpl->setVariable('CLOSE_GLYPH', $default_renderer->render($close));

        return $tpl->get();
    }

    protected function renderSystemInfo(
        Component\MainControls\SystemInfo $component,
        RendererInterface $default_renderer
    ): string {
        $tpl = $this->getTemplate("tpl.system_info.html", true, true);
        $tpl->setVariable('HEADLINE', $component->getHeadLine());
        $tpl->setVariable('BODY', $component->getInformationText());
        $tpl->setVariable('DENOTATION', $component->getDenotation());
        switch ($component->getDenotation()) {
            case Component\MainControls\SystemInfo::DENOTATION_NEUTRAL:
            case Component\MainControls\SystemInfo::DENOTATION_IMPORTANT:
                $tpl->setVariable('LIVE', 'aria-live="polite"');
                break;
            case Component\MainControls\SystemInfo::DENOTATION_BREAKING:
                $tpl->setVariable('ROLE', 'role="alert"');
                break;
        }
        if ($component->isDismissable()) {
            $close = $this->getUIFactory()->symbol()->glyph()->close("#");
            $signal = $component->getCloseSignal();
            $close = $close->withOnClick($signal);
            $tpl->setVariable('CLOSE_BUTTON', $default_renderer->render($close));
            $tpl->setVariable('CLOSE_URI', (string) $component->getDismissAction());
            $component = $component->withAdditionalOnLoadCode(fn($id) => "$(document).on('$signal', function() { il.UI.maincontrols.system_info.close('$id'); });");
        }

        $more = $this->getUIFactory()->symbol()->glyph()->more("#");
        $tpl->setVariable('MORE_BUTTON', $default_renderer->render($more));

        $component = $component->withAdditionalOnLoadCode(fn($id) => "il.UI.maincontrols.system_info.init('$id')");

        $id = $this->bindJavaScript($component);
        $tpl->setVariable('ID', $id);
        $tpl->setVariable('ID_HEADLINE', $id . "_headline");
        $tpl->setVariable('ID_DESCRIPTION', $id . "_description");

        return $tpl->get();
    }


    protected function renderTriggerButtonsAndSlates(
        UITemplateWrapper $tpl,
        RendererInterface $default_renderer,
        Signal $entry_signal,
        string $block,
        array $entries,
        string $active = null
    ): void {
        foreach ($entries as $id => $entry) {
            $use_block = $block;
            $engaged = (string) $id === $active;
            $slate = null;
            if ($entry instanceof Slate) {
                $f = $this->getUIFactory();
                $secondary_signal = $entry->getToggleSignal();
                $clickable = $f->button()->bulky($entry->getSymbol(), $entry->getName(), '#')
                    ->withEngagedState($engaged)
                    ->withOnClick($entry_signal)
                    ->appendOnClick($secondary_signal)
                    ->withAriaRole(IBulky::MENUITEM);

                $slate = $entry;
            } elseif ($entry instanceof IBulky) {
                $clickable = $entry;
                $clickable = $clickable->withAriaRole(IBulky::MENUITEM);
                $slate = null;
            } else {
                $clickable = $entry;
            }

            $clickable_html = $default_renderer->render($clickable);

            if ($slate) {
                $tpl->setCurrentBlock("slate_item");
                $tpl->setVariable("SLATE", $default_renderer->render($slate));
                $tpl->parseCurrentBlock();
            }

            $tpl->setCurrentBlock($use_block);
            $tpl->setVariable("BUTTON", $clickable_html);
            $tpl->parseCurrentBlock();
        }
    }

    protected function bindMainbarJS(MainBar $component): ?string
    {
        $trigger_signals = $this->trigger_signals;

        $inititally_active = $component->getActive();

        $component = $component->withOnLoadCode(
            function ($id) use ($component, $trigger_signals, $inititally_active): string {
                $disengage_all_signal = $component->getDisengageAllSignal();
                $tools_toggle_signal = $component->getToggleToolsSignal();

                $js = "il.UI.maincontrols.mainbar.addTriggerSignal('$disengage_all_signal');";
                $js .= "il.UI.maincontrols.mainbar.addTriggerSignal('$tools_toggle_signal');";

                foreach ($trigger_signals as $signal) {
                    $js .= "il.UI.maincontrols.mainbar.addTriggerSignal('$signal');";
                }

                foreach ($component->getToolEntries() as $k => $tool) {
                    $signal = $component->getEngageToolSignal($k);
                    $js .= "il.UI.maincontrols.mainbar.addTriggerSignal('$signal');";
                }

                $js .= "
                    window.addEventListener('resize', il.UI.maincontrols.mainbar.adjustToScreenSize);
                    il.UI.maincontrols.mainbar.init('$inititally_active');
                ";
                return $js;
            }
        );

        return $this->bindJavaScript($component);
    }

    protected function renderFooter(Footer $component, RendererInterface $default_renderer): string
    {
        $tpl = $this->getTemplate("tpl.footer.html", true, true);
        $links = $component->getLinks();
        $modalsWithTriggers = $component->getModals();
        $links = array_merge($links, array_column($modalsWithTriggers, 1));

        if ($links) {
            $link_list = $this->getUIFactory()->listing()->unordered($links);
            $tpl->setVariable('LINKS', $default_renderer->render($link_list));
        }

        if ($modalsWithTriggers !== []) {
            $tpl->setVariable('MODALS', $default_renderer->render(
                array_column($modalsWithTriggers, 0)
            ));
        }

        $tpl->setVariable('TEXT', $component->getText());

        $perm_url = $component->getPermanentURL();
        if ($perm_url instanceof URI) {
            $url = $perm_url->__toString();
            $link = $this->getUIFactory()
                         ->link()
                         ->standard($this->txt('perma_link'), $url);
            $tpl->setVariable('PERMANENT', $default_renderer->render($link));
        }
        return $tpl->get();
    }

    /**
     * @inheritdoc
     */
    public function registerResources(ResourceRegistry $registry): void
    {
        parent::registerResources($registry);
        $registry->register('./public/mainbar.js');
        $registry->register('./components/ILIAS/UI/src/templates/js/MainControls/dist/maincontrols.min.js');
        $registry->register('./components/ILIAS/GlobalScreen/src/Client/dist/GS.js');
        $registry->register('./components/ILIAS/UI/src/templates/js/MainControls/system_info.js');
    }

    /**
     * @inheritdoc
     */
    protected function getComponentInterfaceName(): array
    {
        return array(
            MetaBar::class,
            MainBar::class,
            Footer::class,
            ModeInfo::class,
            Component\MainControls\SystemInfo::class
        );
    }
}
