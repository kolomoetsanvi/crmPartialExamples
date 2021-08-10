<?php

namespace common\models\client\search;

use common\components\helpers\StringHelper;
use common\models\client\Client;
use common\models\client\ClientDetail;
use common\models\client\ClientStatusList;
use common\models\client\ClientTypeList;
use common\models\client\LegalEntity;
use common\models\details\Detail;
use common\models\details\DetailType;
use common\models\scanner\items\routing\Ip;
use DateTime;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\db\Query;

/**
 *
 * @property string $legalEntity
 * @property string $legalEntityTitle
 * @property int $legalEntityId
 * @property int $inn
 * @property int $manager
 * @property string $address
 * @property string $addressFIAS
 * @property int $deviceId
 * @property int $dateEndContract
 * @property int $client_id
 * @property string $leOrder
 * @property string $leContract
 * @property int $orderId
 * @property int $contractId
 * @property int $bgbId
 * @property int $countOrder
 * @property string $tariff
 * ClientSearch represents the model behind the search form about `common\models\client\Client`.
 */
class LegalEntitySearch extends LegalEntity
{

    public $legalEntity;
    public $legalEntityTitle;
    public $legalEntityId;
    public $inn;
    public $manager;
    public $address;
    public $addressFIAS;
    public $fiasIdentity;
    public $dateEndContract;
    public $deviceId;
    public $leContract;
    public $leOrder;
    public $SurvContract;
    public $SurvContractId;
    public $orderId;
    public $SorderId;
    public $contractId;
    public $bgbId;
    public $hostname;
    public $countOrder;
    public $service;
    public $tariff;
    public $fullSearch;

    public $serv;
    public $actual;
    public $attached;
    public $addresses;
    public $interfaces;

    public $balance;
    public $balancePeriod;
    public $operatingSum;
    public $income;


    private $validationRange = [
        ClientTypeList::TYPE_LEGAL_ENTITIES,
        ClientTypeList::TYPE_CONTRACT,
        ClientTypeList::TYPE_ORDER
    ];

    public $validationDocumentsRange = [
        DetailType::TYPE_DOCUMENTS_CONTRACT,
        DetailType::TYPE_DOCUMENTS_ORDER,
        DetailType::TYPE_DOCUMENTS_ADDITIONAL_AGREEMENT,
        DetailType::TYPE_DOCUMENTS_TERMINATION,
        DetailType::TYPE_DOCUMENTS_ACT,
        DetailType::TYPE_DOCUMENTS_RECONCILIATION_REPORT,
    ];

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['client_id', 'legalEntityId', 'client_status', 'client_type', 'client_create_date', 'organization_id', 'created_by_user',
                'client_update_date', 'updated_by_user', 'deviceId', 'inn', 'service', 'balance', 'operatingSum', 'income'], 'integer'],
            [['client_title', 'addressFIAS', 'address', 'legalEntity', 'legalEntityTitle', 'leContract', 'leOrder', 'hostname', 'serv', 'actual', 'attached', 'addresses', 'interfaces', 'tariff'], 'string'],
            [['client_type'], 'in', 'range' => $this->validationRange, 'allowArray' => true],
            [['inn', 'manager'], 'string', 'max' => 255],
            [['fullSearch', 'dateEndContract'], 'boolean'],
            [['orderId', 'contractId', 'bgbId', 'countOrder'], 'safe'],
        ];
    }

    /**
     * @return array|string[]
     */
    public function attributeLabels()
    {
        return array_merge(parent::attributeLabels(),
            [
                'legalEntityTitle' => 'Юридическое лицо',
                'legalEntityId' => 'ID',
                'orderId' => 'ID',
                'inn' => 'ИНН',
                'manager' => 'Менеджер',
                'address' => 'Адрес подключения',
                'addressFIAS' => 'Адрес ФИАС',
                'dateEndContract' => 'Дата окончания договора',
                'deviceId' => 'ID оборудования',
                'leContract' => 'Договор',
                'leOrder' => 'Заказ',
                'service' => 'Услуга',
                'tariff' => 'Тариф юр. лица',
                'balance' => 'Входящий остаток',
                'operatingSum' => 'Наработка',
                'income' => 'Приход'
            ]
        );
    }

    /**
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = LegalEntity::find()->alias('c');

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        $query->joinWith([
            'clientDetails cd' => static function (ActiveQuery $subquery) {
                $subquery->andOnCondition([
                    'cd.detail_type' => DetailType::TYPE_INN,
                    'cd.detail_status' => Detail::STATUS_ACTIVE
                ]);
            },
        ]);
        $query->andFilterWhere([
            'c.client_type' => $this->validationRange
        ]);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'c.client_id' => $this->client_id,
            'c.client_status' => $this->client_status,
            'c.client_create_date' => $this->client_create_date,
            'c.organization_id' => $this->organization_id,
            'c.created_by_user' => $this->created_by_user,
            'c.client_update_date' => $this->client_update_date,
            'c.updated_by_user' => $this->updated_by_user,
        ]);


        if ($this->client_title) {
            $query->andFilterWhere(['like', 'c.client_title', $this->client_title]);
        } else {
            $query->andFilterWhere(['c.client_type' => $this->client_type]);
        }
        $query->andFilterWhere(['like', 'cd.detail_value', $this->inn]);

        return $dataProvider;
    }

    /**
     * @param ActiveQuery $query
     * @param array $params
     * @return ActiveDataProvider
     */
    public function searchByQuery(ActiveQuery $query, array $params)
    {
        $dataProvider = new ActiveDataProvider([
            'query' => $query
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere(['like', 'c.client_title', $this->client_title]);
        $query->andFilterWhere(['c.client_id' => $this->client_id]);
        $query->andFilterWhere(['like', 'cd.detail_value', $this->address]);

        return $dataProvider;
    }

    private function contractsAndOrdersQuery()
    {
        // выбираем договоры без заказов
        $docs = self::find()->alias('contr')
            ->select([
                'client_id' => 'contr.client_id',
                'legal_id' => 'contr.parent_id',
                'ordr_id' => 'ordr.client_id',
                'ordr_title' => 'ordr.client_title',
                'contr_id' => 'contr.client_id',
                'contr_title' => 'contr.client_title'
            ])
            ->leftJoin(['ordr' => self::tableName()], [
                'ordr.parent_id' => new Expression('contr.client_id'),
                'ordr.client_type' => ClientTypeList::TYPE_ORDER,
                'ordr.client_status' => ClientStatusList::STATUS_ACTIVE
            ])
            ->where([
                'contr.client_type' => ClientTypeList::TYPE_CONTRACT,
                'contr.client_status' => ClientStatusList::STATUS_ACTIVE,
                'ordr.client_id' => null,
            ]);

        // выбираем все заказы и приклеиваем к ним договоры
        $orders = self::find()->alias('ordr')
            ->select([
                'client_id' => 'ordr.client_id',
                'legal_id' => 'contr.parent_id',
                'ordr_id' => 'ordr.client_id',
                'ordr_title' => 'ordr.client_title',
                'contr_id' => 'contr.client_id',
                'contr_title' => 'contr.client_title'
            ])
            ->leftJoin(['contr' => self::tableName()], [
                'ordr.parent_id' => new Expression('contr.client_id'),
                'contr.client_type' => ClientTypeList::TYPE_CONTRACT,
                'contr.client_status' => ClientStatusList::STATUS_ACTIVE
            ])
            ->where([
                'ordr.client_type' => ClientTypeList::TYPE_ORDER,
                'ordr.client_status' => ClientStatusList::STATUS_ACTIVE
            ])
            ->andWhere(['not', ['ordr.parent_id' => null]]);

        // соединяем заказы и договоры без заказов
        return $docs->union($orders);
    }

    /**
     * @param array $params
     * @return ActiveDataProvider
     */
    public function searchWithDevices(array $params)
    {
        $all = self::find()
            ->select([
                't.client_id',
                'leOrder' => 't.ordr_title',
                'leContract' => 't.contr_title',
                'contractId' => 't.contr_id',
                'legalEntityTitle' => 'legal.client_title',
                'legalEntityId' => 'legal.client_id',
                'dateEndContract' => 'dateEndContract.detail_value',
                'address' => 'address.detail_value',
                'service' => 'service.detail_value'
            ])
            ->from(['t' => $this->contractsAndOrdersQuery()])
            ->leftJoin(['legal' => 'clients'],
                [
                    'legal.client_id' => new Expression('t.legal_id'),
                    'legal.client_type' => ClientTypeList::TYPE_LEGAL_ENTITIES,
                    'legal.client_status' => Detail::STATUS_ACTIVE
                ]
            )
            ->addLeftActiveDetail('t.client_id', 'dateEndContract', DetailType::TYPE_CONTRACT_DATE_END)
            ->addLeftActiveDetail('t.client_id', 'address', DetailType::TYPE_ADDRESS)
            ->addLeftActiveDetail('t.client_id', 'service', DetailType::TYPE_SERVICES)
            ->joinWith('activeMigrations');

        $this->load($params);
        $this->attributes = $params;

        $dataProvider = new ActiveDataProvider([
            'query' => $all,
            'pagination' => [
                'pageSize' => 50
            ]
        ]);


        $dataProvider->setSort([
            'attributes' => array_merge($dataProvider->getSort()->attributes, [
                'legalEntityTitle',
                'legalEntityId',
                'leContract',
                'leOrder',
                'dateEndContract',
                'address'
            ])
        ]);


        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            $all->where('0=1');
            return $dataProvider;
        }

        $all->andFilterWhere(['like', 'legal.client_id', $this->legalEntityId]);
        $all->andFilterWhere(['like', 'legal.client_title', $this->legalEntityTitle]);
        $all->andFilterWhere(['like', 't.contr_title', $this->leContract]);
        $all->andFilterWhere(['like', 't.ordr_title', $this->leOrder]);
        $all->andFilterWhere(['>=', 'dateEndContract.detail_value', $this->dateEndContract ? strtotime($this->dateEndContract . ' 00:00:00') : null]);
        $all->andFilterWhere(['<=', 'dateEndContract.detail_value', $this->dateEndContract ? strtotime($this->dateEndContract . ' 23:59:59') : null]);
        $all->andFilterWhere(['like', 'address.detail_value', $this->address]);
        $all->andFilterWhere(['like', 'am.hostname', $this->hostname]);

        switch ($this->actual) {
            case 'actual':
                $all->andWhere(['or', ['>', 'dateEndContract.detail_value', (new DateTime())->getTimestamp()], ['dateEndContract.detail_value' => 0]])->andWhere(['not', ['like', 'c.client_title', 'Переоформлен%', false]]);
                break;
            case 'overdue':
                $all->andWhere(['<', 'dateEndContract.detail_value', (new DateTime())->getTimestamp()]);
                break;
        }
        switch ($this->serv) {
            case 'internet':
                $all->andWhere(['service.detail_value' => [ClientService::INTERNET_STATIC, ClientService::INTERNET_DYNAMIC]]);
                break;
            case 'channel':
                $all->andWhere(['service.detail_value' => [ClientService::CHANNEL]]);
                break;
            case 'not_present':
                $all->andWhere(['service.detail_value' => null]);
                break;
        }
        switch ($this->attached) {
            case 'bound':
                $all->andWhere(['not', ['am.id' => null]]);
                break;
            case 'not_bound':
                $all->andWhere(['am.id' => null]);
                break;
        }
        switch ($this->addresses) {
            case 'present':
                $all->joinWith('ips')->andWhere(['not', ['ai.id' => null]]);
                break;
            case 'absent':
                $all->joinWith('ips')->andWhere(['ai.id' => null]);
                break;
        }
        switch ($this->interfaces) {
            case 'present':
                $all->joinWith('faces')->andWhere(['not', ['faces.id' => null]]);
                break;
            case 'absent':
                $all->joinWith('faces')->andWhere(['faces.id' => null]);
                break;
        }
        return $dataProvider;
    }

    /**
     * @param array $params
     * @return ActiveDataProvider
     */
    public function searchWithAddress(array $params)
    {
        $all = self::find()
            ->select([
                't.client_id',
                'leOrder' => 't.ordr_title',
                'leContract' => 't.contr_title',
                'contractId' => 't.contr_id',
                'legalEntityTitle' => 'legal.client_title',
                'legalEntityId' => 'legal.client_id',
                'dateEndContract' => 'dateEndContract.detail_value',
                'address' => 'address.detail_value',
                'fiasIdentity' => 'addressFias.detail_value',
                'addressFIAS' => 'addressFias.detail_value_cache',
            ])
            ->from(['t' => $this->contractsAndOrdersQuery()])
            ->leftJoin(['legal' => 'clients'],
                [
                    'legal.client_id' => new Expression('t.legal_id'),
                    'legal.client_type' => ClientTypeList::TYPE_LEGAL_ENTITIES,
                    'legal.client_status' => Detail::STATUS_ACTIVE
                ]
            )
            ->addLeftActiveDetail('t.client_id', 'dateEndContract', DetailType::TYPE_CONTRACT_DATE_END)
            ->addLeftActiveDetail('t.client_id', 'address', DetailType::TYPE_ADDRESS)
            ->addLeftActiveDetail('t.client_id', 'addressFias', DetailType::TYPE_FIAS);

        $this->load($params);
        $this->attributes = $params;

        $dataProvider = new ActiveDataProvider([
            'query' => $all,
            'pagination' => [
                'pageSize' => 50
            ]
        ]);


        $dataProvider->setSort([
            'attributes' => array_merge($dataProvider->getSort()->attributes, [
                'legalEntityTitle',
                'legalEntityId',
                'leContract',
                'leOrder',
                'dateEndContract',
                'address',
                'fiasAddress'
            ])
        ]);


        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            $all->where('0=1');
            return $dataProvider;
        }

        $all->andFilterWhere(['like', 'legal.client_id', $this->legalEntityId]);
        $all->andFilterWhere(['like', 'legal.client_title', $this->legalEntityTitle]);
        $all->andFilterWhere(['like', 't.contr_title', $this->leContract]);
        $all->andFilterWhere(['like', 't.ordr_title', $this->leOrder]);
        $all->andFilterWhere(['>=', 'dateEndContract.detail_value', $this->dateEndContract ? strtotime($this->dateEndContract . ' 00:00:00') : null]);
        $all->andFilterWhere(['<=', 'dateEndContract.detail_value', $this->dateEndContract ? strtotime($this->dateEndContract . ' 23:59:59') : null]);
        $all->andFilterWhere(['like', 'address.detail_value', $this->address]);
        $all->andFilterWhere(['like', 'addressFias.detail_value_cache', $this->addressFIAS]);


        return $dataProvider;
    }

    /**
     * @return array|Client[]
     */
    public function searchWithFias()
    {
        unset($this->validationRange[0]);

        return LegalEntity::find()->alias('c')
            ->select(['c.client_id',
                'idBgB' => 'cdb.detail_value',
                'address' => 'address.detail_value',
                'addressFIAS' => 'addressFIAS.detail_value_cache'
            ])
            ->leftJoin(['cdb' => 'client_details'],
                ['and',
                    ['cdb.detail_client_id' => new Expression('c.client_id')],
                    ['cdb.detail_type' => DetailType::TYPE_PERSONAL_ACCOUNT],
                    ['cdb.detail_status' => Detail::STATUS_ACTIVE],
                ]
            )
            ->leftJoin(['address' => 'client_details'],
                [
                    'and',
                    ['address.detail_client_id' => new Expression('c.client_id')],
                    ['address.detail_type' => DetailType::TYPE_ADDRESS],
                    ['address.detail_status' => Detail::STATUS_ACTIVE],
                ]
            )
            ->innerJoin(['addressFIAS' => 'client_details'],
                [
                    'AND',
                    ['addressFIAS.detail_client_id' => new Expression('c.client_id')],
                    ['addressFIAS.detail_type' => DetailType::TYPE_FIAS],
                    ['addressFIAS.detail_status' => Detail::STATUS_ACTIVE],
                    ['not', ['addressFIAS.detail_value_cache' => null]],
                    ['NOT', ['addressFIAS.detail_value_cache' => new Expression('address.detail_value')]]
                ]
            )
            ->where(['and',
                ['c.client_type' => $this->validationRange],
                ['c.client_status' => Detail::STATUS_ACTIVE]
            ])
            ->asArray()
            ->all();
    }

    /**
     * Выводим договора юрлиц. Связывание через атрибут client.parent_id
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function searchForGrouping($params)
    {
        $countOrdersSub = (new Query())
            ->from(['order' => self::tableName()])
            ->where([
                'order.parent_id' => new Expression('doc.client_id'),
                'order.client_status' => Detail::STATUS_ACTIVE
            ])->createCommand()->rawSql;

        $query = self::find()->alias('doc')
            ->select([
                'doc.client_id',
                'legal.client_title',
                'legalEntityId' => 'legal.client_id',
                'inn' => 'inn.detail_value',
                'manager' => 'mng.detail_value',
                'leContract' => 'doc.client_title',
                'contractId' => 'doc.client_id',
                'address' => 'address.detail_value_cache',
                'countOrder' => new Expression("EXISTS($countOrdersSub)"),
                'dateEndContract' => 'dateEndContract.detail_value'
            ])
            ->innerJoin(['legal' => self::tableName()], [
                'legal.client_id' => new Expression('doc.parent_id'),
                'legal.client_status' => Detail::STATUS_ACTIVE
            ])
            ->addLeftActiveDetail('legal.client_id', 'inn', DetailType::TYPE_INN)
            ->addLeftActiveDetail('legal.client_id', 'mng', DetailType::TYPE_MANAGER)
            ->addLeftActiveDetail('doc.client_id', 'address', DetailType::TYPE_FIAS)
            ->addLeftActiveDetail('doc.client_id', 'dateEndContract', DetailType::TYPE_CONTRACT_DATE_END)
            ->where([
                'doc.client_type' => ClientTypeList::TYPE_CONTRACT,
                'doc.client_status' => Detail::STATUS_ACTIVE
            ]);


        $this->load($params);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20
            ],
        ]);

        $dataProvider->setSort([
            'attributes' => array_merge($dataProvider->getSort()->attributes, [
                'client_title' => [
                    'asc' => ['client_title' => SORT_ASC],
                    'desc' => ['client_title' => SORT_DESC],
                    'default' => SORT_ASC
                ],
                'legalEntityId' => [
                    'asc' => ['legalEntityId' => SORT_ASC],
                    'desc' => ['legalEntityId' => SORT_DESC],
                    'default' => SORT_ASC
                ],
                'inn' => [
                    'asc' => ['inn.detail_value' => SORT_ASC],
                    'desc' => ['inn.detail_value' => SORT_DESC],
                    'default' => SORT_ASC
                ],
                'manager' => [
                    'asc' => ['mng.detail_value' => SORT_ASC],
                    'desc' => ['mng.detail_value' => SORT_DESC],
                    'default' => SORT_ASC
                ],
                'leContract' => [
                    'asc' => ['doc.client_title' => SORT_ASC],
                    'desc' => ['doc.client_title' => SORT_DESC],
                    'default' => SORT_ASC
                ],
                'dateEndContract' => [
                    'asc' => ['dateEndContract.detail_value' => SORT_ASC],
                    'desc' => ['dateEndContract.detail_value' => SORT_DESC],
                    'default' => SORT_ASC
                ],
                'address' => [
                    'asc' => ['address.detail_value' => SORT_ASC],
                    'desc' => ['address.detail_value' => SORT_DESC],
                    'default' => SORT_ASC
                ],
            ])
        ]);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            $query->where('0=1');
            return $dataProvider;
        }

        if (!StringHelper::isEmpty($this->client_title) || !StringHelper::isEmpty($this->address) || !StringHelper::isEmpty($this->legalEntityId)) {
            $query->leftJoin(['ordr' => self::tableName()], [
                'ordr.parent_id' => new Expression('doc.client_id'),
                'ordr.client_status' => Detail::STATUS_ACTIVE
            ]);
        }

        if (!StringHelper::isEmpty($this->client_title)) {
            $query->andWhere(['OR',
                ['like', 'legal.client_title', $this->client_title],
                ['like', 'doc.client_title', $this->client_title],
                ['like', 'ordr.client_title', $this->client_title]
            ])
                ->groupBy('doc.client_id');
        }

        if (!StringHelper::isEmpty($this->address)) {
            $query->addLeftActiveDetail('ordr.client_id', 'addressOrder', DetailType::TYPE_FIAS)
                ->andWhere(['OR',
                    ['like', 'address.detail_value_cache', $this->address],
                    ['like', 'addressOrder.detail_value_cache', $this->address],
                ])
                ->groupBy('doc.client_id');
        }

        $query->andFilterWhere([
            'OR',
            ['legal.client_id' => $this->legalEntityId],
            ['doc.client_id' => $this->legalEntityId],
            ['ordr.client_id' => $this->legalEntityId]
        ]);

        $query->andFilterWhere(['like', 'mng.detail_value', $this->manager]);
        $query->andFilterWhere(['like', 'inn.detail_value', $this->inn]);

        if ($this->dateEndContract) {
            $query->andFilterWhere(['OR',
                ['=', 'dateEndContract.detail_value', '0'],
                ['>', 'dateEndContract.detail_value', (new DateTime())->getTimestamp()]
            ]);
        }

        return $dataProvider;
    }

    /**
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function searchExpandBlock($params)
    {
        $query = self::find()->alias('order')
            ->select(['order.client_id',
                'leOrder' => 'order.client_title',
                'orderId' => 'order.client_id',
                'address' => 'address.detail_value',
                'addressFIAS' => 'fias.detail_value_cache',
                'dateEndContract' => 'dateEndContract.detail_value'
            ])
            ->addLeftActiveDetail('order.client_id', 'address', DetailType::TYPE_ADDRESS)
            ->addLeftActiveDetail('order.client_id', 'fias', DetailType::TYPE_FIAS)
            ->addLeftActiveDetail('order.client_id', 'dateEndContract', DetailType::TYPE_CONTRACT_DATE_END)
            ->where([
                'order.parent_id' => $this->contractId,
                'order.client_type' => ClientTypeList::TYPE_ORDER,
                'order.client_status' => Detail::STATUS_ACTIVE
            ]);

        $this->load($params);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => false,
        ]);

        $query->andFilterWhere(['order.client_id' => $this->orderId]);
        $query->andFilterWhere(['like', 'address.detail_value', $this->address]);
        $query->andFilterWhere(['like', 'order.client_title', $this->leOrder]);

        return $dataProvider;
    }

    /**
     * @return array|Client[]
     */
    public function searchParentContractsWithAddress()
    {
        $idBgb = ClientDetail::find()
            ->select('detail_value')
            ->where(['detail_type' => DetailType::TYPE_PARENT])
            ->groupBy('detail_value')
            ->column();

        return LegalEntity::find()->alias('c')
            ->select(['c.client_id',
                'idBgB' => 'cdb.detail_value',
                'address' => 'address.detail_value',
            ])
            ->leftJoin(['cdb' => 'client_details'],
                ['and',
                    ['cdb.detail_client_id' => new Expression('c.client_id')],
                    ['cdb.detail_type' => DetailType::TYPE_PERSONAL_ACCOUNT],
                    ['cdb.detail_status' => Detail::STATUS_ACTIVE],
                ]
            )
            ->innerJoin(['address' => 'client_details'],
                [
                    'and',
                    ['address.detail_client_id' => new Expression('c.client_id')],
                    ['address.detail_type' => DetailType::TYPE_ADDRESS],
                    ['address.detail_status' => Detail::STATUS_ACTIVE],
                    ['not', ['address.detail_value' => null]],
                    ['not', ['address.detail_value' => ""]],
                ]
            )
            ->where(['and',
                ['c.client_type' => ClientTypeList::TYPE_CONTRACT],
                ['IN', 'cdb.detail_value', $idBgb],
                ['c.client_status' => Detail::STATUS_ACTIVE]
            ])
            ->asArray()
            ->all();
    }

    /**
     * @param LegalEntity $model
     *
     * @return ActiveDataProvider
     */
    public function searchDocuments($model)
    {
        $query = $model->getClientDetails()->alias('cld')
            ->joinWith('uploadFilesInfo')
            ->joinWith('clientDetailTypeList')
            ->where(['and',
                ['cld.detail_type' => $this->validationDocumentsRange],
                ['cld.detail_status' => Detail::STATUS_ACTIVE]
            ])
            ->orderBy('cld.detail_type');

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => false,
        ]);
    }

    /**
     * @return array|Client[]
     */
    public function searchWithDevIp()
    {
        unset($this->validationRange[0]);
        $idBgb = ClientDetail::find()
            ->select('detail_value')
            ->where(['detail_type' => DetailType::TYPE_PARENT])
            ->groupBy('detail_value')
            ->column();

        return LegalEntity::find()->alias('c')
            ->select(['c.client_id',
                'idBgB' => 'cdb.detail_value',
                'devip' => new Expression(
                    "GROUP_CONCAT(INET_NTOA(`devip_net`.`address`) ORDER BY `devip_net`.`address` ASC SEPARATOR ', ')"
                ),
                'cdip' => 'ip_cd.detail_value',
            ])
            ->leftJoin(['cdb' => 'client_details'],
                ['and',
                    ['cdb.detail_client_id' => new Expression('c.client_id')],
                    ['cdb.detail_type' => DetailType::TYPE_PERSONAL_ACCOUNT],
                    ['cdb.detail_status' => Detail::STATUS_ACTIVE],
                ]
            )
            ->innerJoin(['devip_net' => 'net_ip4'],
                ['and',
                    ['devip_net.cl_id' => new Expression('c.client_id')],
                    ['devip_net.a' => Ip::ACTIVE],
                ]
            )
            ->leftJoin(['ip_cd' => 'client_details'],
                ['and',
                    ['ip_cd.detail_client_id' => new Expression('c.client_id')],
                    ['ip_cd.detail_type' => DetailType::TYPE_DEVIP],
                    ['ip_cd.detail_status' => Detail::STATUS_ACTIVE],
                ]
            )
            ->where(
                ['and',
                    ['c.client_type' => $this->validationRange],
                    ['c.client_status' => Detail::STATUS_ACTIVE],
                    ['not', ['IN', 'cdb.detail_value', $idBgb]],
                ]
            )
            ->groupBy('idBgB')
            ->having(['not', ['=', 'devip', new Expression('cdip')]])
            ->orHaving(['cdip' => null])
            ->asArray()
            ->all();
    }

    /**
     * @param $params
     * @return ActiveDataProvider
     */
    public function searchFinance($params)
    {
        $query = self::find()->alias('c')
            ->select([
                'c.client_id',
                'c.client_title',
                'legalEntityId' => 'l.client_id',
                'legalEntityTitle' => 'l.client_title',
                'balance' => 'balance.detail_value',
                'balancePeriod' => 'balance.update_date',
                'operatingSum' => 'IF(UNIX_TIMESTAMP(DATE_FORMAT(CURRENT_DATE(), "%Y-%m-01")) < operating.update_date, operating.detail_value, null)',
                'income' => 'IF(UNIX_TIMESTAMP(DATE_FORMAT(CURRENT_DATE(), "%Y-%m-01")) < income.update_date, income.detail_value, null)'
            ])
            ->active('c')
            ->addLeftActiveDetail('c.client_id', 'parent', DetailType::TYPE_PARENT_CLIENT)
            ->addLeftActiveDetail('c.client_id', 'balance', DetailType::TYPE_INCOMING_BALANCE)
            ->addLeftActiveDetail('c.client_id', 'income', DetailType::TYPE_INCOME)
            ->addLeftActiveDetail('c.client_id', 'operating', DetailType::TYPE_OPERATING_SUM)
            ->leftJoin(['l' => self::tableName()], [
                'parent.detail_value' => new Expression('l.client_id'),
                'l.client_status' => ClientStatusList::STATUS_ACTIVE
            ])
            ->where([
                'c.client_type' => ClientTypeList::TYPE_CONTRACT
            ]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $dataProvider->setSort([
            'attributes' => array_merge($dataProvider->getSort()->attributes, [
                'legalEntityTitle' => [
                    'asc' => ['l.client_title' => SORT_ASC],
                    'desc' => ['l.client_title' => SORT_DESC],
                    'label' => 'Legal Entity',
                    'default' => SORT_ASC
                ],
                'balance' => [
                    'asc' => ['CAST(balance.detail_value AS SIGNED)' => SORT_ASC],
                    'desc' => ['CAST(balance.detail_value AS SIGNED)' => SORT_DESC],
                    'default' => SORT_ASC
                ],
                'operatingSum' => [
                    'asc' => ['CAST(IF(UNIX_TIMESTAMP(DATE_FORMAT(CURRENT_DATE(), "%Y-%m-01")) < operating.update_date, operating.detail_value, null) AS SIGNED)' => SORT_ASC],
                    'desc' => ['CAST(IF(UNIX_TIMESTAMP(DATE_FORMAT(CURRENT_DATE(), "%Y-%m-01")) < operating.update_date, operating.detail_value, null) AS SIGNED)' => SORT_DESC],
                    'default' => SORT_ASC
                ],
                'income' => [
                    'asc' => ['CAST(IF(UNIX_TIMESTAMP(DATE_FORMAT(CURRENT_DATE(), "%Y-%m-01")) < income.update_date, income.detail_value, null) AS SIGNED)' => SORT_ASC],
                    'desc' => ['CAST(IF(UNIX_TIMESTAMP(DATE_FORMAT(CURRENT_DATE(), "%Y-%m-01")) < income.update_date, income.detail_value, null) AS SIGNED)' => SORT_DESC],
                    'default' => SORT_ASC
                ]
            ])
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            $query->where('0=1');
            return $dataProvider;
        }

        return $dataProvider;
    }
}

