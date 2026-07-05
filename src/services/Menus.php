<?php

namespace jainilnagar\navkit\services;

use Craft;
use craft\db\Query;
use craft\enums\PropagationMethod;
use craft\errors\BusyResourceException;
use craft\errors\StaleResourceException;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\Structure;
use jainilnagar\navkit\elements\Node;
use jainilnagar\navkit\models\Menu;
use jainilnagar\navkit\Navkit;
use jainilnagar\navkit\records\MenuRecord;
use Throwable;
use yii\base\Component;

/**
 * Menus service.
 *
 * Owns the lifecycle of Menu definitions: validating and writing them to project
 * config, reacting to project-config changes by mirroring into the database, and
 * maintaining each menu's Structure.
 */
class Menus extends Component
{
    /** @var Menu[]|null */
    private ?array $_menus = null;

    // --- Reads ---------------------------------------------------------------

    /**
     * @return Menu[]
     */
    public function getAllMenus(): array
    {
        if ($this->_menus !== null) {
            return $this->_menus;
        }

        $this->_menus = [];

        /** @var MenuRecord[] $rows */
        $rows = MenuRecord::find()
            ->orderBy(['name' => SORT_ASC])
            ->all();

        foreach ($rows as $row) {
            $this->_menus[] = $this->_createMenuFromRecord($row);
        }

        return $this->_menus;
    }

    public function getMenuById(int $id): ?Menu
    {
        foreach ($this->getAllMenus() as $menu) {
            if ($menu->id === $id) {
                return $menu;
            }
        }
        return null;
    }

    public function getMenuByHandle(string $handle): ?Menu
    {
        foreach ($this->getAllMenus() as $menu) {
            if ($menu->handle === $handle) {
                return $menu;
            }
        }
        return null;
    }

    public function getMenuByUid(string $uid): ?Menu
    {
        foreach ($this->getAllMenus() as $menu) {
            if ($menu->uid === $uid) {
                return $menu;
            }
        }
        return null;
    }

    // --- Writes (project config is the source of truth) ----------------------

    /**
     * Validates and saves a menu by writing it to project config. The matching
     * database changes happen in {@see handleChangedMenu()}.
     */
    public function saveMenu(Menu $menu, bool $runValidation = true): bool
    {
        $isNew = !$menu->id;

        if ($runValidation && !$menu->validate()) {
            Craft::info('Menu not saved due to validation error.', __METHOD__);
            return false;
        }

        if ($isNew) {
            $menu->uid = StringHelper::UUID();
        } elseif (!$menu->uid) {
            $menu->uid = Db::uidById(MenuRecord::tableName(), $menu->id);
        }

        $configPath = Navkit::CONFIG_MENUS_KEY . '.' . $menu->uid;
        Craft::$app->getProjectConfig()->set(
            $configPath,
            $menu->getConfig(),
            "Save the “{$menu->handle}” Navkit menu"
        );

        if ($isNew) {
            $menu->id = Db::idByUid(MenuRecord::tableName(), $menu->uid);
        }

        $this->_menus = null;

        return true;
    }

    public function deleteMenuById(int $id): bool
    {
        $menu = $this->getMenuById($id);
        if ($menu === null) {
            return false;
        }
        return $this->deleteMenu($menu);
    }

    public function deleteMenu(Menu $menu): bool
    {
        Craft::$app->getProjectConfig()->remove(
            Navkit::CONFIG_MENUS_KEY . '.' . $menu->uid,
            "Delete the “{$menu->handle}” Navkit menu"
        );

        $this->_menus = null;

        return true;
    }

    // --- Project config handlers --------------------------------------------

    /**
     * @throws Throwable
     * @throws BusyResourceException
     * @throws StaleResourceException
     */
    public function handleChangedMenu(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $data = $event->newValue;

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $record = MenuRecord::findOne(['uid' => $uid]) ?? new MenuRecord();

            // Ensure a Structure exists and reflects the configured maxLevels.
            $maxLevels = $data['maxLevels'] ?? null;
            $structure = null;

            if ($record->structureId) {
                $structure = Craft::$app->getStructures()->getStructureById($record->structureId);
            }
            if ($structure === null) {
                $structure = new Structure();
            }
            $structure->maxLevels = $maxLevels;
            Craft::$app->getStructures()->saveStructure($structure);

            $record->uid = $uid;
            $record->name = $data['name'];
            $record->handle = $data['handle'];
            $record->structureId = $structure->id;
            $record->settings = [
                'maxLevels' => $maxLevels,
                'propagationMethod' => $data['propagationMethod'] ?? PropagationMethod::All->value,
                'siteSettings' => $data['siteSettings'] ?? [],
            ];

            // Field layout
            if (!empty($data['fieldLayouts'])) {
                $layout = FieldLayout::createFromConfig(reset($data['fieldLayouts']));
                $layout->id = $record->fieldLayoutId;
                $layout->type = Node::class;
                $layout->uid = key($data['fieldLayouts']);
                Craft::$app->getFields()->saveLayout($layout);
                $record->fieldLayoutId = $layout->id;
            } elseif ($record->fieldLayoutId) {
                Craft::$app->getFields()->deleteLayoutById($record->fieldLayoutId);
                $record->fieldLayoutId = null;
            }

            $record->save(false);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $this->_menus = null;
    }

    /**
     * @throws Throwable
     */
    public function handleDeletedMenu(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $record = MenuRecord::findOne(['uid' => $uid]);

        if ($record === null) {
            return;
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Soft-delete every node in this menu, then drop the menu + structure.
            $nodeIds = (new Query())
                ->select(['id'])
                ->from(['{{%navkit_nodes}}'])
                ->where(['menuId' => $record->id])
                ->column();

            foreach (\jainilnagar\navkit\elements\Node::find()->id($nodeIds)->all() as $node) {
                Craft::$app->getElements()->deleteElement($node);
            }

            if ($record->structureId) {
                Craft::$app->getStructures()->deleteStructureById($record->structureId);
            }

            if ($record->fieldLayoutId) {
                Craft::$app->getFields()->deleteLayoutById($record->fieldLayoutId);
            }

            $record->delete();

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $this->_menus = null;
    }

    /**
     * Rebuilds the menus portion of project config from the database.
     */
    public function getRebuiltConfig(): array
    {
        $config = [];
        foreach ($this->getAllMenus() as $menu) {
            $config[$menu->uid] = $menu->getConfig();
        }
        return $config;
    }

    // --- Internals -----------------------------------------------------------

    private function _createMenuFromRecord(MenuRecord $record): Menu
    {
        $settings = $record->settings ?? [];

        $menu = new Menu();
        $menu->id = (int)$record->id;
        $menu->uid = $record->uid;
        $menu->name = $record->name;
        $menu->handle = $record->handle;
        $menu->structureId = $record->structureId !== null ? (int)$record->structureId : null;
        $menu->fieldLayoutId = $record->fieldLayoutId !== null ? (int)$record->fieldLayoutId : null;
        $menu->maxLevels = $settings['maxLevels'] ?? null;
        $menu->propagationMethod = PropagationMethod::tryFrom($settings['propagationMethod'] ?? '') ?? PropagationMethod::All;
        $menu->siteSettings = $settings['siteSettings'] ?? [];

        return $menu;
    }
}
