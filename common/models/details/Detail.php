<?php

namespace common\models\details;

use Yii;
use DateTime;
use Exception;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use common\models\Users;
use common\models\UploadFiles;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use common\models\details\query\DetailQuery;
use common\components\validators\MacValidator;
use common\components\validators\FiasValidator;
use common\components\validators\DetailTypeValidator;

/**
 * This is the abstract model class for "details" table.
 *
 * @property int $row_id
 * @property int $parent_id
 * @property int $detail_type
 * @property string $detail_value
 * @property string $detail_value_cache
 * @property int $create_date
 * @property int $created_by_user
 * @property int $update_date
 * @property int $updated_by_user
 * @property int $detail_status
 * @property string $ownerAttribute
 *
 * @property DetailType $detailType
 * @property Users $createdByUser
 * @property Users $updatedByUser
 */
abstract class Detail extends ActiveRecord
{
    const STATUS_ACTIVE = 1;
    const STATUS_DELETED = 2;
    const STATUS_TO_DELETE = 3;

    const SCENARIO_STRING = 'string';
    const SCENARIO_REAL = 'real';
    const SCENARIO_BOOL = 'bool';
    const SCENARIO_DATE = 'date';
    const SCENARIO_DATE_TIME = 'datetime';
    const SCENARIO_LIST = 'list';
    const SCENARIO_FIAS = 'fias';
    const SCENARIO_PHONE = 'phone';
    const SCENARIO_E_MAIL = 'email';
    const SCENARIO_MAC = 'mac';
    const SCENARIO_SN = 'sn';

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'create_date',
                'updatedAtAttribute' => 'update_date'
            ],
            [
                'class' => BlameableBehavior::class,
                'createdByAttribute' => 'created_by_user',
                'updatedByAttribute' => 'updated_by_user'
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['detail_user_id', 'detail_type'], 'required'],
            ['detail_type', DetailTypeValidator::class],
            ['detail_status', 'default', 'value' => self::STATUS_ACTIVE],
            ['detail_status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_DELETED, self::STATUS_TO_DELETE]],
            [['parent_id', 'detail_type', 'create_date', 'created_by_user', 'update_date', 'updated_by_user', 'detail_status'], 'integer'],
            ['detail_value', 'double', 'on' => self::SCENARIO_REAL],
            ['detail_value', 'boolean', 'on' => self::SCENARIO_BOOL],
            ['detail_value', 'email', 'on' => self::SCENARIO_E_MAIL],
            ['detail_value', 'number', 'on' => self::SCENARIO_LIST],
            ['detail_value', 'trim'],
            ['detail_value',
                'filter',
                'on' => [self::SCENARIO_MAC, self::SCENARIO_SN],
                'filter' => static function ($value) {
                    return strtoupper($value);
                }],
            ['detail_value', FiasValidator::class, 'on' => self::SCENARIO_FIAS],
            ['detail_value', MacValidator::class, 'on' => self::SCENARIO_MAC],
            ['detail_value', 'number', 'on' => self::SCENARIO_PHONE, 'when' => static function ($model) {
                return $model->detail_value !== '8wipline_808';
            }],
            ['detail_value', 'date',
                'on' => self::SCENARIO_DATE,
                'format' => 'php:U',
                'timestampAttribute' => 'detail_value'
            ],
            ['detail_value', 'date',
                'on' => self::SCENARIO_DATE_TIME,
                'format' => Yii::$app->formatter->datetimeFormat,
                'timestampAttribute' => 'detail_value'
            ],
            ['detail_value', 'string', 'on' => self::SCENARIO_STRING, 'max' => 255],
            [['detail_value_cache'], 'string', 'max' => 255]
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        $scenarios = parent::scenarios();

        return array_merge($scenarios, [
            self::SCENARIO_REAL => ['detail_type', 'detail_value', 'detail_status', 'detail_value_cache'],
            self::SCENARIO_BOOL => ['detail_type', 'detail_value', 'detail_status', 'detail_value_cache'],
            self::SCENARIO_DATE => ['detail_type', 'detail_value', 'detail_status', 'detail_value_cache'],
            self::SCENARIO_DATE_TIME => ['detail_type', 'detail_value', 'detail_status', 'detail_value_cache'],
            self::SCENARIO_LIST => ['detail_type', 'detail_value', 'detail_status', 'detail_value_cache'],
            self::SCENARIO_FIAS => ['detail_type', 'detail_value', 'detail_status', 'detail_value_cache'],
            self::SCENARIO_PHONE => ['detail_type', 'detail_value', 'detail_status', 'detail_value_cache'],
            self::SCENARIO_E_MAIL => ['detail_type', 'detail_value', 'detail_status', 'detail_value_cache'],
            self::SCENARIO_STRING => ['detail_type', 'detail_value', 'detail_status', 'detail_value_cache'],
            self::SCENARIO_MAC => ['detail_type', 'detail_value', 'detail_status', 'detail_value_cache'],
            self::SCENARIO_SN => ['detail_type', 'detail_value', 'detail_status', 'detail_value_cache']
        ]);
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function beforeValidate()
    {
        if ($this->detail_type) {
            $this->scenario = $this->getScenarioByType();
        }

        if ($this->scenario === self::SCENARIO_DATE) {

            $date = DateTime::createFromFormat('U', $this->detail_value);

            $errors = DateTime::getLastErrors();

            if (!$errors['error_count']) {
                $this->detail_value = $date->getTimestamp();
            } else {
                $this->detail_value = (new DateTime($this->detail_value))->getTimestamp();
            }

        }

        return true;
    }

    /**
     * Init scenario by type
     *
     * @param null $type
     *
     * @return bool
     */
    public function getScenarioByType($type = null)
    {
        if ($type) {
            $this->detail_type = $type;
        }

        if (!$this->detail_type) {
            return self::SCENARIO_DEFAULT;
        }

        if ((int)$this->detail_type === DetailType::TYPE_SN) {
            return self::SCENARIO_SN;
        }

        switch ((int)$this->detailType->type) {
            case DetailType::TYPE_VIEW_DATE:
                return self::SCENARIO_DATE;

            case DetailType::TYPE_VIEW_DATE_TIME:
                return self::SCENARIO_DATE_TIME;

            case DetailType::TYPE_VIEW_TARIFF:
            case DetailType::TYPE_VIEW_LIST:
                return self::SCENARIO_LIST;

            case DetailType::TYPE_VIEW_E_MAIL:
                return self::SCENARIO_E_MAIL;

            case DetailType::TYPE_VIEW_FIAS:
                return self::SCENARIO_FIAS;

            case DetailType::TYPE_VIEW_PHONE:
                return self::SCENARIO_PHONE;

            case DetailType::TYPE_VIEW_MAC:
                return self::SCENARIO_MAC;

            case DetailType::TYPE_VIEW_REAL:
                return self::SCENARIO_REAL;

            case DetailType::TYPE_VIEW_BOOL:
                return self::SCENARIO_BOOL;

            case DetailType::TYPE_VIEW_LINK:
            case DetailType::TYPE_VIEW_IMAGE:
            case DetailType::TYPE_VIEW_FILE:
            case DetailType::TYPE_VIEW_FILE_ONLINE:
            case DetailType::TYPE_VIEW_TEXT:
            default:
                return self::SCENARIO_STRING;
        }
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'row_id' => 'Row ID',
            'parent_id' => 'Parent ID',
            'detail_type' => 'Detail Type',
            'detail_value' => 'Атрибут',
            'detail_value_cache' => 'Detail Value Cache',
            'create_date' => 'Create Date',
            'created_by_user' => 'Created By User',
            'update_date' => 'Update Date',
            'updated_by_user' => 'Updated By User',
            'detail_status' => 'Detail Status'
        ];
    }

    /**
     * @return ActiveQuery
     */
    public function getDetailType()
    {
        return $this->hasOne(DetailType::class, ['detail_type_id' => 'detail_type']);
    }

    /**
     * @return ActiveQuery
     */
    public function getCreatedByUser()
    {
        return $this->hasOne(Users::class, ['user_id' => 'created_by_user']);
    }

    /**
     * @return ActiveQuery
     */
    public function getUpdatedByUser()
    {
        return $this->hasOne(Users::class, ['user_id' => 'updated_by_user']);
    }

    /**
     * @return ActiveQuery
     */
    public function getDetailFile()
    {
        return $this->hasOne(UploadFiles::class, ['id' => 'detail_value']);
    }

    /**
     * Возвращает ID собственника этого свойства
     *
     * @return string
     */
    abstract public function getOwnerAttribute();

    /**
     * add or update attribute
     *
     * @param $itemID
     * @param $type
     * @param $value
     * @param string $value_cache
     * @param int $id
     * @param string $parent
     * @return array|Detail
     */
    public static function setDetail($itemID, $type, $value, $value_cache = '', $id = 0, $parent = null)
    {
        if ($value === null) {
            return ['detail_value' => 'Value is empty'];
        }

        $modelDef = new static([
            'detail_type' => $type
        ]);

        $typeDetail = $modelDef->detailType;

        $condition = [
            'detail_type' => $type,
            $modelDef->ownerAttribute => $itemID
        ];

        if ($typeDetail && $typeDetail->multy) {
            $condition['detail_value'] = $value;
        }

        $model = $id ? static::findOne($id) : static::findOne($condition);

        $model = $model ?: $modelDef;

        if (!$model->isNewRecord && $itemID != $model->{$model->ownerAttribute}) {
            $model->addError($model->ownerAttribute, 'Owner attribute mismatch');
            return $model->getErrors();
        }

        $model->{$model->ownerAttribute} = $itemID;
        $model->detail_type = $type;
        $model->detail_value = $value;
        $model->detail_value_cache = $value_cache;
        $model->parent_id = $parent;
        $model->detail_status = self::STATUS_ACTIVE;
        if ($model->save()) {
            return $model;
        }

        return $model->getErrors();
    }

    /**
     * @param $detailId
     * @return array
     */
    public static function getDetailsToRemove($detailId)
    {
        $detailsToRemove = [$detailId];

        $details = static::find()
            ->select(['row_id'])
            ->where([
                'parent_id' => $detailId,
                'detail_status' => self::STATUS_ACTIVE
            ])
            ->asArray()
            ->all();

        foreach ($details as $detail) {
            $detailsToRemove[] = $detail['row_id'];
            array_merge($detailsToRemove, static::getDetailsToRemove($detail['row_id']));
        }

        return array_unique($detailsToRemove);
    }

    /**
     * @param $detailId
     * @return int
     */
    public static function deleteDetail($detailId)
    {
        $children = self::getDetailsToRemove($detailId);
        return static::updateAll(['detail_status' => self::STATUS_DELETED],
            ['row_id' => $children]);
    }

    /**
     * @inheritdoc
     * @return DetailQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new DetailQuery(static::class);
    }

    /**
     * Возвращает последнее значение атрибута по его типу
     *
     * @param $itemId
     * @param $detailType
     * @return false|null|string
     */
    public static function getDetailValueByType($itemId, $detailType)
    {
        return static::find()
            ->select('detail_value')
            ->where([
                (new static())->getOwnerAttribute() => $itemId,
                'detail_type' => $detailType,
                'detail_status' => self::STATUS_ACTIVE
            ])
            ->orderBy(['row_id' => SORT_DESC])
            ->scalar();
    }

    /**
     * Возвращает последнее значение атрибута по его типу
     *
     * @param $itemId
     * @param $detailType
     * @return false|null|string
     */
    public static function getDetailValueCacheByType($itemId, $detailType)
    {
        return static::find()
            ->select('detail_value_cache')
            ->where([
                (new static())->getOwnerAttribute() => $itemId,
                'detail_type' => $detailType,
                'detail_status' => self::STATUS_ACTIVE
            ])
            ->orderBy(['row_id' => SORT_DESC])
            ->scalar();
    }

    /**
     * Возвращает все значения атрибута по его типу
     *
     * @param $itemId
     * @param $detailType
     * @return array
     */
    public static function getDetailValueListByType($itemId, $detailType)
    {
        return static::find()
            ->select('detail_value')
            ->where([
                (new static())->getOwnerAttribute() => $itemId,
                'detail_type' => $detailType,
                'detail_status' => self::STATUS_ACTIVE
            ])
            ->column();
    }

    /**
     * @param int $typeId
     * @param int $clientId
     * @return false|self|null
     */
    public static function getClientDetailByType($clientId, $typeId)
    {
        return self::find()
            ->where([
                (new static())->getOwnerAttribute() => $clientId,
                'detail_type' => $typeId,
                'detail_status' => self::STATUS_ACTIVE,
            ])
            ->one();
    }

    /**
     * @param int $typeId
     * @param int $clientId
     * @return false|ActiveRecord[]|null
     */
    public static function getClientDetailListByType($clientId, $typeId)
    {
        return self::find()
            ->where([
                'detail_client_id' => $clientId,
                'detail_type' => $typeId,
                'detail_status' => self::STATUS_ACTIVE,
            ])->all();
    }

    /**
     * @param int $typeId
     * @param int $clientId
     * @param string $value
     * @return false|ActiveRecord|null
     */
    public static function getClientDetailByTypeValue($clientId, $typeId, $value)
    {
        return self::find()
            ->where(['and',
                ['detail_client_id' => $clientId],
                ['detail_type' => $typeId],
                ['detail_status' => self::STATUS_ACTIVE],
                ['or', ['detail_value' => $value], ['detail_value_cache' => $value]]
            ])
            ->one();
    }
}
