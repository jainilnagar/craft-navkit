<?php

namespace jainilnagar\navkit\records;

use craft\db\ActiveRecord;
use craft\records\FieldLayout;
use craft\records\Structure;
use yii\db\ActiveQueryInterface;

/**
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property int|null $structureId
 * @property int|null $fieldLayoutId
 * @property array|null $settings
 * @property string $uid
 */
class MenuRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%navkit_menus}}';
    }

    public function getStructure(): ActiveQueryInterface
    {
        return $this->hasOne(Structure::class, ['id' => 'structureId']);
    }

    public function getFieldLayout(): ActiveQueryInterface
    {
        return $this->hasOne(FieldLayout::class, ['id' => 'fieldLayoutId']);
    }
}
