<?php

namespace frontend\controllers;

use common\models\bgbillingdb\search\BgbDbContractSearch;
use common\models\client\Client;
use common\models\client\ClientDetail;
use common\models\client\ClientDetailTypeList;
use common\models\client\ClientDetailTypeListTypeList;
use common\models\client\ClientStatusList;
use common\models\client\ClientTypeList;
use common\models\client\LegalEntity;
use common\models\client\search\ClientDevice;
use common\models\client\search\ClientFace;
use common\models\client\search\ClientIp;
use common\models\client\search\ClientService;
use common\models\client\search\LegalEntitySearch;
use common\models\details\Detail;
use common\models\details\DetailType;
use common\models\import\Imports;
use common\models\scanner\DevClient;
use common\models\scanner\Device;
use common\models\scanner\items\FaceBase;
use common\models\scanner\scan\queues\SetSimpleQueue;
use common\models\UploadFiles;
use common\models\Users;
use Exception;
use frontend\models\BillForm;
use frontend\models\InvoiceForm;
use frontend\models\LegalEntityCreateForm;
use frontend\modules\bgbilling\models\BgbParametersForm;
use frontend\modules\infocard\widgets\infoCard\components\Tree;
use http\Exception\RuntimeException;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\data\ArrayDataProvider;
use yii\db\Expression;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\filters\AjaxFilter;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;


/**
 * Class UsersController
 *
 * @package frontend\controllers
 */
class LegalEntitiesController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['index', 'view', 'address', 'download-files'],
                        'roles' => ['view_legal_entities']
                    ],
                    [
                        'allow' => true,
                        'actions' => ['create', 'get-new-legals', 'get-last-legal-id'],
                        'roles' => ['create_legal_entities']
                    ],
                    [
                        'allow' => true,
                        'actions' => ['finance', 'payments'],
                        'roles' => ['main_seller']
                    ],
                    [
                        'allow' => true,
                        'actions' => ['update', 'get-fias', 'update-address', 'upload-files',
                            'update-parameters', 'update-crm-parameters', 'set-assigned-manager', 'update-group-list', 'change-limit', 'change-limit-status'],
                        'roles' => ['edit_legal_entities']
                    ],
                    [
                        'allow' => true,
                        'actions' => ['delete', 'delete-files'],
                        'roles' => ['delete_legal_entities']
                    ],
                    [
                        'allow' => true,
                        'actions' => ['devices', 'create-service', 'delete-service', 'delete-ip', 'delete-face', 'change-limit', 'change-limit-status'],
                        'roles' => ['view_legal_entity_device']
                    ],
                    [
                        'allow' => true,
                        'actions' => ['upsert-device', 'un-bind-device', 'detach-device', 'attach-device'],
                        'roles' => ['edit_legal_entity_device', 'support_user']
                    ],
                    [
                        'allow' => true,
                        'actions' => ['remove-device'],
                        'roles' => ['delete_legal_entity_device']
                    ],
                    [
                        'allow' => true,
                        'actions' => ['expanded'],
                        'roles' => ['view_legal_entities']
                    ],
                    [
                        'allow' => true,
                        'actions' => ['billing'],
                        'roles' => ['main_seller']
                    ],
                ]
            ],
            'verb' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => 'POST'
                ]
            ],
            [
                'class' => AjaxFilter::class,
                'only' => [
                    'get-fias', 'update-address', 'upsert-device', 'un-bind-device', 'detach-device', 'delete-files', 'set-assigned-manager', 'change-limit-status'
                ]
            ]
        ];
    }

    /**
     * Finds the SocketItems model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     *
     * @param int $id
     * @return LegalEntity the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = LegalEntity::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new LegalEntitySearch();
        $dataProvider = $searchModel->searchForGrouping(Yii::$app->request->queryParams);

        return $this->render('index', compact('searchModel', 'dataProvider'));
    }

    /**
     * @return string
     * @throws Exception
     */
    public function actionExpanded()
    {
        $id = (int)Yii::$app->request->post('expandRowKey');

        if ($id === 0) {
            throw new BadRequestHttpException('Не передан обязательный парамерт');
        }

        $searchModel = new LegalEntitySearch(['contractId' => $id]);
        return $this->renderPartial('_expand_order', [
            'dataProvider' => $searchModel->searchExpandBlock(Yii::$app->request->queryParams),
        ]);
    }

    /**
     * @return int
     * @throws Exception
     */
    public function actionGetLastLegalId()
    {
        return (int)LegalEntity::find()->alias('c')
            ->select('max(cast(lk.detail_value as SIGNED)) as maxId')
            ->leftJoin('client_details as lk', ['lk.detail_client_id' => new Expression('c.client_id'),
                'lk.detail_type' => DetailType::TYPE_PERSONAL_ACCOUNT,
                'lk.detail_status' => Detail::STATUS_ACTIVE])
            ->where(['c.client_type' => ClientTypeList::TYPES_FOR_LEGAL])->scalar();
    }

    /**
     * @return string
     * @throws Exception
     */
    public function actionGetNewLegals($id)
    {
        if ($clients = self::GetLegalFromBGB($id)) {
            foreach ($clients as $client) {
                self::LegalImport($client);
            }
            return json_encode($clients);
        }
        return json_encode(['error' => 'no data']);
    }

    /**
     * @return array | false
     * @throws Exception
     */
    public static function GetLegalFromBGB($id)
    {
        $bgbilling = Yii::createObject(Yii::$app->components['bgbill']);
        $client = $bgbilling->getContractSubList($id, true)[0];
        $clients = [];
        if (!empty($client)) {
            $items = $bgbilling->processContractFromBgb($client);
            if (count($items) > 1) {
                foreach ($items as $item) {
                    $clients[] = $item;
                }
            } else {
                $clients[] = $items[0];
            }
        } else {
            return false;
        }
        return $clients;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public static function LegalImport($client)
    {
        $settings = (int)$client['client_type'] === ClientTypeList::TYPE_LEGAL_ENTITIES ? Imports::getNewImportLegalEntityModel() : Imports::getContractOrOrderImportModel();
        $fields = unserialize($settings['import_settings']);
        $details = $client;

        if (!isset($client['client_title'])) {
            if (isset($client['name'])) {
                $details['temporary_title'] = $client['name'];
            } elseif (isset($client['num_contract'])) {
                $details['temporary_title'] = $client['num_contract'];
            }
        } else {
            $details['temporary_title'] = $client['client_title'];
        }
        $details['client_type'] = (int)$client['client_type'];

        $model = null;
        if (!isset($fields['type']) || (isset($fields['type']) && $fields['type'] === 'clients')) {
            $model = Client::saveFromAssoc($details, $fields['fields'], $fields['title'], $fields['unique']);
        }

        if ($model && in_array((int)$model->client_type, [ClientTypeList::TYPE_CONTRACT, ClientTypeList::TYPE_ORDER], true)) {
            ClientDetail::setParentAccount($model->client_id);
        }
        return !($model === null);

    }
    /**
     * @param $id
     * @return string
     * @throws NotFoundHttpException
     * @throws Exception
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);
        $searchModel = new LegalEntitySearch();
        $params = Yii::$app->request->queryParams;

        $details = $model->getClientDetails()
            ->joinWith(['detailType d'])
            ->joinWith(['client cl'])
            ->where(['and',
                ['NOT IN', 'detail_type', $model->filteredAttributes],
                ['NOT IN', 'd.detail_type_type', $model->filteredDocuments],
                ['detail_status' => Detail::STATUS_ACTIVE]
            ])
            ->orderBy(new Expression('-d.sortable DESC'))
            ->asArray()
            ->all();

        $detailsTree = Tree::buildTreeArray($details, 'row_id', 'parent_id', null, 'children');

        $groups = [];
        $tariffs = [];
        $balance = [];
        $details = [];

        array_map(static function ($element) use (&$groups, &$tariffs, &$balance, &$details) {
            switch ($element['detail_type']) {
                case DetailType::TYPE_GROUP:
                    $groups[] = $element;
                    break;
                case DetailType::TYPE_LEGAL_ENTITY_TARIFF:
                    $tariffs[] = $element;
                    break;
                case DetailType::TYPE_ACCOUNT_STATE:
                case DetailType::TYPE_LIMIT:
                    $balance[] = $element;
                    break;
                default:
                    $details[] = $element;
            }
        }, $detailsTree);

        $detailsDataProvider = new ArrayDataProvider([
            'allModels' => $details,
            'pagination' => false
        ]);

        $groupsDataProvider = new ArrayDataProvider([
            'allModels' => $groups,
            'pagination' => false
        ]);

        $tariffsDataProvider = new ArrayDataProvider([
            'allModels' => $tariffs,
            'pagination' => false
        ]);

        $balanceDataProvider = new ArrayDataProvider([
            'allModels' => $balance,
            'pagination' => false
        ]);

        $createService = new ClientService();
        if ($err = Yii::$app->request->get('service_add_error')) {
            $createService->addError('service_id', $err);
        }

        $createIp = new ClientIp();
        if (Yii::$app->request->get('add_ip') && $createIp->load(Yii::$app->request->bodyParams) && $createIp->setClient($id)->validate()) {
            $createIp->apply();
        }

        $createFace = new ClientFace();
        if (Yii::$app->request->get('add_face') && $createFace->load(Yii::$app->request->bodyParams)) {
            $createFace->setClient($id)->apply();
        }

        $services = $model->getServices();

        $devModel = new ClientDevice(['client_id' => $id]);
        $activeDevicesProvider = $devModel->search(Yii::$app->request->bodyParams);
        $historyDevicesProvider = $devModel->searchHistory(Yii::$app->request->bodyParams);

        $documentsDataProvider = $searchModel->searchDocuments($model);

        $renderParams = compact(
            'model',
            'searchModel',
            'detailsDataProvider',
            'groupsDataProvider',
            'tariffsDataProvider',
            'balanceDataProvider',
            'documentsDataProvider',
            'activeDevicesProvider',
            'historyDevicesProvider',
            'services', 'createService', 'createIp', 'createFace'
        );

        switch ($model->client_type) {
            case ClientTypeList::TYPE_LEGAL_ENTITIES:
                $renderParams['dataProvider'] = $searchModel->searchByQuery($model->getContracts(), $params);
                break;
            case ClientTypeList::TYPE_CONTRACT:
                $renderParams['dataProvider'] = $searchModel->searchByQuery($model->getOrders(), $params);
                $renderParams['legalEntityModel'] = $model->legalEntity;
                break;
            case ClientTypeList::TYPE_ORDER:
                $renderParams['contractModel'] = ($model->contract !== null) ? $model->contract : $model->getParentInternal();
                if ($renderParams['contractModel'] !== null) {
                    $renderParams['legalEntityModel'] = isset($model->contract->legalEntity) ? $model->contract->legalEntity : $renderParams['contractModel']->getParentInternal();
                }
                break;
            default:
                throw new NotFoundHttpException('Не опознан тип');
        }

        $documentsTypeList = ClientDetailTypeList::find()
            ->select(['detail_type_descr'])
            ->where(['detail_type_id' => $searchModel->validationDocumentsRange])
            ->indexBy('detail_type_id')
            ->column();

        $renderParams['documentsTypeList'] = $documentsTypeList;
        $renderParams['modelUploadFiles'] = new UploadFiles();
        return $this->render('legal_entity', $renderParams);
    }

    /**
     * @param $id
     * @return Response
     */
    public function actionCreateService($id)
    {
        $model = new ClientService();
        $model->client_id = $id;
        $model->load(Yii::$app->request->bodyParams);
        if ($model->save()) {
            return $this->redirect(['view', 'id' => $id]);
        }
        return $this->redirect(['view', 'id' => $id, 'service_add_error' => current($model->getErrorSummary(false))]);
    }

    /**
     * @param $id
     * @return Response
     */
    public function actionDeleteService($id)
    {
        $model = new ClientService();
        $model->client_id = $id;
        $model->service_id = Yii::$app->request->get('service_id');
        $model->remove();
        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * @param $id
     * @return Response
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function actionDeleteIp($id)
    {
        if ($model = ClientIp::findOne(Yii::$app->request->get('ip'))) {
            $model->clear()->apply();
        }
        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * @param $id
     * @return Response
     */
    public function actionDeleteFace($id)
    {
        if ($model = FaceBase::findOne(Yii::$app->request->get('face_id'))) {
            $model->clearClient()->apply();
        }
        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * @param $id
     * @return bool
     * @throws NotFoundHttpException
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);

        if ($model) {
            $model->client_status = ClientStatusList::STATUS_DELETED;
            return $model->save();
        }

        return false;
    }

    /**
     * Создаем новое юр. лицо
     * @param $message
     * @return string | null
     */
    public function actionCreate($message = null)
    {
        $model = new LegalEntityCreateForm();
        if ($model->load(Yii::$app->request->post())) {
            $multiParams = Yii::$app->request->post('multiParams');
            $result = $model->addNewLegalEntity(isset($multiParams) ? $multiParams : '');
            return isset($result) ? $result : null;
        }
        return $this->render('create_legal_entity', compact('model', 'message'));
    }

    /**
     * @throws NotSupportedException
     */
    public function actionUpdate()
    {
        throw new NotSupportedException('Функция не поддерживается');
    }

    /**
     * @return string
     */
    public function actionDevices()
    {
        $searchModel = new LegalEntitySearch();
        $dataProvider = $searchModel->searchWithDevices(Yii::$app->request->queryParams);

        return $this->render('devices', compact('searchModel', 'dataProvider'));
    }

    /**
     * @return array
     */
    public function actionAttachDevice()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $devId = (int)Yii::$app->request->post('device_id');
        $clientId = (int)Yii::$app->request->post('client_id');
        $deviceClient = DevClient::bind($devId, $clientId)->apply();

        if ($deviceClient->hasErrors()) {
            Yii::$app->response->statusCode = 400;
            return ['message' => $deviceClient->getErrorSummary(true)];
        }
        return [];
    }

    /**
     * @param $id
     * @return array|string[]
     */
    public function actionUpsertDevice($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $err = [
            'output' => 'error',
            'message' => 'Запись не добавлена'
        ];
        $request = Yii::$app->request->bodyParams;
        if (!isset($request['LegalEntitySearch']['deviceId'])) {
            return $err;
        }
        $device = Device::findOne($request['LegalEntitySearch']['deviceId']);
        if ($device === null) {
            return $err;
        }

        $deviceClient = DevClient::bind($device->id, $id);
        if ($deviceClient->save()) {
            return [
                'output' => $device->hostname,
                'message' => '',
                'devId' => $device->id,
                'clientId' => $id,
            ];
        }
        return [
            'output' => 'error',
            'message' => print_r($deviceClient->getFirstErrors(), true)
        ];
    }

    // отменить привязку устройства

    /**
     * @return array|string[]
     */
    public function actionUnBindDevice()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $devId = (int)Yii::$app->request->post('devId');
        $clientId = (int)Yii::$app->request->post('clientId');
        if (DevClient::find()->a()->bind()->device($devId)->client($clientId)->one()->revert()->apply()) {
            return [
                'devId' => $devId,
                'clientId' => $clientId,
                'message' => ''
            ];
        }
        return [
            'output' => 'error',
            'message' => "Ошибка при попытке отменить привязку оборудования!"
        ];
    }

    /**
     * штатно открепить устройство
     * @return array|string[]
     */
    public function actionDetachDevice()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $devId = (int)Yii::$app->request->post('devId');
        $clientId = (int)Yii::$app->request->post('clientId');
        $clearDev = (int)Yii::$app->request->post('clearDev');

        if ($clearDev ? DevClient::release($devId, $clientId)->clear()->apply() : DevClient::release($devId, $clientId)->apply()) {
            return [
                'devId' => $devId,
                'clientId' => $clientId,
                'message' => ''
            ];
        }
        return [
            'output' => 'error',
            'message' => "Ошибка при попытке штатного открепления оборудования!"
        ];
    }

    /**
     * @return string
     */
    public function actionAddress()
    {
        $searchModel = new LegalEntitySearch();
        $dataProvider = $searchModel->searchWithAddress(Yii::$app->request->queryParams);

        return $this->render('address', compact('searchModel', 'dataProvider'));
    }

    /**
     * @return false|string
     */
    public function actionGetFias()
    {
        $model = new BgbParametersForm();
        $model->contractIdCRM = Yii::$app->request->post('elementId');
        $model->addressGUID = " ";
        $model->address = " ";

        $addressGUID = ClientDetail::find()
            ->select('row_id, detail_value, detail_value_cache')
            ->where([
                'detail_client_id' => Yii::$app->request->post('elementId'),
                'detail_type' => DetailType::TYPE_FIAS,
                'detail_status' => Detail::STATUS_ACTIVE,
            ])->asArray()->all();
        if (!empty($addressGUID)) {
            $detail = end($addressGUID);
        }
        $detailId = 0;
        if (isset($detail) && $detail['detail_value'] !== '') {
            $model->addressGUID = $detail['detail_value'];
            $detailId = $detail['row_id'];
        }
        return $this->renderAjax('_address_fias', compact('model', 'detailId'));
    }

    /**
     * @return BgbParametersForm
     * @throws Exception
     */
    public function actionUpdateAddress()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $model = new BgbParametersForm();
        if (!$model->load(Yii::$app->request->post())) {
            throw new Exception('Ошибка получения данных из формы');
        }

        try {
            $detailId = Yii::$app->request->post('detailId');
            ClientDetail::setDetail($model->contractIdCRM, DetailType::TYPE_FIAS, $model->addressGUID, $model->address, $detailId);
        } catch (Exception $e) {
            throw new Exception('Новый адрес не сохранен в базу CRM!');
        }
        return $model;
    }

    /**
     * @return array|bool|string[]
     */
    public function actionUploadFiles()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $legalEntityId = (int)Yii::$app->request->post('legalEntityId');
        $documetsType = (int)Yii::$app->request->post('documentsType');
        if ($legalEntityId === null || $documetsType === null) {
            return [
                'error' => 'Ошибка при загрузке файла.'
            ];
        }

        $model = new UploadFiles();
        if ($model->load(Yii::$app->request->post())) {
            $model->type = (strpos($model->file->type, 'image') !== false) ? UploadFiles::TYPE_IMAGE : UploadFiles::TYPE_FILE;
            $model->file_type = $model->type;
            if ($model->upload() && $model->save(false)) {
                ClientDetail::setDetail($legalEntityId, $documetsType, $model->id, $model->file_path);
                return true;
            }
        }
        return [
            'error' => $model->getFirstError('file')
        ];
    }

    /**
     * @return bool
     * @throws RuntimeException
     */
    public function actionDeleteFiles()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $legalEntityId = (int)Yii::$app->request->post('legalEntityId');
        $detailId = (int)Yii::$app->request->post('detailId');
        $details = ClientDetail::findOne(['row_id' => $detailId]);

        if ($details && $details->detail_client_id === $legalEntityId &&
            in_array($details->detailType->detail_type_type, (new LegalEntity())->filteredDocuments, true) &&
            ClientDetail::deleteDetail($details->row_id) &&
            UploadFiles::removeFileUpload($details->detail_value)) {
            return true;
        }
        throw new RuntimeException('Ошибка при удалении файла.');
    }

    /**
     * Редактирует данные в BgBilling
     * и соответствующую запись в CRM
     * @return array
     * @throws \yii\base\ErrorException
     */
    public function actionUpdateParameters()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $model = new BgbParametersForm();
        if ($model->load(Yii::$app->request->post()) && $model->addressGUID) {
            $model->detailId = (int)Yii::$app->request->post('detailId');
        }

        $id = (int)Yii::$app->request->post('id');
        $client = (int)Yii::$app->request->post('client');

        $clientDetail = ClientDetail::findOne($id);
        if ($clientDetail->detail_client_id === $client) {
            $value = Yii::$app->request->post('parameters');
            //Новые значения параметров передаются в переменной 'parameters', кроме адресов
            // Если редактируется адрес 'parameters' === null, то в переменную value подставляем значение нового адреса из модели
            $value = ($value === null) ? $model->address : $value;
            return $model->editBGBParametersFromDetailsId($value, $clientDetail);
        }
        return $model->responseMessage('Данные не сохранены');
    }

    /**
     * Редактирует данные только в CRM
     * @return array
     */
    public function actionUpdateCrmParameters()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $clientId = (int)Yii::$app->request->post('clientId');
        $detailType = (int)Yii::$app->request->post('detailType');
        $value = Yii::$app->request->post('value');
        $oldValue = Yii::$app->request->post('oldValue');
        $deleteKey = Yii::$app->request->post('delete');
        if ($clientId === null || $detailType === null || $value === null) {
            return ['output' => "error", 'message' => "Ошибка при передаче данных!"];
        }

        //если это адрес - нужно сохранить/отредактировать адрес ФИАС
        if ($detailType === DetailType::TYPE_ADDRESS || $detailType === DetailType::TYPE_LEGAL_ENTITY_ADDRESS) {
            $fiasClientDetail = ClientDetail::getClientDetailByTypeValue($clientId, DetailType::TYPE_FIAS, $oldValue);
            if ($deleteKey === "true") {
                if ($fiasClientDetail === null) {
                    return ['output' => "error", 'message' => "Ошибка при удалении данных!"];
                }
                ClientDetail::deleteDetail($fiasClientDetail->row_id);
            } else {
                $fiasDataArr = Yii::$app->request->post('BgbParametersForm');
                if ($fiasDataArr['address'] === null || $fiasDataArr['addressGUID'] === null) {
                    return ['output' => "error", 'message' => "Ошибка при передаче данных адреса!"];
                }
                $value = $fiasDataArr['address'];
                ClientDetail::setDetail($clientId, DetailType::TYPE_FIAS, $fiasDataArr['addressGUID'], $fiasDataArr['address'], ($fiasClientDetail) ? (int)$fiasClientDetail->row_id : 0);
            }
        }

        $detailTypeType = ClientDetailTypeList::getDetailTypeTypeByType($detailType);
        $oldValue = (strlen($oldValue) > 0) ? ClientDetailTypeListTypeList::saveFormat($detailTypeType, $oldValue, $detailType) : null;
        $oldClientDetail = ($oldValue !== null) ? ClientDetail::getClientDetailByTypeValue($clientId, $detailType, $oldValue) : null;
        if ($deleteKey === "true") {
            if ($oldClientDetail === null) {
                return ['output' => "error", 'message' => "Ошибка при удалении данных!"];
            }
            ClientDetail::deleteDetail($oldClientDetail->row_id);
            return ['output' => "ok", 'message' => ""];
        }
        $detail_value = ClientDetailTypeListTypeList::saveFormat($detailTypeType, $value, $detailType);
        $detail_value_cache = ((int)$detailTypeType === DetailType::TYPE_VIEW_LIST) ? $value : '';
        ClientDetail::setDetail($clientId, $detailType, $detail_value, $detail_value_cache, ($oldClientDetail) ? (int)$oldClientDetail->row_id : 0);

        return ['output' => "ok", 'message' => $detail_value];
    }

    /**
     * @param $id
     * @return array
     * @throws InvalidConfigException
     */
    public function actionSetAssignedManager($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $requestBody = Yii::$app->request->getBodyParams();
        if ($requestBody['hasEditable']) {
            if (isset($requestBody['LegalEntitySearch'], $requestBody['editableIndex'])) {
                $userid = $requestBody['LegalEntitySearch'][$requestBody['editableIndex']][$requestBody['editableAttribute']];
            } else {
                $userid = $requestBody['manager'];
            }
            $user = Users::findOne($userid);
            if (!$user) {
                return ['output' => '', 'message' => 'Пользователь не найден'];
            }
            ClientDetail::setDetail($id, DetailType::TYPE_MANAGER, $userid, $user->getFullName());
            return ['output' => $user->getFullName(), 'message' => ''];
        }
        return ['output' => '', 'message' => ''];
    }

    /**
     * Обновляет список групп Юр. лица в CRM
     * @return array
     */
    public function actionUpdateGroupList()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $idClientCrm = (int)Yii::$app->request->post('idClientCrm');
        if ($idClientCrm === 0) {
            return ['output' => "error", 'message' => "Ошибка при обновлении списка групп!"];
        }
        $selectedGroupList = Yii::$app->request->post('groupList');
        $groupListCrm = ClientDetail::getClientDetailListByType($idClientCrm, DetailType::TYPE_GROUP);

        foreach ($groupListCrm as $item) {
            if ($selectedGroupList === null || !in_array($item->detail_value, $selectedGroupList)) {
                $item->detail_status = ClientDetail::STATUS_DELETED;
                $item->save();
            } else {
                unset($selectedGroupList[array_search($item->detail_value, $selectedGroupList)]);
            }
        }
        if ($selectedGroupList) {
            foreach ($selectedGroupList as $item) {
                ClientDetail::setDetail($idClientCrm, DetailType::TYPE_GROUP, $item);
            }
        }

        return ['output' => "ok", 'message' => ""];
    }

    /**
     * @return string
     */
    public function actionFinance()
    {
        $model = new LegalEntitySearch();
        $dataProvider = $model->searchFinance(Yii::$app->request->queryParams);

        return $this->render('finance', compact('model', 'dataProvider'));
    }

    /**
     * @return string
     */
    public function actionPayments()
    {
        $searchModel = new BgbDbContractSearch();
        $dataProvider = $searchModel->searchPayments(Yii::$app->request->queryParams);

        return $this->render('payments', compact('searchModel', 'dataProvider'));
    }


    /**
     * @return string
     */
    public function actionBilling()
    {
        if (!Yii::$app->request->isAjax) {
            return $this->render('billing');
        }

        $clientId = (int)Yii::$app->request->post('clientId');
        $client = LegalEntity::findOne($clientId);
        if (!isset($client)) {
            return "<h4>Клиент с ИД: " . $clientId . " не найден!</h4>";
        }
        if ($client->client_type === ClientTypeList::TYPE_LEGAL_ENTITIES) {
            return "<h4>Клиент с ИД: " . $clientId . " не является  Договором / Заказом</h4>";
        }
        $le = ($client->client_type === ClientTypeList::TYPE_ORDER) ? $client->parent->parent : $client->parent;
        if (!isset($le)) {
            return "<h4>Нет данных о Юридическом лице!</h4>";
        }

        $renderParams['modelBillForm'] = new BillForm();
        $renderParams['modelInvoiceForm'] = new InvoiceForm();
        $renderParams['client'] = $client;
        $renderParams['le'] = $le;

        return $this->renderAjax('_billing_form', $renderParams);
    }

    public function actionChangeLimit()
    {
        $id = Yii::$app->request->post('id');
        $rate = Yii::$app->request->post('rate');
        $face = FaceBase::findActive()->id($id)->one();
        Yii::$app->response->statusCode = 400;
        if (!$face) {
            return "Интерфейс {$id} не найден";
        }
        $queue = SetSimpleQueue::add([
            'target_id' => $face->device_id,
        ], [], $rate, $face->name, $face->id);
        if ($queue->hasErrors()) {
            return current($queue->getErrorSummary(true));
        }
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->statusCode = 200;
        return ['queue_id' => $queue->id];
    }

    public function actionChangeLimitStatus($id)
    {
        if ($q = SetSimpleQueue::findOne($id)) {
            if ($q->error) {
                Yii::$app->response->statusCode = 400;
                return $q->getError()->getDesc();
            }
            Yii::$app->response->format = Response::FORMAT_JSON;
            return [
                'finished' => $q->status === SetSimpleQueue::STATUS_FAILED || $q->status === SetSimpleQueue::STATUS_FINISHED ? '1' : '',
                'rate' => $q->data,
            ];
        }
        Yii::$app->response->statusCode = 400;
        return "Не найден {$id}";
    }
}