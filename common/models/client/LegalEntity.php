<?php

namespace common\models\client;

use common\models\calls\Call;
use common\models\calls\CallList;
use common\models\calls\CallListDetail;
use common\models\client\query\ClientQuery;
use common\models\client\search\ClientService;
use common\models\details\Detail;
use common\models\details\DetailType;
use common\models\devices\Device;
use common\models\devices\DeviceClientList;
use common\models\HintCalls;
use common\models\mail\MailMessages;
use common\models\Notes;
use common\models\Organizations;
use common\models\segments\SegmentedClients;
use common\models\Users;
use yii\db\ActiveQuery;

/**
 * This is the model class for table "clients".
 *
 * @property int $client_id
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
 * @property LegalEntity legalEntity
 * @property LegalEntity[] orders
 * @property LegalEntity[] contracts
 * @property LegalEntity contract
 */
class LegalEntity extends Client
{
    public $legalEntityTitle;

    public $filteredAttributes = [
        DetailType::TYPE_SUBCONTRACT,
        DetailType::TYPE_PARENT
    ];

    public $filteredDocuments = [
        DetailType::TYPE_VIEW_IMAGE,
        DetailType::TYPE_VIEW_FILE,
        DetailType::TYPE_VIEW_FILE_ONLINE
    ];

    /**
     * @return ClientQuery
     */
    public function getContracts()
    {
        return $this->getRelatedQuery(
            ClientTypeList::TYPE_CONTRACT
        );
    }

    /**
     * @return ClientQuery
     */
    public function getOrders()
    {
        return $this->getRelatedQuery(
            ClientTypeList::TYPE_ORDER
        );
    }

    /**
     * @return array|Client|LegalEntity
     */
    public function getContract()
    {
        return self::find()->alias('c')
            ->where(['c.client_id' => $this->parent_id, 'c.client_type' => ClientTypeList::TYPE_CONTRACT])
            ->one();
    }

    /**
     * @return array|Client|LegalEntity
     */
    public function getLegalEntity()
    {
        return self::find()->alias('c')
            ->where(['c.client_id' => $this->parent_id, 'c.client_type' => ClientTypeList::TYPE_LEGAL_ENTITIES])
            ->one();
    }

    /**
     * @return LegalEntity|null
     */
    public function getParentInternal()
    {
        $parentId = (int)ClientDetail::getDetailValueByType($this->client_id, DetailType::TYPE_PARENT_CLIENT);
        return self::findOne($parentId);
    }

    /**
     * @param $clientType
     * @return ClientQuery
     */
    protected function getRelatedQuery($clientType)
    {
        return self::find()->alias('c')
            ->where(['c.client_type' => $clientType, 'c.parent_id' => $this->client_id]);
    }

    /**
     * @return ClientService[]
     */
    public function getServices()
    {
        return ClientService::getServices($this->client_id);
    }
}