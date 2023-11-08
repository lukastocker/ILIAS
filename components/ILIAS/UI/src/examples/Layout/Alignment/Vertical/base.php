<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Layout\Alignment\Vertical;

function base()
{
    global $DIC;
    $ui_factory = $DIC['ui.factory'];
    $renderer = $DIC['ui.renderer'];
    $tpl = $DIC['tpl'];
    $tpl->addCss('components/ILIAS/UI/src/examples/Layout/Alignment/alignment_examples.css');

    $blocks = [
        $ui_factory->legacy('<div class="example_block fullheight blue">Example Block</div>'),
        $ui_factory->legacy('<div class="example_block fullheight green">Another Example Block</div>'),
        $ui_factory->legacy('<div class="example_block fullheight yellow">And a third block is also part of this group</div>')
    ];

    $vertical = $ui_factory->layout()->alignment()->vertical(...$blocks);
    return $renderer->render($vertical);
}
