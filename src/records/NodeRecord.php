<?php

namespace jainilnagar\navkit\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use yii\db\ActiveQueryInterface;

/**
 * @property int $id
 * @property int $menuId
 * @property string $type
 * @property string|null $url
 * @property int|null $linkedElementId
 * @property int|null $linkedSiteId
 * @property string|null $target
 * @property string|null $classes
 * @property string|null $rel
 * @property array|null $data
 */
class NodeRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%navkit_nodes}}';
    }

    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    public function getMenu(): ActiveQueryInterface
    {
        return $this->hasOne(MenuRecord::class, ['id' => 'menuId']);
    }
}
