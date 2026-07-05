<?php

namespace jainilnagar\navkit\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
use jainilnagar\navkit\elements\Node;
use jainilnagar\navkit\Navkit;

/**
 * The "Link" control shown in the node editor.
 *
 * Renders a type selector plus the relevant target input (element select, URL
 * field, or nothing for passive), and the optional target/class/rel attributes.
 * All inputs post native Node attributes, which Node declares "safe" so that
 * elements/save assigns them.
 *
 * Registered as a mandatory native field via FieldLayout::EVENT_DEFINE_NATIVE_FIELDS
 * (see Navkit::init), so it is always present in a node's editor.
 */
class LinkField extends BaseNativeField
{
    public function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('navkit', 'Link');
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Node) {
            return null;
        }

        $linkTypes = Navkit::getInstance()->linkTypes->getAllLinkTypes();

        $typeOptions = [];
        foreach ($linkTypes as $linkType) {
            $typeOptions[] = ['label' => $linkType::displayName(), 'value' => $linkType::id()];
        }

        // Type selector
        $typeHtml = Cp::selectFieldHtml([
            'label' => Craft::t('navkit', 'Link type'),
            'id' => 'navkit-type',
            'name' => 'type',
            'options' => $typeOptions,
            'value' => $element->type,
            'disabled' => $static,
        ]);

        // One target input per type, only the selected one visible
        $targetsHtml = '';
        $targets = '';
        foreach ($linkTypes as $linkType) {
            $inner = $linkType->getInputHtml($element, $static);
            if ($inner === '') {
                continue;
            }
            $targets .= Html::tag('div', $inner, [
                'class' => 'navkit-link-target',
                'data-navkit-type' => $linkType::id(),
                'style' => $element->type === $linkType::id() ? null : 'display:none;',
            ]);
        }
        if ($targets !== '') {
            $targetsHtml = Html::tag('div', $targets, ['class' => 'navkit-link-targets']);
        }

        $row = Html::tag('div',
            Html::tag('div', $typeHtml,  ['class' => 'flex-grow', 'style' => 'flex:1 1 0;']) .
            Html::tag('div', $targetsHtml, ['class' => 'flex-grow', 'style' => 'flex:1 1 0;']),
            ['class' => 'flex', 'style' => 'gap:24px; align-items:flex-start; margin-bottom:24px;']
        );

        $html = $row;

        $classesHtml = Cp::textFieldHtml([
            'label' => Craft::t('navkit', 'CSS classes'),
            'instructions' => Craft::t('navkit', 'e.g. myclass'),
            'name' => 'classes',
            'value' => $element->classes,
            'disabled' => $static,
        ]);

        $relHtml = Cp::textFieldHtml([
            'label' => Craft::t('navkit', 'Rel'),
            'instructions' => Craft::t('navkit', 'e.g. nofollow, noopener'),
            'name' => 'rel',
            'value' => $element->rel,
            'disabled' => $static,
        ]);

        $row = Html::tag('div',
            Html::tag('div', $classesHtml,  ['class' => 'flex-grow', 'style' => 'flex:1 1 0;']) .
            Html::tag('div', $relHtml, ['class' => 'flex-grow', 'style' => 'flex:1 1 0;']),
            ['class' => 'flex', 'style' => 'gap:24px; align-items:flex-start;']
        );

        $html .= $row;

        // Attributes
        $html .= Cp::lightswitchFieldHtml([
            'label' => Craft::t('navkit', 'Target'),
            'name' => 'target',
            'on' => $element->target === '_blank',
            'onLabel' => Craft::t('navkit', 'Opens in new tab'),
            'value' => '_blank',
            'disabled' => $static,
        ]);

        // Toggle target inputs when the type changes
        if (!$static) {
            $view = Craft::$app->getView();
            $selectId = $view->namespaceInputId('navkit-type');
            $view->registerJs(<<<JS
(function () {
    var sel = document.getElementById('$selectId');
    if (!sel) return;
    var scope = sel.closest('.navkit-link-targets') ? sel.parentNode : (sel.closest('form') || document);
    function update() {
        scope.querySelectorAll('.navkit-link-target').forEach(function (el) {
            el.style.display = (el.getAttribute('data-navkit-type') === sel.value) ? '' : 'none';
        });
    }
    sel.addEventListener('change', update);
    update();
})();
JS);
        }

        return $html;
    }
}
