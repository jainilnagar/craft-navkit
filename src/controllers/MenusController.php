<?php

namespace jainilnagar\navkit\controllers;

use Craft;
use craft\enums\PropagationMethod;
use craft\web\Controller;
use jainilnagar\navkit\elements\Node;
use jainilnagar\navkit\models\Menu;
use jainilnagar\navkit\Navkit;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Manages Navkit menu definitions.
 */
class MenusController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $this->requirePermission('navkit:manageMenus');
        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('navkit/menus/_index', [
            'menus' => Navkit::getInstance()->menus->getAllMenus(),
        ]);
    }

    public function actionEdit(?int $menuId = null, ?Menu $menu = null): Response
    {
        if ($menu === null) {
            if ($menuId !== null) {
                $menu = Navkit::getInstance()->menus->getMenuById($menuId);
                if ($menu === null) {
                    throw new NotFoundHttpException('Menu not found');
                }
            } else {
                $menu = new Menu();
            }
        }

        $title = $menu->id
            ? trim($menu->name) ?: Craft::t('navkit', 'Edit Menu')
            : Craft::t('navkit', 'Create a new menu');

        return $this->renderTemplate('navkit/menus/_edit', [
            'menu' => $menu,
            'title' => $title,
            'allSites' => Craft::$app->getSites()->getAllSites(),
            'propagationMethodOptions' => $this->_propagationMethodOptions(),
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();

        $menuId = $request->getBodyParam('menuId');
        $menu = $menuId
            ? Navkit::getInstance()->menus->getMenuById((int)$menuId)
            : new Menu();

        if ($menu === null) {
            throw new NotFoundHttpException('Menu not found');
        }

        $menu->name = $request->getBodyParam('name', $menu->name);
        $menu->handle = $request->getBodyParam('handle', $menu->handle);
        $menu->maxLevels = (int)$request->getBodyParam('maxLevels') ?: null;
        $menu->propagationMethod = PropagationMethod::tryFrom(
            (string)$request->getBodyParam('propagationMethod')
        ) ?? PropagationMethod::All;

        // Build site settings from the posted checkboxes.
        $postedSites = $request->getBodyParam('sites') ?? [];
        $siteSettings = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            if (in_array($site->uid, $postedSites, true) || in_array((string)$site->id, $postedSites, true)) {
                $siteSettings[$site->uid] = ['enabledByDefault' => true];
            }
        }
        // Fall back to the primary site if nothing was selected.
        if (empty($siteSettings)) {
            $primary = Craft::$app->getSites()->getPrimarySite();
            $siteSettings[$primary->uid] = ['enabledByDefault' => true];
        }
        $menu->siteSettings = $siteSettings;

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Node::class;
        $menu->setFieldLayout($fieldLayout);

        if (!Navkit::getInstance()->menus->saveMenu($menu)) {
            Craft::$app->getSession()->setError(Craft::t('navkit', 'Couldn’t save menu.'));
            Craft::$app->getUrlManager()->setRouteParams(['menu' => $menu]);
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('navkit', 'Menu saved.'));
        return $this->redirectToPostedUrl($menu);
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $menuId = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');
        Navkit::getInstance()->menus->deleteMenuById($menuId);

        return $this->asSuccess(Craft::t('navkit', 'Menu deleted.'));
    }

    private function _propagationMethodOptions(): array
    {
        return [
            ['value' => PropagationMethod::None->value, 'label' => Craft::t('app', 'Only save entries to the site they were created in')],
            ['value' => PropagationMethod::SiteGroup->value, 'label' => Craft::t('app', 'Save entries to other sites in the same site group')],
            ['value' => PropagationMethod::Language->value, 'label' => Craft::t('app', 'Save entries to other sites with the same language')],
            ['value' => PropagationMethod::All->value, 'label' => Craft::t('app', 'Save entries to all sites enabled for this menu')],
        ];
    }
}
