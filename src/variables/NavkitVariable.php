<?php

namespace jainilnagar\navkit\variables;

use jainilnagar\navkit\elements\db\NodeQuery;
use jainilnagar\navkit\elements\Node;
use jainilnagar\navkit\models\Menu;
use jainilnagar\navkit\Navkit;
use Twig\Markup;

/**
 * Exposes Navkit to templates as `craft.navkit`.
 *
 * Surfaces menus, a node query factory, and render() for front-end output.
 */
class NavkitVariable
{
    /**
     * @return Menu[]
     */
    public function menus(): array
    {
        return Navkit::getInstance()->menus->getAllMenus();
    }

    public function menu(string $handle): ?Menu
    {
        return Navkit::getInstance()->menus->getMenuByHandle($handle);
    }

    /**
     * Node query factory, e.g. {% set items = craft.navkit.nodes('main').all() %}
     */
    public function nodes(Menu|string|null $menu = null, array $criteria = []): NodeQuery
    {
        $query = Node::find();

        if ($menu !== null) {
            $query->menu($menu);
        }

        \Craft::configure($query, $criteria);

        return $query;
    }

    public function render(Menu|string|null $menu = null, array $options = []): Markup
    {
        return Navkit::getInstance()->render->render($menu, $options);
    }
}
