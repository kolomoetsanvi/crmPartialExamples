<?php


namespace console\controllers;


use common\components\Helper;
use common\models\baseStation\BaseStation;
use common\models\baseStation\BaseStationDetail;
use common\models\client\ClientDetailTypeList;
use common\models\client\ClientDetailTypeListTypeList;
use common\models\details\Detail;
use common\models\details\DetailType;
use common\models\document\Document;
use common\models\document\DocumentDetail;
use common\models\document\DocumentStatusList;
use common\models\document\DocumentTypeList;
use yii\console\Controller;
use yii\db\Expression;
use yii\helpers\Console;


/**
 * Class DocumentsController
 * @package console\controllers
 */
class DocumentsController extends Controller
{

    /**
     * Импорт (создание) сущностей Документов по данных из Базовых станций
     */
    public function actionImportChannelDocuments()
    {
        $baseStationsDetail = BaseStationDetail::find()->alias('channel')
            ->select([
                'channel.row_id as channelRow',
                'channel.detail_base_station_id as stationId',
                'dContract.detail_value as contractName',
                'dContractDate.detail_value as contractDate',
                'dOrderName.detail_value as orderName',
                'dOrderDate.detail_value as orderDate',
                'dPryce.detail_value as price',
                'dCapacity.detail_value as capacity',
                'dProvider.detail_value as provider',
                'dDocId.detail_value as docId',
                'dDocPath.detail_value_cache as docPath',
            ])
            ->innerJoin(['bs' => BaseStation::tableName()], [
                'channel.detail_base_station_id' => new Expression('bs.station_id'),
                'bs.station_status' => Detail::STATUS_ACTIVE
            ])
            ->leftJoin(['dTransfer' => 'base_station_details'],
                [
                    'dTransfer.parent_id' => new Expression('channel.row_id'),
                    'dTransfer.detail_type' => DetailType::TYPE_TRANSFER_METHOD,
                    'dTransfer.detail_status' => Detail::STATUS_ACTIVE
                ]
            )
            ->leftJoin(['dContract' => 'base_station_details'],
                [
                    'dContract.parent_id' => new Expression('channel.row_id'),
                    'dContract.detail_type' => DetailType::TYPE_CONTRACT_MULTY,
                    'dContract.detail_status' => Detail::STATUS_ACTIVE
                ]
            )
            ->leftJoin(['dContractDate' => 'base_station_details'],
                [
                    'dContractDate.parent_id' => new Expression('channel.row_id'),
                    'dContractDate.detail_type' => DetailType::TYPE_CONTRACT_DATE,
                    'dContractDate.detail_status' => Detail::STATUS_ACTIVE
                ]
            )
            ->leftJoin(['dOrderName' => 'base_station_details'],
                [
                    'dOrderName.parent_id' => new Expression('channel.row_id'),
                    'dOrderName.detail_type' => DetailType::TYPE_ORDER_BLANK,
                    'dOrderName.detail_status' => Detail::STATUS_ACTIVE
                ]
            )
            ->leftJoin(['dOrderDate' => 'base_station_details'],
                [
                    'dOrderDate.parent_id' => new Expression('channel.row_id'),
                    'dOrderDate.detail_type' => DetailType::TYPE_ORDER_BLANK_DATE,
                    'dOrderDate.detail_status' => Detail::STATUS_ACTIVE
                ]
            )
            ->leftJoin(['dPryce' => 'base_station_details'],
                [
                    'dPryce.parent_id' => new Expression('channel.row_id'),
                    'dPryce.detail_type' => DetailType::TYPE_PRICE,
                    'dPryce.detail_status' => Detail::STATUS_ACTIVE
                ]
            )
            ->leftJoin(['dCapacity' => 'base_station_details'],
                [
                    'dCapacity.parent_id' => new Expression('channel.row_id'),
                    'dCapacity.detail_type' => DetailType::TYPE_CAPACITY,
                    'dCapacity.detail_status' => Detail::STATUS_ACTIVE
                ]
            )
            ->leftJoin(['dProvider' => 'base_station_details'],
                [
                    'dProvider.parent_id' => new Expression('channel.row_id'),
                    'dProvider.detail_type' => DetailType::TYPE_PROVIDER,
                    'dProvider.detail_status' => Detail::STATUS_ACTIVE
                ]
            )
            ->leftJoin(['dDocId' => 'base_station_details'],
                [
                    'dDocId.parent_id' => new Expression('channel.row_id'),
                    'dDocId.detail_type' => DetailType::TYPE_DOCUMENT,
                    'dDocId.detail_status' => Detail::STATUS_ACTIVE
                ]
            )
            ->leftJoin(['dDocPath' => 'base_station_details'],
                [
                    'dDocPath.parent_id' => new Expression('channel.row_id'),
                    'dDocPath.detail_type' => DetailType::TYPE_DOCUMENT,
                    'dDocPath.detail_status' => Detail::STATUS_ACTIVE
                ]
            )
            ->where([
                'channel.detail_type' => DetailType::TYPE_CHANNEL_LINK,
                'channel.detail_status' => Detail::STATUS_ACTIVE,
                'dTransfer.detail_value' => 2
            ])
            ->asArray()
            ->all();

        $countBS = (empty($baseStationsDetail)) ? 0 : count($baseStationsDetail);
        $step = 0;
        $importBS = 0;
        Console::stdout(Helper::currentTime() . ' Старт импорта доументов Каналов связи.' . PHP_EOL);
        Console::stdout('Необходимо обработать ' . $countBS . ' записей.' . PHP_EOL);

        foreach ($baseStationsDetail as $bs) {
            if ($this->saveBsDetail($bs)) {
                $importBS++;
            }
            $step++;
            if ($step % 10 === 0) {
                Console::stdout('Обработано ' . $step . ' документов. Из ' . $countBS . PHP_EOL);
            }
        }
        Console::stdout(Helper::currentTime() . ' Импорт окончен.' . PHP_EOL);
        Console::stdout('Импортировано ' . $importBS . ' заказов.' . PHP_EOL);
    }


    /**
     * @param BaseStation $bs
     * @return bool
     */
    public function saveBsDetail($bs)
    {
        if ($bs['contractName'] === null) {
            return false;
        }
        $provider = $this->getProvider($bs['provider']);
        $contract = Document::getByTypeTitle(DocumentTypeList::TYPE_CONTRACT, $bs['contractName'], $provider);
        if ($contract === null) {
            $contract = new Document();
            $contract->document_status = DocumentStatusList::STATUS_ACTIVE;
            $contract->document_type = DocumentTypeList::TYPE_CONTRACT;
            $contract->document_title = $bs['contractName'];
            $contract->create_date = time();
            $contract->created_by_user = 1;
            if (!$contract->save()) {
                return false;
            }
            $this->saveDetail($contract->document_id, $bs['contractDate'], DetailType::TYPE_CONTRACT_DATE);
            $this->saveDetail($contract->document_id, $provider, DetailType::TYPE_CHANNEL_PARTNER);

        }

        $order = Document::getByTypeTitle(DocumentTypeList::TYPE_ORDER, $bs['orderName'], null, $contract->document_id);
        if (($order !== false && $order !== null) || $bs['orderName'] === null) {
            return false;
        }

        $order = new Document();
        $order->parent_id = $contract->document_id;
        $order->document_status = DocumentStatusList::STATUS_ACTIVE;
        $order->document_type = DocumentTypeList::TYPE_ORDER;
        $order->document_title = $bs['orderName'];
        $order->create_date = time();
        $order->created_by_user = 1;
        if (!$order->save()) {
            return false;
        }
        BaseStationDetail::setDetail($bs['stationId'], DetailType::TYPE_DOCUMENT_ID, $order->document_id, '', 0, $bs['channelRow']);
        $this->saveDetail($order->document_id, $bs['orderDate'], DetailType::TYPE_CONTRACT_DATE);
        $this->saveDetail($order->document_id, $bs['price'], DetailType::TYPE_SUBSCRITION);
        $this->saveDetail($order->document_id, $bs['capacity'], DetailType::TYPE_CAPACITY);
        if ($bs['docId'] !== null && $bs['docPath'] !== null) {
            DocumentDetail::setDetail($order->document_id, DetailType::TYPE_DOCUMENT, $bs['docId'], $bs['docPath']);
        }

        return true;
    }


    /**
     * @param int $documentId
     * @param string $value
     * @param int $typeId
     * @return bool
     */
    public function saveDetail($documentId, $value, $typeId)
    {
        if (!isset($value) || $value === '') {
            return false;
        }
        $detailTypeType = (int)ClientDetailTypeList::getDetailTypeTypeByType($typeId);
        $detail_value = ClientDetailTypeListTypeList::saveFormat($detailTypeType, $value, $typeId);
        $detail_value_cache = ($detailTypeType === DetailType::TYPE_VIEW_LIST) ? $value : '';
        DocumentDetail::setDetail($documentId, $typeId, $detail_value, $detail_value_cache);
        return true;
    }

    /**
     * @param string $value
     * @return bool
     */
    public function getProvider($value)
    {
        switch ($value) {
            case '*': // конфиденциальная информация
            case '*': // конфиденциальная информация
            case '*': // конфиденциальная информация
                return '*'; // конфиденциальная информация
            case '*': // конфиденциальная информация
            case '*': // конфиденциальная информация
                return '*'; // конфиденциальная информация
            case '*': // конфиденциальная информация
            case '*': // конфиденциальная информация
                return '*'; // конфиденциальная информация
            default:
                return '';
        }
    }
}