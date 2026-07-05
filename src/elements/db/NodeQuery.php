<?php

namespace jainilnagar\navkit\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use jainilnagar\navkit\models\Menu;
use jainilnagar\navkit\Navkit;

/**
 * Node element query.
 *
 * Joins the navkit_nodes table and, when scoped to a menu, drives the query off
 * that menu's Structure so the element index renders as a nested, drag-orderable
 * tree.
 */
class NodeQuery extends ElementQuery
{
    public mixed $menuId = null;
    public mixed $type = null;

    /**
     * @var bool Whether to query the menu's structure (default true so the index
     *           and template queries return ordered, nested results).
     */
    public ?bool $withStructure = true;

    public function menu(Menu|string|null $value): self
    {
        if ($value instanceof Menu) {
            $this->menuId = $value->id;
            $this->structureId = $value->structureId;
        } elseif (is_string($value)) {
            $menu = Navkit::getInstance()->menus->getMenuByHandle($value);
            $this->menuId = $menu?->id;
            $this->structureId = $menu?->structureId;
        } else {
            $this->menuId = null;
        }

        return $this;
    }

    public function menuId(mixed $value): self
    {
        $this->menuId = $value;
        return $this;
    }

    public function type(mixed $value): self
    {
        $this->type = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        // If we're scoped to a single menu but don't yet know its structure,
        // resolve it so the structure joins are applied.
        if ($this->structureId === null && is_numeric($this->menuId)) {
            $menu = Navkit::getInstance()->menus->getMenuById((int)$this->menuId);
            $this->structureId = $menu?->structureId;
        }

        $this->joinElementTable('navkit_nodes');

        $this->query->select([
            'navkit_nodes.menuId',
            'navkit_nodes.type',
            'navkit_nodes.url',
            'navkit_nodes.linkedElementId',
            'navkit_nodes.linkedSiteId',
            'navkit_nodes.target',
            'navkit_nodes.classes',
            'navkit_nodes.rel',
            'navkit_nodes.data',
        ]);

        if ($this->menuId !== null) {
            $this->subQuery->andWhere(Db::parseParam('navkit_nodes.menuId', $this->menuId));
        }

        if ($this->type !== null) {
            $this->subQuery->andWhere(Db::parseParam('navkit_nodes.type', $this->type));
        }

        return parent::beforePrepare();
    }
}
