<?php

namespace jainilnagar\navkit\controllers;

use Craft;
use craft\base\Element;
use craft\helpers\Cp;
use craft\web\Controller;
use jainilnagar\navkit\elements\Node;
use jainilnagar\navkit\Navkit;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Serves the node element index, with an empty state when no menus exist yet.
 *
 * Craft's element index can't initialize with zero sources, and there are no
 * sources until at least one menu exists — so we branch here rather than letting
 * the index spin forever.
 */
class NodesController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $this->requirePermission('navkit:manageNodes');
        return true;
    }

    public function actionIndex(): Response
    {
        $hasMenus = !empty(Navkit::getInstance()->menus->getAllMenus());

        return $this->renderTemplate(
            $hasMenus ? 'navkit/nodes/_index' : 'navkit/nodes/_empty'
        );
    }

    public function actionCreate(): Response
    {
        $menuId = (int)$this->request->getRequiredParam('menuId');
        $menu = Navkit::getInstance()->menus->getMenuById($menuId);
        if (!$menu) {
            throw new BadRequestHttpException('Invalid menu.');
        }

        $site = Cp::requestedSite() ?? Craft::$app->getSites()->getPrimarySite();

        $node = new Node();
        $node->menuId = $menu->id;
        $node->siteId = $site->id;
        $node->title = Craft::t('navkit', 'New node');
        $node->setScenario(Element::SCENARIO_ESSENTIALS);

        if (!Craft::$app->getDrafts()->saveElementAsDraft($node, Craft::$app->getUser()->getId(), markAsSaved: false)) {
            throw new ServerErrorHttpException('Unable to create node.');
//            throw new ServerErrorHttpException('Unable to create node: ' . implode('; ', $node->getErrorSummary(true)));
        }

        return $this->redirect($node->getCpEditUrl());
    }
}
