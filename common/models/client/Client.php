<?php

namespace common\models\client;

use common\components\helpers\Hostname;
use common\components\traits\HierarchicalDetailsTrait;
use common\components\traits\TSaveFromAssoc;
use common\models\bill\BillClientService;
use common\models\bill\BillClientTariff;
use common\models\bill\BillService;
use common\models\bill\BillServiceGroupList;
use common\models\calls\Call;
use common\models\calls\CallList;
use common\models\calls\CallListDetail;
use common\models\client\query\ClientQuery;
use common\models\details\Detail;
use common\models\details\DetailPosition;
use common\models\details\DetailType;
use common\models\dev\DevModel;
use common\models\devices\Device;
use common\models\devices\DeviceClientList;
use common\models\HintCalls;
use common\models\mail\MailMessages;
use common\models\nas\NasModel;
use common\models\Notes;
use common\models\Organizations;
use common\models\scanner\BaseDev;
use common\models\scanner\DevClient;
use common\models\scanner\Device as NetDevice;
use common\models\scanner\items\abonent\Abonent;
use common\models\scanner\items\FaceBase;
use common\models\scanner\items\ppp\PppClient;
use common\models\scanner\items\routing\Ip;
use common\models\scanner\items\routing\Route;
use common\models\segments\SegmentedClients;
use common\models\Tariffs;
use common\models\tasks\Task;
use common\models\Users;
use Yii;
use yii\base\InvalidConfigException;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;


/**
 * This is the model class for table "clients".
 *
 * @property int $client_id
 * @property int $parent_id [int(11)]
 * @property int $client_status
 * @property int $client_type
 * @property string $client_title
 * @property int $client_create_date
 * @property int $organization_id
 * @property int $created_by_user
 * @property int $client_update_date
 * @property int $updated_by_user
 *
 * @property CallListDetail[] $callListDetails
 * @property CallList[] $callLists
 * @property Call[] $calls
 * @property ClientDetail[] $details
 * @property ClientDetail[] $clientDetails
 * @property ClientStatusList $clientStatus
 * @property ClientTypeList $clientType
 * @property Organizations $organization
 * @property Users $createdByUser
 * @property Users $updatedByUser
 * @property DeviceClientList[] $deviceClientLists
 * @property Device[] $devices
 * @property HintCalls[] $hintCalls
 * @property MailMessages[] $mailMessages
 * @property Notes[] $notes
 * @property SegmentedClients[] $segmentedClients
 * @property string personalAccount
 * @property Tariffs $tariff
 * @property Tariffs $oldTariff
 *
 * @property Abonent $abonent
 * @property DevClient[] $migrations все миграции устройств (активные и неактивные, привязанные и отвязанные)
 * @property DevClient[] $activeMigrations активные привязанные устройства
 * @property NetDevice[] $devs активные привязанные к клиенту устройства
 * @property Ip[] $ips активные IP адреса, используемые клиентом
 * @property Route $route активный маршрут по умолчанию, используемый клиентом
 * @property FaceBase[] $faces интерфейсы устройства, отданные клиенту
 * @property PppClient[] $ppps PPP интерфейсы устройства, закрепленные за клиентом
 */
class Client extends ActiveRecord
{
    use HierarchicalDetailsTrait, TSaveFromAssoc;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'clients';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['client_id'], 'integer'],
            [['client_status', 'client_type', 'client_create_date', 'organization_id', 'created_by_user', 'client_update_date', 'updated_by_user'], 'integer'],
            [['client_title'], 'string', 'max' => 256],
//            [['client_status'], 'exist', 'skipOnError' => true, 'targetClass' => ClientStatusList::class, 'targetAttribute' => ['client_status' => 'client_status_id']],
//            [['client_type'], 'exist', 'skipOnError' => true, 'targetClass' => ClientTypeList::class, 'targetAttribute' => ['client_type' => 'client_type_id']],
            [['organization_id'], 'exist', 'skipOnError' => true, 'targetClass' => Organizations::class, 'targetAttribute' => ['organization_id' => 'organization_id']],
            [['created_by_user'], 'exist', 'skipOnError' => true, 'targetClass' => Users::class, 'targetAttribute' => ['created_by_user' => 'user_id']],
            [['updated_by_user'], 'exist', 'skipOnError' => true, 'targetClass' => Users::class, 'targetAttribute' => ['updated_by_user' => 'user_id']]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'client_id' => 'Идентификатор',
            'client_status' => 'Статус клиента',
            'client_type' => 'Тип',
            'client_title' => 'Название',
            'client_create_date' => 'Дата создания',
            'organization_id' => 'Организация',
            'created_by_user' => 'Создал',
            'client_update_date' => 'Дата обновления',
            'updated_by_user' => 'Обновил'
        ];
    }

    /**
     * @return string[][]
     */
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'client_create_date',
                'updatedAtAttribute' => 'client_update_date'
            ],
            [
                'class' => BlameableBehavior::class,
                'createdByAttribute' => 'created_by_user',
                'updatedByAttribute' => 'updated_by_user'
            ]
        ];
    }

    /**
     * @return array
     */
    public static function getFieldList()
    {
        return [
            'id' => 'client_id',
            'title' => 'client_title',
            'status' => 'client_status',
            'type' => 'client_type',
            'created_at' => 'client_create_date',
            'detail_related' => 'detail_client_id'
        ];
    }

    /**
     * @param int $uniqType
     * @param string $value
     * @return int|null
     */
    public static function getIDByUniqueAttr($uniqType, $value, $clientType = null)
    {
        $query = (new Query())
            ->select('c.client_id')
            ->from('{{clients}} c')
            ->innerJoin('{{client_details}} cd', 'cd.detail_client_id = c.client_id')
            ->where([
                'and',
                ['cd.detail_type' => $uniqType],
                ['cd.detail_value' => $value],
                ['c.client_status' => ClientStatusList::STATUS_ACTIVE]
            ]);

        if ($clientType) {
            $query->andWhere(['c.client_type' => $clientType]);
        }

        $result = $query->scalar();

        if ($result === false || empty($result)) {
            return null;
        }

        return (int)$result;
    }

    /**
     * @return string
     */
    public static function getClassDetails()
    {
        return ClientDetail::class;
    }

    /**
     * @return ActiveQuery
     */
    public function getCallListDetails()
    {
        return $this->hasMany(CallListDetail::class, ['call_list_client' => 'client_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getCallLists()
    {
        return $this->hasMany(CallList::class, ['last_called_client' => 'client_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getCalls()
    {
        return $this->hasMany(Call::class, ['call_client_id' => 'client_id']);
    }

    /**
     * Не работает, переопределяется трейтом
     *
     * @return ActiveQuery
     * @deprecated
     */
    public function getDetails()
    {
        return $this->hasMany(ClientDetail::class, ['detail_client_id' => 'client_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getClientDetails()
    {
        return $this->hasMany(ClientDetail::class, ['detail_client_id' => 'client_id']);
    }

    /**
     * Получить текущий тариф абонента
     * @return Tariffs
     */
    public function getPassword()
    {
        return $this->getAttributeValueByType(DetailType::TYPE_PASSWORD);
    }

    /**
     * Получить текущий тариф абонента
     * @return Tariffs
     */
    public function getTariff()
    {
        if (($tariff_id = $this->getAttributeValueByType(DetailType::TYPE_TARIFF)) > 0) {
            return Tariffs::findOne($tariff_id);
        }
        return null;
    }

    /**
     * Получить ранее использованный тариф абонента
     * @return Tariffs
     */
    public function getOldTariff()
    {
        if (($tariff_id = $this->getAttributeValueByType(DetailType::TYPE_OLD_TARIFF)) > 0) {
            return Tariffs::findOne(['tarif_external_id' => $tariff_id]);
        }
        return null;
    }

    /**
     * @return mixed|string
     */
    public function generateHostname()
    {
        list ($soname, $name, $patr) = explode(' ', $this->client_title);
        return $this->abonent && $this->abonent->device && $this->abonent->device->hostname ?
            $this->abonent->device->hostname :
            Hostname::generate($soname, $name, $patr);
    }

    /**
     * @param int $clientIds
     * @return ClientDetail[]
     */
    public function getDetailByIds($clientIds)
    {
        /** @var Users $user */
        $user = Yii::$app->user->identity;

        $qb = ClientDetail::find()->alias('cd')
            ->where([
                'cd.detail_client_id' => $clientIds
            ]);

        if (!Yii::$app->user->can('supervisor')) {
            $qb->innerJoin(
                [
                    'cdpl' => DetailPosition::tableName()
                ],
                [
                    'cdpl.detail_type_id' => 'detail_type',
                    'cdpl.org_pos_id' => $user->user_org_pos_id
                ]);
        }
        $viewConfig = unserialize('a:10:{i:0;s:9:"client_id";i:1;s:3:"a17";i:2;s:2:"a6";i:3;s:2:"a9";i:4;s:12:"client_title";i:5;s:11:"client_type";i:6;s:13:"client_status";i:7;s:8:"segments";i:8;s:15:"organization_id";i:9;s:15:"created_by_user";}');

        $types = [];

        foreach ($viewConfig as $item) {
            if (preg_match('/(^a\d+$)/', $item, $matches)) {
                $types[] = preg_replace("/\D/", '', $item);
            }
        }

        $qb->andWhere([
            'detail_type' => $types
        ]);


        $qb->select('cd.*');

        return $qb->all();
    }

    /**
     * @param ClientDetail[] $details
     *
     * @return array
     */
    public static function restructureClientDetails($details)
    {
        $result = [];

        foreach ($details as $detail) {
            if (!isset($result[$detail->detail_client_id])) {
                $result[$detail->detail_client_id] = [];
            }

            if (!isset($result[$detail->detail_client_id][$detail->detail_type])) {
                $result[$detail->detail_client_id][$detail->detail_type] = [];
            }

            $result[$detail->detail_client_id][$detail->detail_type][] = $detail;
        }

        return $result;
    }

    /**
     * @return Query
     */
    public function getLinkedClientsQuery()
    {
        $query1 = $this->getLinkedClientsSubQuery(
            DetailType::TYPE_SUBCONTRACT,
            DetailType::TYPE_PERSONAL_ACCOUNT,
            1
        );
        $query2 = $this->getLinkedClientsSubQuery(
            DetailType::TYPE_PERSONAL_ACCOUNT,
            DetailType::TYPE_SUBCONTRACT,
            2
        );
        $query3 = $this->getLinkedClientsSubQuery(
            DetailType::TYPE_PERSONAL_ACCOUNT,
            DetailType::TYPE_PARENT,
            3
        );
        $query4 = $this->getLinkedClientsSubQuery(
            DetailType::TYPE_PARENT,
            DetailType::TYPE_PERSONAL_ACCOUNT,
            4
        );
        $query5 = $this->getLinkedSurveyClientsSubQuery(
            DetailType::TYPE_PARENT_CLIENT,
            'detail_client_id',
            'detail_value',
            5
        );
        $query6 = $this->getLinkedSurveyClientsSubQuery(
            DetailType::TYPE_PARENT_CLIENT,
            'detail_value',
            'detail_client_id',
            6
        );
        return $query1
            ->union($query2)
            ->union($query3)
            ->union($query4)
            ->union($query5)
            ->union($query6);
    }

    /**
     * @return array
     */
    public function getLinkedClients()
    {
        $query = self::find()->alias('c')
            ->select([
                'id' => 'c.client_id',
                'c.client_type',
                'type' => 'client_type_list.client_type_descr',
                'title' => 'c.client_title',
                'address' => 'address.detail_value_cache',
                'expired' => 'IF((dateEnd.detail_value <> 0 AND dateEnd.detail_value < UNIX_TIMESTAMP()), 1, 0)'
            ])
            ->innerJoinWith('clientType')
            ->addLeftActiveDetail('c.client_id', 'address', DetailType::TYPE_FIAS)
            ->addLeftActiveDetail('c.client_id', 'dateEnd', DetailType::TYPE_CONTRACT_DATE_END)
            ->where([
                'c.client_status' => ClientStatusList::STATUS_ACTIVE
            ]);

        if ($this->client_type === ClientTypeList::TYPE_LEGAL_ENTITIES) {
            $query->andWhere([
                'c.client_type' => ClientTypeList::TYPE_CONTRACT,
                'c.parent_id' => $this->client_id
            ]);
        } elseif ($this->client_type === ClientTypeList::TYPE_ORDER) {
            $query->andWhere([
                'c.client_type' => ClientTypeList::TYPE_CONTRACT,
                'c.client_id' => $this->parent_id
            ]);
        } else {
            $orderQuery = clone $query;
            $query->union($orderQuery->andWhere([
                'c.client_type' => ClientTypeList::TYPE_ORDER,
                'c.parent_id' => $this->client_id
            ]))
                ->andWhere([
                    'c.client_type' => ClientTypeList::TYPE_LEGAL_ENTITIES,
                    'c.client_id' => $this->parent_id
                ]);

        }
        return $query->asArray()->all();
    }

    /**
     * @param $fromAttribute
     * @param $toAttribute
     * @param null $postfix
     * @return ActiveQuery
     */
    private function getLinkedClientsSubQuery($fromAttribute, $toAttribute, $postfix = null)
    {
        return $this->getClientDetails()->alias('current_client' . $postfix)
            ->select([
                'id' => 'client' . $postfix . '.client_id',
                'type' => 'client' . $postfix . '.client_type',
                'title' => 'client' . $postfix . '.client_title',
            ])
            ->leftJoin(['cd' . $postfix => ClientDetail::tableName()], [
                'AND',
                ['cd' . $postfix . '.detail_type' => $toAttribute,],
                ['cd' . $postfix . '.detail_status' => Detail::STATUS_ACTIVE],
                ['cd' . $postfix . '.detail_value' => new Expression('current_client' . $postfix . '.detail_value')],
                ['!=', 'cd' . $postfix . '.detail_client_id', new Expression('current_client' . $postfix . '.detail_client_id')]
            ])
            ->innerJoin(
                ['client' . $postfix . '' => self::tableName()],
                ['client' . $postfix . '.client_id' => new Expression('cd' . $postfix . '.detail_client_id')]
            )
            ->where(['current_client' . $postfix . '.detail_type' => $fromAttribute]);
    }

    /**
     * @param $fromAttribute
     * @param $detail_field
     * @param $request_field
     * @param null $postfix
     * @return ActiveQuery
     */
    private function getLinkedSurveyClientsSubQuery($fromAttribute, $detail_field, $request_field, $postfix = null)
    {
        return $this->getClientDetails()->alias('current_client' . $postfix)
            ->select([
                'id' => 'client' . $postfix . '.client_id',
                'type' => 'client' . $postfix . '.client_type',
                'title' => 'client' . $postfix . '.client_title',
            ])
            ->leftJoin(['cd' . $postfix => ClientDetail::tableName()], [
                'AND',
                ['cd' . $postfix . '.detail_type' => $fromAttribute,],
                ['cd' . $postfix . '.detail_status' => Detail::STATUS_ACTIVE],
                ['cd' . $postfix . '.' . $detail_field => new Expression('current_client' . $postfix . '.detail_client_id')]
            ])
            ->innerJoin(
                ['client' . $postfix . '' => self::tableName()],
                ['client' . $postfix . '.client_id' => new Expression('cd' . $postfix . '.' . $request_field)]
            );
    }

    /**
     * @return ActiveQuery
     */
    public function getClientStatus()
    {
        return $this->hasOne(ClientStatusList::class, ['client_status_id' => 'client_status']);
    }

//    /**
//     * @return \yii\db\ActiveQuery
//     */
//    public function getClientStatus()
//    {
//        return $this->hasOne(ClientStatusList::class, ['client_status_id' => 'client_status']);
//    }

    /**
     * @return ActiveQuery
     */
    public function getClientType()
    {
        return $this->hasOne(ClientTypeList::class, ['client_type_id' => 'client_type']);
    }

    /**
     * @return ActiveQuery
     */
    public function getOrganization()
    {
        return $this->hasOne(Organizations::class, ['organization_id' => 'organization_id']);
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

//    /**
//     * @return \yii\db\ActiveQuery
//     */
//    public function getDeviceClientLists()
//    {
//        return $this->hasMany(DeviceClientList::class, ['client_id' => 'client_id']);
//    }

    /**
     * @return ActiveQuery
     * @throws InvalidConfigException
     */
    public function getDevices()
    {
        return $this->hasMany(Device::class, ['device_id' => 'device_id'])->viaTable('device_client_list', ['client_id' => 'client_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getAbonent()
    {
        return $this->hasOne(Abonent::class, ['card_id' => 'client_id']);
    }

//    /**
//     * @return \yii\db\ActiveQuery
//     */
//    public function getHintCalls()
//    {
//        return $this->hasMany(HintCalls::class, ['client_id' => 'client_id']);
//    }
//
//    /**
//     * @return \yii\db\ActiveQuery
//     */
//    public function getMailMessages()
//    {
//        return $this->hasMany(MailMessages::class, ['client_id' => 'client_id']);
//    }
//
//    /**
//     * @return \yii\db\ActiveQuery
//     */
//    public function getNotes()
//    {
//        return $this->hasMany(Notes::class, ['client_id' => 'client_id']);
//    }
//
    /**
     * @return ActiveQuery
     */
    public function getSegmentedClients()
    {
        return $this->hasMany(SegmentedClients::class, ['segment_client_id' => 'client_id']);
    }

    /**
     * @inheritdoc
     * @return ClientQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ClientQuery(static::class);
    }

    /**
     * @param bool $all
     * @param string $regionCode
     * @return array
     * @throws Exception
     */
    public static function getFiasRegions($all = false, $regionCode = '')
    {
        if ($all) {
            $regionsLimits = $regionCode ? '' : "regions.REGIONCODE IN ($regionCode)";
            $result = (new Query())
                ->select('regions.AOGUID, CONCAT(regions.FORMALNAME, " ", regions.SHORTNAME) AS FORMALNAME')
                ->from('addrobj ao')
                ->join('{{addrobj}} regions', 'regions.PARENTGUID = ao.AOGUID')
                ->where(['and', 'ao.AOLEVEL = 1', 'regions.ACTSTATUS = 1', 'regions.LIVESTATUS = 1', $regionsLimits])
                ->orderBy('regions.FORMALNAME ASC')
                ->all(Yii::$app->db_fias);
        } else {
            // только те, где есть клиенты
            $usedRegions = (new Query())
                ->select('cd.detail_value')
                ->from('{{client_details}} cd')
                ->where(['and', 'cd.detail_type = 22', 'cd.detail_status = 1'])
                ->groupBy('cd.detail_value')
                ->createCommand(Yii::$app->db)->queryColumn();

            $result = (new Query())
                ->select(['ao.AOGUID', 'CONCAT(ao.FORMALNAME, " ", ao.SHORTNAME) AS FORMALNAME'])
                ->from('addrobj ao')
                ->where(['and', 'ao.ACTSTATUS = 1', 'ao.LIVESTATUS = 1', ['in', 'ao.AOGUID', $usedRegions]])
                ->orderBy('ao.FORMALNAME ASC')
                ->all(Yii::$app->db_fias);
        }

        return ArrayHelper::map($result, 'AOGUID', 'FORMALNAME');
    }

    private static $cashCountClients = [];

    /**
     * return count clients with cabinet and total count by locality
     *
     * @param string $fiasString string in full FIAS format
     * @return array|bool
     */
    public static function getCountClientsByLocality($fiasString)
    {
        if (array_key_exists($fiasString, self::$cashCountClients)) {
            return self::$cashCountClients[$fiasString];
        }
        $subQuery = (new Query())
            ->select(['have_ls' => 'MAX(IF(cld.detail_type=6,1,0))', 'c' => new Expression(1)])
            ->from(['cl' => '{{client_details}}'])
            ->innerJoin(['cld' => '{{client_details}}'], 'cl.detail_client_id=cld.detail_client_id')
            ->where('`cl`.`detail_type` = 9 AND `cl`.`detail_status`=1 AND `cl`.`detail_value_cache` LIKE :fias_string', [':fias_string' => $fiasString . '%'])
            ->groupBy('`cl`.`detail_client_id`');

        $countClients = (new Query())
            ->select(['sum_have_ls' => 'SUM(t.have_ls)', 'total' => 'SUM(t.c)'])
            ->from(['t' => $subQuery])
            ->one();
        self::$cashCountClients[$fiasString] = $countClients;
        return $countClients;
    }

    /**
     * возвращает значение атрибута клиента по его типу
     * @param int $typeAttribute тип атрибута
     * @return false|int|string|null
     */
    public function getAttributeValueByType($typeAttribute)
    {
        return $this->getAttributesByTypeQueryBuilder($typeAttribute)
            ->indexBy('row_id')
            ->select('detail_value')
            ->scalar();
    }

    /**
     * возвращает значение кэшированного атрибута клиента по его типу
     * @param int $typeAttribute тип атрибута
     * @return mixed|null
     */
    public function getAttributeCacheValueByType($typeAttribute)
    {
        return $this->getAttributesByTypeQueryBuilder($typeAttribute)
            ->indexBy('row_id')
            ->select('detail_value_cache')
            ->scalar();
    }


    /**
     * @param $typeAttribute
     * @return ActiveQuery
     */
    public function getAttributesByTypeQueryBuilder($typeAttribute)
    {
        return ClientDetail::find()
            ->where(
                'detail_client_id=:user_id AND detail_type=:type',
                [
                    ':user_id' => $this->client_id,
                    ':type' => $typeAttribute
                ]
            );
    }

    /**
     * @return false|string|null
     */
    public function getPersonalAccount()
    {
        return $this
            ->getClientDetails()
            ->where([
                'detail_type' => DetailType::TYPE_PERSONAL_ACCOUNT,
                'detail_status' => Detail::STATUS_ACTIVE
            ])
            ->select(['detail_value'])
            ->scalar();
    }

    /**
     * @param $phone
     * @return array
     */
    public static function getClientsByPhone($phone)
    {
        return ClientDetail::find()->alias('cd')
            ->select([
                'id' => 'c.client_id',
                'client_title' => 'c.client_title'])
            ->innerJoin(['c' => self::tableName()], ['cd.detail_client_id' => new Expression('c.client_id')])
            ->where([
                'cd.detail_status' => ClientDetail::STATUS_ACTIVE,
                'cd.detail_type' => DetailType::TYPE_PHONE,
                'cd.detail_value' => $phone
            ])
            ->groupBy(['c.client_id'])
            ->asArray()
            ->all();
    }

    /**
     * @param $client_id
     * @return bool | string | array
     */
    public static function CreditAvailable($client_id)
    {
        if (!empty($credit = Yii::$app->external->getCreditInfo($client_id))) {
            return $credit;
        }
        $details = ClientDetail::find()->alias('c')
            ->select([
                'is_available' => new Expression('IF(c.detail_value >= 0
                                && tf.tarif_cost IS NOT NULL
                                && texp.detail_value < UNIX_TIMESTAMP()
                                && c.detail_value < tf.tarif_cost,
                                TRUE, FALSE)')
            ])
            ->addTariffInfo('c')
            ->where([
                'c.detail_client_id' => $client_id,
                'c.detail_type' => DetailType::TYPE_ACCOUNT_STATE,
                'c.detail_status' => Detail::STATUS_ACTIVE
            ])
            ->scalar();
        return (bool)$details;
    }

    /**
     * @return bool
     */
    public function isEntity()
    {
        return in_array($this->client_type, ClientTypeList::TYPES_FOR_LEGAL, false);
    }

    /**
     * @return ActiveQuery
     */
    public function getDevs()
    {
        return $this->hasMany(NetDevice::class, ['id' => 'device_id'])->alias('ads')->via('activeMigrations');
    }

    /**
     * @return ActiveQuery
     */
    public function getFaces()
    {
        return $this->hasMany(FaceBase::class, ['card_id' => 'client_id'])->alias('faces')->andOnCondition(['faces.a' => FaceBase::ACTIVE]);
    }

    /**
     * @return ActiveQuery
     */
    public function getPpps()
    {
        return $this->hasMany(PppClient::class, ['card_id' => 'client_id'])->alias('ppp')->andOnCondition(['ppp.a' => PppClient::ACTIVE, 'ppp.type' => PppClient::TYPE, 'ppp.enabled' => PppClient::ENABLED]);
    }

    /**
     * @return ActiveQuery
     */
    public function getIps()
    {
        return $this->hasMany(Ip::class, ['cl_id' => 'client_id'])->alias('ai')->andOnCondition(['ai.a' => Ip::ACTIVE]);
        /** @use IpQuery */
    }

    /**
     * @return mixed
     */
    public function getRoute()
    {
        return $this->hasOne(Route::class, ['cl_id' => 'client_id'])->alias('ar')->a()->def();
        /** @use RouteQuery */
    }

    /**
     * @return ActiveQuery
     */
    public function getMigrations()
    {
        return $this->hasMany(DevClient::class, ['client_id' => 'client_id'])->alias('m')
            ->andOnCondition(['m.type' => DevClient::TYPE]);
    }

    /**
     * @return ActiveQuery
     */
    public function getActiveMigrations()
    {
        return $this->hasMany(DevClient::class, ['client_id' => 'client_id'])->alias('am')
            ->andOnCondition([
                'am.active' => DevClient::ACTIVE,
                'am.target' => DevClient::TARGET_BIND,
                'am.type' => DevClient::TYPE
            ]);
    }

    /**
     * @param $client_id
     * @return NasModel|null
     */
    public static function getClientNas($client_id)
    {
        try {
            return self::findOne($client_id)->activeMigrations[0]->device->abonents[0]->nas;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return DevModel|null
     */
    public function getParentDevice()
    {
        try {
            if ($device = $this->activeMigrations[0]) {
                return DevModel::findOne(['id' => $device->device_id])->getParentDevice();
            }
        } catch (\Exception $e) {
            return null;
        }
    }


    /**
     * @return bool|Response
     * @throws NotFoundHttpException
     * @throws BadRequestHttpException
     */
    public function detachUserFromDevice()
    {
        $devices = $this->activeMigrations;

        if (empty($devices)) {
            throw new NotFoundHttpException('У клиента не нашлось оборудования.');
        }

        if ($this->client_type === ClientTypeList::TYPE_INDIVIDUAL) {
            try {
                Yii::$app->external->deleteUser($this->getAttributeValueByType(DetailType::TYPE_PERSONAL_ACCOUNT));
            } catch (Exception $e) {
                throw new BadRequestHttpException('Не удалось открепить клиента во внешней системе');
            }
        }

        foreach ($devices as $dev) {
            if (!($device = BaseDev::findOne($dev->device_id))) {
                throw new NotFoundHttpException('Не удалось найти оборудование.');
            }

            $device->onUnlinkClient($this->client_id)->unlinkClient($this->client_id)->apply();

            $device->location = Yii::$app->user->id;

            $device->save();
        }

        $this->client_status = ClientStatusList::STATUS_DELETED;

        return $this->save();
    }

    /**
     * @param $id
     * @return false|string|null
     */
    public static function getClientTitleById($id)
    {
        return self::find()
            ->select('client_title')
            ->where([
                'client_id' => $id,
                'client_status' => Detail::STATUS_ACTIVE,
            ])
            ->scalar();
    }

    /**
     * @return ActiveQuery
     */
    public function getLegalTariffs()
    {
        return $this->hasMany(ClientDetail::class, ['detail_client_id' => 'client_id'])->alias('tar')
            ->andOnCondition([
                'tar.detail_type' => DetailType::TYPE_LEGAL_ENTITY_TARIFF,
                'tar.detail_status' => Detail::STATUS_ACTIVE
            ]);
    }

    /**
     * @param $id
     * @return false|string|null
     */
    public static function getClientTypeById($id)
    {
        return self::find()
            ->select('client_type')
            ->where([
                'client_id' => $id,
                'client_status' => Detail::STATUS_ACTIVE,
            ])
            ->scalar();
    }

    /**
     * @return ActiveQuery
     */
    public function getParent()
    {
        return $this->hasOne(Client::class, ['client_id' => 'parent_id']);
    }

    /**
     * @return ActiveQuery
     * @throws InvalidConfigException
     */
    public function getBillServiceGroups()
    {
        return $this->hasMany(BillServiceGroupList::class, ['service_group_id' => 'service_group_id'])->viaTable('bill_client_service_group', ['client_id' => 'client_id']);
    }

    /**
     * @return ActiveQuery
     * @throws InvalidConfigException
     */
    public function getBillServices()
    {
        return $this->hasMany(BillService::class, ['service_id' => 'service_id'])->viaTable('bill_client_service', ['client_id' => 'client_id']);
    }

    /**
     * @return ActiveQuery
     * @throws InvalidConfigException
     */
    public function getBillClientServices()
    {
        return $this->hasMany(BillClientService::class, ['client_id' => 'client_id']);
    }

    /**
     * @return ActiveQuery
     * @throws InvalidConfigException
     */
    public function getTariffs()
    {
        return $this->hasMany(Tariffs::class, ['tarif_id' => 'tariff_id'])->viaTable('bill_client_tariff', ['client_id' => 'client_id']);
    }

    /**
     * @return ActiveQuery
     * @throws InvalidConfigException
     */
    public function getBillClientTariffs()
    {
        return $this->hasMany(BillClientTariff::class, ['client_id' => 'client_id']);
    }

    /**
     * @param $clientId
     * @return array
     */
    public static function getSuccessor($clientId)
    {
        return self::find()
            ->select('client_id')
            ->where([
                'parent_id' => $clientId,
                'client_status' => ClientStatusList::STATUS_ACTIVE
            ])
            ->column();
    }

    /**
     * @return ActiveQuery
     */
    public function getChildren()
    {
        return $this->hasMany(__CLASS__, ['parent_id' => 'client_id']);
    }

    /**
     * @return string[]
     * @throws BadRequestHttpException
     */
    public function detachExternal()
    {
        if ($this->client_type === ClientTypeList::TYPE_INDIVIDUAL && $this->personalAccount) {
            try {
                Yii::$app->external->deleteUser($this->personalAccount);
            } catch (Exception $e) {
                throw new BadRequestHttpException('Не удалось открепить клиента во внешней системе.');
            }
        }
        return [
            'result' => 'ok',
            'message' => 'Клиент откреплен во внешней системе.'
        ];
    }

    /**
     * @return string[]
     * @throws BadRequestHttpException
     */
    public function restoreExternal()
    {
        if ($this->client_type === ClientTypeList::TYPE_INDIVIDUAL && $this->personalAccount) {
            $login = ClientDetail::getDetailValueByType($this->client_id, DetailType::TYPE_LOGIN);
            if ($login === null) {
                return [
                    'result' => 'ok',
                    'message' => 'Нет логина для восстановления во внешней системе.'
                ];
            }
            try {
                Yii::$app->external->restoreUser($login);
            } catch (Exception $e) {
                throw new BadRequestHttpException('Не удалось восстановить клиента во внешней системе.');
            }
        }
        return [
            'result' => 'ok',
            'message' => 'Клиент восстановлен во внешней системе.'
        ];
    }
}