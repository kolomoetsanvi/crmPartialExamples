<?php

namespace common\components\billings;

use common\models\client\ClientDetail;
use common\models\client\ClientDetailTypeList;
use common\models\client\ClientTypeList;
use common\models\details\DetailType;
use common\models\import\Imports;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use HttpResponseException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use SimpleXMLElement;
use Yii;
use yii\base\Component;
use yii\base\ErrorException;
use yii\helpers\ArrayHelper;
use yii\i18n\Formatter;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;





/**
 * Class BGBilling
 *
 * @property string $url
 * @property string $login
 * @property string $password
 */
class Billing extends Component
{
    const GET_METHOD = 'GET';
    const POST_METHOD = 'POST';
    const PID_TITLE = 1;
    const PID_DEVIP = 2;
    const PID_CONNECT_DATE = 3;
    const PID_CONTACT = 4;
    const PID_EMAIL = 6;
    const PID_ENTITY_ADDRESS = 7;
    const PID_INVOICE_DELIVERY_METHOD = 8;
    const PID_INN = 9;
    const PID_PHONE = 10;
    const PID_ADDITIONAL_PARAM = 11;
    const PID_ADDRESS = 12;
    const PID_CONTROLE_DATE = 13;
    const PID_PARAM_COMMENT = 14;
    const PID_CLIENT_CONTRACT_ID = 15;
    const PID_SERVICES = 18;

    const MODUL_ALL_MODULS = -1;
    const MODUL_CARD = 1;
    const MODUL_INTERNET_TRAFFIC = 2;
    const MODUL_SUBSCRIPTION = 3;
    const MODUL_OTHER_SERVICES = 4;
    const MODUL_TELEPHONY = 5;
    const MODUL_REPORT = 6;
    const MODUL_ACCOUNTING = 7;
    const MODUL_DBA = 8;

    const SUB_MODE_ALL = -1;
    const SUB_MODE_DEPENDET = 0;   //зависимый баланс
    const SUB_MODE_INDEPENDET = 1; //не зависимый баланс

    const MODE_DEBIT = 'debet';
    const MODE_CREDIT = 'credit';

    const ENTITY_KEY_PHISICAL = 0;
    const ENTITY_KEY_LEGAL = 1;

    /** тип списка - список доступных для подключения объектов (услуг) */
    const TYPE_LIST_AVALIABLE = "avaliable";
    /** тип списка - список подключенных объектов (услуг) */
    const TYPE_LIST_SELECT = "select";

    const TYPE_GROUP_LIST_EDITABLE = "editable";
    /** Счета */
    const TYPE_ACCOUNTING_LIST_BIll = "bill";
    /** Счета-Фактуры, АКТы, УПД */
    const TYPE_ACCOUNTING_LIST_INVOICE = "invoice";

    /** запрашивает список допустимых значений параметра */
    const TYPE_ACTION_PARAMS_VALUES = "GetListParam";
    /** запрашивает список Шаблонов договоров */
    const TYPE_ACTION_PATTERNS = "GetPatternList";
    /** запрашивает список доступных Модулей */
    const TYPE_ACTION_MODULES_LIST = "ContractModuleList";
    /** запрашивает список доступных Групп (назначенных + не назначенных) */
    const TYPE_ACTION_CONTRACT_GROUP = "ContractGroup";
    /** запрашивает список доступных услуг (в модуле) */
    const TYPE_ACTION_SERVICE_LIST = "GetServiceList";
    /** запрашивает список Счетов-Фактур, АКТов, УПД. Модуль "Бухгалтерия" */
    const TYPE_ACTION_DOCTYPE_LIST = "DocTypeList";
    /** запрашивает список тарифов, доступных для заданного модуля */
    const TYPE_ACTION_TARIFF_PLAN_LIST = "ContractTariffPlan";
    /** запрашивает данные договора */
    const TYPE_ACTION_CONTRACT_INFO_LIST = "ContractInfo";

    const TYPE_ACTION_MODULES_ADD = "ContractModuleAdd";
    const TYPE_ACTION_MODULES_DELETE = "ContractModuleDelete";
    const TYPE_ACTION_CONTRACT_GROUP_ADD = "contractGroupAdd";
    const TYPE_ACTION_CONTRACT_GROUP_REMOVE = "contractGroupRemove";
    const TYPE_ACTION_UPDATE_LIMIT = "updateContractLimit";
    const TYPE_ACTION_SERVICE_TABLE = "ServiceObjectTable";
    const TYPE_ACTION_SERVICE_UPDATE = "ServiceObjectUpdate";
    const TYPE_ACTION_SERVICE_DELETE = "ServiceObjectDelete";
    const TYPE_ACTION_CONTRACT_DOCTYPE_ADD = "ContractDocTypeAdd";
    const TYPE_ACTION_CONTRACT_DOCTYPE_DELETE = "ContractDocTypeDelete";
    const TYPE_ACTION_OTHER_SERVICE_UPDATE = "updateRSCMContractService";
    const TYPE_ACTION_OTHER_SERVICE_DELETE = "deleteRSCMContractService";
    const TYPE_ACTION_CONTRACT_TARIFF_PLAN_UPDATE = "UpdateContractTariffPlan";
    const TYPE_ACTION_CONTRACT_TARIFF_PLAN_DELETE = "DeleteContractTariffPlan";
    const TYPE_ACTION_TARIFF_PLAN_ADD = "AddTariffPlan";
    const TYPE_ACTION_TARIFF_PLAN_GET = "ContractTariffPlans";
    const TYPE_ACTION_TARIFF_PLAN_UPDATE = "UpdateTariffPlan";
    const TYPE_ACTION_TARIFF_PLAN_DELETE = "DeleteTariffPlan";
    const TYPE_ACTION_CONTRACT_SUB_LIST = "contractSubList";


    /** @var Client $httpClient */
    private $httpClient;
    public $url;
    public $login;
    public $password;

    /** @var array */
    public $statuses;

    public $pids = [
        1 => 'title',
        2 => 'devip',
        3 => 'connect_date',
        4 => 'contact',
        6 => 'email',
        7 => 'entity_address',
        8 => 'invoice_delivery_method',
        9 => 'inn',
        10 => 'phone',
        11 => 'additional_param',
        12 => 'address',
        13 => 'control_date',
        14 => 'param_comment',
        15 => 'client_contract_id',
        18 => 'services',
    ];

    public $mids = [
        1 => 'Карточки',
        2 => 'Интернет по трафику',
        3 => 'Абонплаты',
        4 => 'Прочие услуги',
        5 => 'Телефония',
        6 => 'Отчеты',
        7 => 'Бухгалтерия ',
        8 => 'DBA',
    ];

    /**
     * {@inheritDoc}
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->httpClient = new Client([
            'base_uri' => $this->url,
            'cookies' => true,
            'verify' => false,
            'connect_timeout' => 5,
            'timeout' => 10,
        ]);
    }

    /**
     * @return string
     */
    public function getNewUrl()
    {
        return $this->url . '*'; // конфиденциальная информация
    }

    /**
     * @return string
     */
    public function getContractServiceUrl()
    {
        return 'bgbilling/executer/json/ru.bitel.bgbilling.kernel.contract.api/ContractService';
    }

    /**
     * @return string
     */
    public function getContractLimitServiceUrl()
    {
        return 'bgbilling/executer/json/ru.bitel.bgbilling.kernel.contract.limit/ContractLimitService';
    }

    /**
     * @return string
     */
    public function getContractOtherServiceListUrl()
    {
        return 'bgbilling/executer/json/ru.bitel.bgbilling.modules.rscm/4/RSCMService';
    }

    /**
     * @return string
     */
    public function getTariffServiceUrl()
    {
        return 'bgbilling/executer/json/ru.bitel.bgbilling.kernel.tariff/TariffService';
    }


    /**
     * @param $method
     * @param $url
     * @param array $params
     * @return string
     * @throws NotFoundHttpException
     */
    private function doRequest($method, $url, $params = [])
    {
        Yii::info(
            '[BGBilling] Попытка совершения запроса: ' . print_r(['method' => $method, 'URL' => $url, 'params' => $params], true),
            'external-system'
        );

        switch ($method) {
            case self::GET_METHOD:
                $response = $this->doGetRequest($url, $params);
                break;
            case self::POST_METHOD:
                $response = $this->doPostRequest($url, $params);
                break;
            default:
                throw new NotFoundHttpException('Неправильно указан HTTP метод.');
        }

        /** @var ResponseInterface $response */
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException($response->getReasonPhrase());
        }

        $responseText = $response->getBody()->getContents();

        Yii::info('[BGBilling] Ответ: ' . $responseText, 'external-system');

        return $responseText;
    }

    /**
     * @param $url
     * @param array $params
     * @return ResponseInterface
     */
    private function doGetRequest($url, $params = [])
    {
        return $this->httpClient->get($url, $params);
    }

    /**
     * @param $url
     * @param $params
     * @return ResponseInterface
     */
    private function doPostRequest($url, $params)
    {
        return $this->httpClient->post($url, $params);
    }

    /**
     * @link https://docs.bitel.ru/pages/viewpage.action?pageId=119505351
     *
     * @param array $params
     * @return mixed
     * @throws NotFoundHttpException
     * @throws HttpResponseException|BadRequestHttpException
     */
    public function getClients($params = [])
    {
        $requestParams = ArrayHelper::merge(
            [
                'method' => 'contractList',
                'user' => [
                    'user' => $this->login,
                    'pswd' => $this->password,
                ],
                'params' => [
                    'title' => '.*',
                    'fc' => -1,
                    'groupMask' => 0,
                    'subContracts' => true,
                    'closed' => false,
                    'hidden' => true,
                    'page' => [
                        'pageIndex' => 1,
                        'pageSize' => 2500
                    ]
                ]
            ],
            $params
        );

        $CACHE_KEY = $requestParams['params']['page']['pageIndex'] . '_page_bgbilling_client_loader';

        $data = $this->doRequest(
            'POST',
            'bgbilling/executer/json/ru.bitel.bgbilling.kernel.contract.api/ContractService',
            [RequestOptions::JSON => $requestParams]
        );

        $clients = [];
        $result = json_decode($data);

        if ($result->status !== 'ok') {
            throw new BadRequestHttpException(implode(' ', [$result->status, $result->exception, $result->message]));
        }

        if (md5($data) === Yii::$app->cache->get($CACHE_KEY)) {
            // если совпадают хеши текущего ответа и от предыдущей синхронизации
            if ($result->data->page->pageIndex < $result->data->page->pageCount) {
                // то, при наличии следующих страниц, попытаться найти изменения в них
                $requestParams['params']['page']['pageIndex'] = $result->data->page->pageIndex + 1;
                $clients = array_merge($clients, $this->getClients($requestParams));
            }
        } else {
            foreach ($result->data->return as $contract) {
                $items = $this->processContractFromBgb($contract);
                if (count($items) > 1) {//создается сразу юр.лицо и договор к нему
                    foreach ($items as $item) {
                        $clients[] = $item;
                    }
                } else {
                    $clients[] = $items[0];
                }
            }

            Yii::$app->cache->set($CACHE_KEY, md5($data), 2592000 /*месяц*/);

            if ($result->data->page->pageIndex < $result->data->page->pageCount) {
                $requestParams['params']['page']['pageIndex'] = $result->data->page->pageIndex + 1;
                $clients = array_merge($clients, $this->getClients($requestParams));
            }
        }

        return $clients;
    }

    /**
     * Раскладывает и дополняет контракт до готового для импорта массива клиента
     * @param array $contract
     * @return array
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function processContractFromBgb($contract)
    {
        $contractParams = $this->getContractParams($contract->id);
        $contractInfo = $this->getContractInfo($contract->id);
        $tariffs = $this->getTariffs($contract->id);
        $clients = [];

        if ($contract->super || (!$contract->super && !$contract->sub)) {
            //это договор, из него сохраняется юридическое лицо и договор
            $client = [
                'client_type' => ClientTypeList::TYPE_LEGAL_ENTITIES,
                'sublist' => $contract->id,
            ];

            $this->addClientParams($client, $contractParams);
            $clients[] = $client;

            $newContract = [
                'client_type' => ClientTypeList::TYPE_CONTRACT,
                'id' => $contract->id,
                'name' => $contract->title,
                'start_date' => $contract->dateFrom,
                'contract_number' => $contract->title,
                'stop_date' => $contract->dateTo,
                'enable_date' => $contract->statusTimeChange,
                'comment' => $contract->comment,
                'balance' => $contractInfo['balance'],
                'status' => $contractInfo['status'],
                'limit' => $contractInfo['limit'],
                'tariffs' => count($tariffs) ? $tariffs : null,
                'groups' => isset($contractInfo['groups']) ? implode(';', $contractInfo['groups']) : null,
            ];

            $this->addParams($newContract, $contractParams);
            $clients[] = $newContract;
        } else {
            // это заказ, из него только заказ
            $newOrder = [
                'client_type' => ClientTypeList::TYPE_ORDER,
                'id' => $contract->id,
                'parent' => $contract->superCid,
                'name' => $contract->title,
                'start_date' => $contract->dateFrom,
                'contract_number' => $contract->title,
                'stop_date' => $contract->dateTo,
                'enable_date' => $contract->statusTimeChange,
                'comment' => $contract->comment,
                'tariffs' => count($tariffs) ? $tariffs : null,
                'groups' => isset($contractInfo['groups']) ? implode(';', $contractInfo['groups']) : null,
                'balance' => $contractInfo['balance'],
                'status' => $contractInfo['status'],
                'limit' => $contractInfo['limit'],
            ];

            $this->addParams($newOrder, $contractParams);
            $clients[] = $newOrder;
        }
        return $clients;
    }
    /**
     * @param $contractId
     * @return array
     * @throws NotFoundHttpException|BadRequestHttpException
     */
    public function getContractParams($contractId)
    {
        $response = $this->doRequest(
            'GET',
            'bgbilling/executer?' . http_build_query([
                'user' => $this->login,
                'pswd' => $this->password,
                'module' => 'contract',
                'action' => 'ContractParameters',
                'cid' => $contractId
            ])
        );

        $data = new SimpleXMLElement($response);

        $parameters = [];

        if ((string)$data['status'] !== 'ok') {
            throw new BadRequestHttpException(implode(' ', [$data['status'], $data['exception'], $data['message']]));
        }

        foreach ($data->parameters->parameter as $parameter) {
            $value = (string)$parameter['value'];

            if (isset($value) && $value === '') {
                continue;
            }

            if (!isset($this->pids[(int)$parameter['pid']])) {
                continue;
            }

            $parameters[$this->pids[(int)$parameter['pid']]] = $value;
        }
        return $parameters;
    }

    /**
     * @param $contractId
     * @return array
     * @throws NotFoundHttpException
     */
    public function getContractInfo($contractId)
    {
        $response = $this->doRequest(
            'GET',
            'bgbilling/executer?' . http_build_query([
                'user' => $this->login,
                'pswd' => $this->password,
                'module' => 'contract',
                'action' => 'ContractInfo',
                'cid' => $contractId
            ])
        );

        $data = new SimpleXMLElement($response);

        $info = [];

        if ((string)$data['status'] === 'ok') {
            foreach ($data->info->groups->item as $group) {
                $info['groups'][(int)$group['id']] = (string)$group['title'];
            }
        }

        $info['status'] = (string)$data->contract['status'];
        $info['limit'] = (string)$data->contract['limit'];

        $info['balance'] = [
            'key' => 'balance',
            'value' => null,
            'children' => [
                'incoming_balance' => (string)$data->info->balance['summa1'],
                'income' => (string)$data->info->balance['summa2'],
                'operating_sum' => (string)$data->info->balance['summa3'],
                'outcome' => (string)$data->info->balance['summa4'],
                'outgoing_balance' => (string)$data->info->balance['summa5'],
                'available_amount' => (string)$data->info->balance['summa6'],
                'reserve' => (string)$data->info->balance['summa7'],
            ]
        ];

        return $info;
    }

    /**
     * @param $contractId
     * @return array
     * @throws NotFoundHttpException
     */
    public function getTariffs($contractId)
    {
        $response = $this->doRequest(
            'GET',
            'bgbilling/executer?' . http_build_query([
                'user' => $this->login,
                'pswd' => $this->password,
                'module' => 'contract',
                'action' => 'ContractTariffPlans',
                'cid' => $contractId
            ])
        );

        $data = new SimpleXMLElement($response);

        $info = [];

        if ((string)$data['status'] === 'ok') {
            foreach ($data->table->data->row as $tariff) {
                $info[] = [
                    'key' => 'tariffs',
                    'value' => (string)$tariff['title'],
                    'children' => [
                        'tariff_date_start' => (string)$tariff['date1'],
                        'tariff_date_end' => (string)$tariff['date2'],
                        'comment' => (string)$tariff['comment'],
                    ],
                ];
            }
        }

        return $info;
    }

    /**
     * @param array $contract
     * @param array $contractParams
     */
    private function addParams(array &$contract, array $contractParams)
    {
        foreach ($this->pids as $param) {
            if (isset($contractParams[$param])) {
                $contract[$param] = $contractParams[$param];
            }
        }
    }

    /**
     * @param array $contract
     * @param array $contractParams
     */
    private function addClientParams(array &$contract, array $contractParams)
    {
        $paramsList = [
            'inn' => 'inn',
            'name' => 'title',
            'entity_address' => 'entity_address'
        ];
        foreach ($paramsList as $key => $param) {
            if (isset($contractParams[$param])) {
                $contract[$key] = $contractParams[$param];
            }
        }
    }

    /**
     * Добавляет данные юридического лица в базу данных BgBilling
     * Используется для добавления данные договора
     * путем отправки HTTP запроса
     * Данные привязываются к определенному договору по номеру $cid
     * полученному от BgBilling
     * @param $pid
     * @param $cid
     * @param $value
     * @param $additionalParams //не обязательные параметры запроса
     * @return array
     * @throws ErrorException
     * @property string $updateParameterType
     */
    public function setClientParams($cid, $pid, $value, $additionalParams = [])
    {
        $params = $this->clientRequestParams($cid, $pid, $value, $additionalParams);

        $response = $this->doRequest(self::POST_METHOD, $this->getNewUrl(), ['query' => $params]);
        $this->checkError($response);

        $parameters['status'] = 'ok';
        return $parameters;
    }


    /**
     * Функция формирует список параметров для отправки запроса в BgBilling
     * в виде массива ключ => значение
     * @param $pid
     * @param $cid
     * @param $value
     * @param $additionalParams
     * @return array
     * @throws Exception
     */
    private function clientRequestParams($cid, $pid, $value, $additionalParams)
    {
        $updateParameterType = 'UpdateParameterType1';
        switch ($pid) {
            case self::PID_CONNECT_DATE:
            case self::PID_CONTROLE_DATE:
                $updateParameterType = 'UpdateParameterType6';
                break;
            case self::PID_EMAIL:
                $updateParameterType = 'UpdateEmailInfo';
                break;
            case self::PID_INVOICE_DELIVERY_METHOD:
                $updateParameterType = 'UpdateListParam';
                break;
            case self::PID_ADDRESS:
            case self::PID_ENTITY_ADDRESS:
                $updateParameterType = 'AddAddressCustom';
                break;
        }

        $params = ['user' => $this->login,
            'pswd' => $this->password,
            'BGBillingSecret' => Yii::$app->security->generatePasswordHash('BGBillingSecret'),
            'module' => 'contract',
            'action' => $updateParameterType,
            'pid' => $pid,
            'id' => $cid, // используется не в каждом запросе
            'value' => $value,
            'cid' => $cid,
        ];

        if (!empty($additionalParams)) {
            $params = array_merge($params, $additionalParams);
        }

        return $params;
    }


    /**
     * Функция добавляет/редактирует данные юридического лица в базе данных BgBilling
     * выбор параметра pid - по типу изменяемого атрибута в таблице CRM {client_details}
     * Данные привязываются к определенному договору по номеру $cid, полученному от BgBilling
     * @param $cid
     * @param $clientDetail
     * @param $value
     * @return string | null
     * @throws ErrorException
     */
    public function setClientParamsByDetailType($cid, $clientDetail, $value)
    {
        $pid = $this->getParamsPidFromDetailType($clientDetail->detail_type);
        switch ($pid) {
            case self::PID_PHONE:
                $valueToBgb = $this->getEditParametersValueListToString($clientDetail, $value);
                $additionalParams = null;
                break;
            case self::PID_EMAIL:
                $valueListString = $this->getEditParametersValueListToString($clientDetail, $value);
                $valueToBgb = '';
                $additionalParams = ['eid' => 0, 'buf' => '', 'e-mail' => ' ' . $valueListString];
                break;
            case self::PID_ENTITY_ADDRESS:
            case self::PID_ADDRESS:
                $valueToBgb = '';
                $additionalParams = ['address' => $value];
                break;
            case self::PID_CONNECT_DATE:
            case self::PID_CONTROLE_DATE:
                $formatter = new Formatter();
                $valueToBgb = $formatter->asDate($value, "dd.MM.yyyy");
                $additionalParams = null;
                break;
            default:
                $valueToBgb = $value;
                $additionalParams = null;
                break;
        }

        $typeType = ClientDetailTypeList::findOne($clientDetail->detail_type);
        if (!$typeType) {
            throw new ErrorException('Тип данных не установлен и не может быть добавлен');
        }
        if ($typeType->detail_type_type == DetailType::TYPE_VIEW_LIST) {
            $valueList = $this->getListValue(self::TYPE_ACTION_PARAMS_VALUES, $cid, ["pid" => $pid]);
            $valueToBgb = array_keys($valueList, $value)[0];
        }
        // непосредственно, устанавливаем новое значение параметра
        $result = $this->setClientParams($cid, $pid, $valueToBgb, $additionalParams);
        if ($result['status'] !== 'ok') {
            return false;
        }
        // получаем обновленные значения параметра из BGBilling
        $contractParameters = $this->getContractParams($cid);
        if (!$contractParameters) {
            return false;
        }
        /* если установлено пустое значение параметра, он не выгрузится в список $contractParameters.
        Тогда возвращаем null */
        if (!isset($contractParameters[$this->pids[$pid]])) {
            return null;
        }
        return (strpos($contractParameters[$this->pids[$pid]], $value) !== false) ? $value : false;
    }


    /**
     * Функция получает на вход параметр из CRM и его новое значение
     * т.к. данный параметр может хранится в BGBilling в множественном числе
     * получаем из таблице CRM {client_details} все значения параметра с данным типом
     * редактируем необходимое значение и возвращаем список в виде строки
     * для передачи в BGBilling
     * @param $clientDetail // редактируемый параметр
     * @param $value // новое значение редактируемого параметра
     * @return string
     */
    public function getEditParametersValueListToString($clientDetail, $value)
    {
        $valueList = ClientDetail::getDetailValueListByType($clientDetail->detail_client_id, $clientDetail->detail_type);
        $resultString = '';
        foreach ($valueList as $key => $itemValue) {
            switch ($clientDetail->detail_type) {
                case DetailType::TYPE_EMAIL:
                    $resultString .= (($key) ? "\n " : '');
                    break;
                default:
                    $resultString .= (($key) ? ', ' : '');
                    break;
            }
            $resultString .= ((strcmp($itemValue, $clientDetail->detail_value) === 0) ? $value : $itemValue);
        }
        return $resultString;
    }


    /**
     * Возвращает список идентификаторов типов атрибутов
     * в базе  CRM ({client_detail_type_list})
     * соответствующих параметрам договора в BgBilling
     * Получаем настройки импорта (содержат идентификаторы типов)
     * Сопоставляем настройки с перечнем параметров договора BgBilling ($this->pids)
     * @return array
     */
    public function getParamsDetailTypeList()
    {
        $importLe = unserialize(Imports::getNewImportLegalEntityModel()->import_settings);
        $importContractOrOrder = unserialize(Imports::getContractOrOrderImportModel()->import_settings);
        $import = array_merge($importLe['fields'], $importContractOrOrder['fields']);
        $detailTypeList = [];
        foreach ($this->pids as $pid) {
            foreach ($import as $key => $value) {
                if (strcasecmp($pid, $key) == 0) {
                    $detailTypeList[] = $value;
                }
            }
        }
        return $detailTypeList;
    }


    /**
     * Функция возвращает идентификатор параметра в BgBilling
     * по типу атрибута в CRM
     * @param $typeId
     * @return integer
     */
    public function getParamsPidFromDetailType($typeId)
    {
        $importLe = unserialize(Imports::getNewImportLegalEntityModel()->import_settings);
        $importContractOrOrder = unserialize(Imports::getContractOrOrderImportModel()->import_settings);
        $import = array_merge($importLe['fields'], $importContractOrOrder['fields']);
        $importKey = array_keys($import, $typeId);
        $pid = array_keys($this->pids, $importKey[0]);
        return $pid[0];
    }


    /**
     * Функция создает новый договор в BgBilling
     * Устанавливает статую Лицо : Юридическое. Добавляет название договора и комментарий
     * Возвращает id договора в базе BgBilling
     * @param integer $pattern_id //шаблон договора
     * @param string $date
     * @param integer $sub_mode
     * @throws ErrorException
     * @return integer
     */
    public function createContract($pattern_id, $date, $sub_mode = 0)
    {
        $params = ['user' => $this->login,
            'pswd' => $this->password,
            'BGBillingSecret' => Yii::$app->security->generatePasswordHash('BGBillingSecret'),
            'date' => $date,
            'pattern_id' => $pattern_id,
            'module' => 'contract',
            'action' => 'NewContract',
            'sub_mode' => $sub_mode,
            'params' => '',
        ];

        $response = $this->doRequest(self::POST_METHOD, $this->getNewUrl(), ['query' => $params]);
        $this->checkError($response, 'Договор не создан');

        $data = new SimpleXMLElement($response);
        return (string)$data->contract['id'];
    }

    /**
     * @return array
     * @throws ErrorException
     */
    public function deleteContract($contractIdBgB)
    {
        $addParams = ['action' => 'DeleteContract',
            'save' => 1,
        ];
        return $this->setClientParams($contractIdBgB, '', '', $addParams);
    }


    /**
     * @param string $response
     * @param string $message
     * @return bool
     * @throws ErrorException
     */
    public function checkError($response, $message = '')
    {
        $data = new SimpleXMLElement($response);
        if ((string)$data['status'] !== 'ok') {
            throw new ErrorException($message . (string)$data);
        }
        return true;
    }


    /**
     * @param string $response
     * @param string $message
     * @return bool
     * @throws ErrorException
     */
    public function checkErrorJson($response, $message = '')
    {
        if (json_decode($response)->status !== 'ok') {
            throw new ErrorException($message . ' ' . json_decode($response)->message);
        }
        return true;
    }


    /**
     * Универсальная функция
     * Возвращает из BgBilling списочные значения по типу списка
     * @param string $action
     * @param integer $cid
     * @param array $additionalParams
     * @return array
     * @throws ErrorException
     * @throws NotFoundHttpException
     */
    public function getListValue($action, $cid = '', $additionalParams = [])
    {
        $params = array_merge([
            'user' => $this->login,
            'pswd' => $this->password,
            'BGBillingSecret' => Yii::$app->security->generatePasswordHash('BGBillingSecret'),
            'module' => 'contract',
            'action' => $action,
            'cid' => $cid
        ], $additionalParams);

        $response = $this->doRequest(self::POST_METHOD, $this->getNewUrl(), ['query' => $params]);
        $this->checkError($response);

        $data = new SimpleXMLElement($response);
        $valueList = [];
        $listItem = [];
        switch ($action) {
            case self::TYPE_ACTION_PARAMS_VALUES:
                $listItem = $data->values->item;
                break;
            case self::TYPE_ACTION_PATTERNS:
                $listItem = $data->patterns->item;
                break;
            case self::TYPE_ACTION_MODULES_LIST:
            case self::TYPE_ACTION_DOCTYPE_LIST:
                if ($params['typeList'] == self::TYPE_LIST_AVALIABLE) {
                    $listItem = $data->list_avaliable->item;
                }
                if ($params['typeList'] == self::TYPE_LIST_SELECT) {
                    $listItem = $data->list_select->item;
                }
                break;
            case self::TYPE_ACTION_CONTRACT_GROUP:
                $listItem = $data->groups->group;
                break;
            case self::TYPE_ACTION_SERVICE_LIST:
                $listItem = $data->services->service;
                break;
            case self::TYPE_ACTION_TARIFF_PLAN_LIST:
                $listItem = $data->tariffPlans->item;
                break;
            case self::TYPE_ACTION_CONTRACT_INFO_LIST:
                return $data->contract;
                break;
        }

        foreach ($listItem as $parameter) {
            $id = (string)$parameter['id'];
            $title = (string)$parameter['title'];
            $valueList[$id] = $title;
        }
        return $valueList;
    }


    /**
     * Возвращает список шаблонов договоров из BgBilling
     * @return array
     */
    public function getPatternList()
    {
        return $this->getListValue(self::TYPE_ACTION_PATTERNS);
    }


    /**
     * Возвращает ПОЛНЫЙ список доступных групп из BgBilling
     * @param integer $cid
     * @return array
     */
    public function getGroupsList($cid)
    {
        return $this->getListValue(self::TYPE_ACTION_CONTRACT_GROUP, $cid);
    }


    /**
     * Возвращает список модулей из BgBilling, доступных для подключения клиента
     * @param integer $cid
     * @return array
     */
    public function getAvaliableModulsList($cid)
    {
        return $this->getListValue(
            self::TYPE_ACTION_MODULES_LIST, $cid, ["typeList" => self::TYPE_LIST_AVALIABLE]
        );
    }


    /**
     * Возвращает список модулей из BgBilling, установленных у клиента
     * @param integer $cid
     * @return array
     */
    public function getSelectedModulsList($cid)
    {
        return $this->getListValue(
            self::TYPE_ACTION_MODULES_LIST, $cid, ["typeList" => self::TYPE_LIST_SELECT]
        );
    }


    /**
     * Возвращает список услуг, подключаемых в модуле $mid
     * @param integer $mid
     * @return array
     */
    public function getServiceList($mid)
    {
        return $this->getListValue(
            self::TYPE_ACTION_SERVICE_LIST, "", ['module' => 'service', 'mid' => $mid]
        );
    }

    /**
     * Возвращает список тарифов доступных в модуле
     * @param integer $mid
     * @param integer $cid
     * @param integer $showUsed
     * @param integer $tariffGroupFilter
     * @param integer $useFilter
     * @return array
     */
    public function getTariffPlanList($mid, $cid, $showUsed = 1, $tariffGroupFilter = 0, $useFilter = 1)
    {
        return $this->getListValue(
            self::TYPE_ACTION_TARIFF_PLAN_LIST,
            $cid,
            [
                'module' => 'contract',
                'mid' => $mid,
                'showUsed' => $showUsed,
                'tariffGroupFilter' => $tariffGroupFilter,
                'useFilter' => $useFilter
            ]
        );
    }


    /**
     * Модуль "Бухгалтерия"
     * Возвращает список документов
     * Счетов. Если: $type = self::TYPE_ACCOUNTING_LIST_BIll
     * Счетов-Фактур, АКТов, УПД. Если: $type = self::TYPE_ACCOUNTING_LIST_INVOICE
     * $typeList = self::TYPE_LIST_AVALIABLE - документы доступные для подключения
     * $typeList = self::TYPE_LIST_SELECT - подключенные документы
     * @param integer $cid
     * @param integer $mid
     * @param string $type
     * @param string $typeList
     * @return array
     */
    public function getAccountingDocumentsList($cid, $mid, $type, $typeList = self::TYPE_LIST_AVALIABLE)
    {
        return $this->getListValue(
            self::TYPE_ACTION_DOCTYPE_LIST, $cid, ['module' => 'bill', 'mid' => $mid, 'type' => $type, "typeList" => $typeList]
        );
    }


    /**
     * Отправляет запросы в BgBilling
     * Использует Web-сервисы BgBilling
     * @param string $method
     * @param string $url
     * @param array $params
     * @param bool $return
     * @return bool | string
     * @throws ErrorException
     */
    public function doServiceRequest($method, $url, $params = [], $return = false)
    {
        $requestParams = [
            'method' => $method,
            'user' => [
                'user' => $this->login,
                'pswd' => $this->password,
            ],
            'params' => $params
        ];

        $response = $this->doRequest(self::POST_METHOD, $url, [RequestOptions::JSON => $requestParams]);
        $this->checkErrorJson($response);

        return ($return) ? $response : true;
    }


    /**
     * Подключает клиента к модулю(ям) из массива $mid
     * @param integer $cid
     * @param аrray $mid
     * @return bool | void
     * @throws ErrorException
     */
    public function setModul($cid, $mid)
    {
        $addParams = [
            'module_ids' => implode(", ", $mid),
            'action' => self::TYPE_ACTION_MODULES_ADD,
        ];
        if ($this->setClientParams($cid, '', '', $addParams)) {
            return true;
        }
    }


    /**
     * Удаляет модуль из списка установленных
     * @param integer $cid
     * @param integer $mid
     * @return bool | void
     * @throws ErrorException
     */
    public function unsetModul($cid, $mid)
    {
        $addParams = [
            'module_id' => $mid,
            'action' => self::TYPE_ACTION_MODULES_DELETE,
        ];
        if ($this->setClientParams($cid, '', '', $addParams)) {
            return true;
        }
    }


    /**
     * Добавляет клиента в Группу
     * @param integer $cid
     * @param integer $gid
     * @return bool
     * @throws ErrorException
     */
    public function setGroup($cid, $gid)
    {
        return $this->doServiceRequest(
            self::TYPE_ACTION_CONTRACT_GROUP_ADD,
            $this->getContractServiceUrl(),
            [
                'contractId' => $cid,
                'contractGroupId' => $gid
            ]);
    }


    /**
     * Удаляет клиента из Группы
     * @param integer $cid
     * @param integer $gid
     * @return bool
     * @throws ErrorException
     */
    public function unsetGroup($cid, $gid)
    {
        return $this->doServiceRequest(
            self::TYPE_ACTION_CONTRACT_GROUP_REMOVE,
            $this->getContractServiceUrl(),
            [
                'contractId' => $cid,
                'contractGroupId' => $gid
            ]);
    }


    /**
     * Устанавливает новый лимит
     * @param integer $cid
     * @param integer $limit
     * @return bool
     * @throws ErrorException
     */
    public function updateLimit($cid, $limit = -1000000)
    {
        return $this->doServiceRequest(
            self::TYPE_ACTION_UPDATE_LIMIT,
            $this->getContractLimitServiceUrl(),
            [
                'contractId' => $cid,
                'limit' => $limit
            ]);
    }

    /**
     * Модуль Абонплаты
     * Возвращает данные услуг, подключенных в модуле
     * @param int $cid
     * @return array
     * @throws ErrorException
     */
    public function getServices($cid)
    {
        $additionalParams = [
            "actualItemsDate" => Yii::$app->formatter->asDate('now', 'dd.MM.yyyy'),
            "module" => "npay",
            "actualItemsOnly" => 1,
            "object_id" => 0,
            "action" => self::TYPE_ACTION_SERVICE_TABLE,
            "mid" => self::MODUL_SUBSCRIPTION
        ];
        $params = $this->clientRequestParams($cid, '', '', $additionalParams);
        $response = $this->doRequest(self::POST_METHOD, $this->getNewUrl(), ['query' => $params]);
        $this->checkError($response);

        $data = new SimpleXMLElement($response);
        $services = [];
        foreach ($data->table->data->row as $item) {
            $service = [];
            foreach ($item->attributes() as $key => $value) {
                $service[$key] = (string)$value;
            }
            $services[] = $service;
        }

        return $services;
    }

    /**
     * Модуль Абонплаты
     * Подключает выбранную услугу
     * @param integer $cid id клиента
     * @param integer $mid id модуля
     * @param integer $sid id услуги
     * @param integer $col количество
     * @param string $comment
     * @param string $dateStart "dd.MM.yyyy"
     * @param string $dateEnd "dd.MM.yyyy"
     * @return bool | void
     * @throws ErrorException
     */
    public function setService($cid, $mid, $sid, $col, $comment, $dateStart, $dateEnd = '')
    {
        $addParams = [
            'module' => 'npay',
            'id' => 0,
            'oid' => 0,
            'action' => self::TYPE_ACTION_SERVICE_UPDATE,
            'mid' => $mid,
            'sid' => $sid,
            'col' => $col,
            'comment' => $comment,
            'date1' => $dateStart,
            'date2' => $dateEnd,

        ];
        if ($this->setClientParams($cid, '', '', $addParams)) {
            return true;
        }
    }

    /**
     * Модуль Абонплаты
     * Отключает выбранную услугу
     * @param integer $cid id клиента
     * @param integer $mid id модуля
     * @param integer $sid id услуги
     * @param integer $col количество
     * @param string $comment
     * @param string $dateStart "dd.MM.yyyy"
     * @param string $dateEnd "dd.MM.yyyy"
     * @return bool | void
     * @throws ErrorException
     */
    public function unsetService($cid, $mid, $id, $sid, $col, $comment, $dateStart, $dateEnd = null)
    {
        $addParams = [
            'module' => 'npay',
            'id' => $id,
            'oid' => 0,
            'action' => self::TYPE_ACTION_SERVICE_UPDATE,
            'mid' => $mid,
            'sid' => $sid,
            'col' => $col,
            'comment' => $comment,
            'date1' => $dateStart,
            'date2' => is_null($dateEnd) ? Yii::$app->formatter->asDate('now', 'dd.MM.yyyy') : $dateEnd,

        ];
        if ($this->setClientParams($cid, '', '', $addParams)) {
            return true;
        }
    }

    /**
     * Модуль Абонплаты
     * Удаляет выбранную услугу
     * @param integer $cid id клиента
     * @param integer $mid id модуля
     * @param integer $id id подключенной услуги
     * @return bool | void
     * @throws ErrorException
     */
    public function deleteService($cid, $mid, $id)
    {
        $addParams = [
            'module' => 'npay',
            'action' => self::TYPE_ACTION_SERVICE_DELETE,
            'mid' => $mid,
            'id' => $id,
        ];
        if ($this->setClientParams($cid, '', '', $addParams)) {
            return true;
        }
    }


    /**
     * Модуль "Бухгалтерия".
     * Подключает документы
     * @param integer $cid
     * @param аrray $iid
     * @return bool | void
     * @throws ErrorException
     */
    public function setAccountingDocuments($cid, $iid)
    {
        $addParams = [
            'selectedItems' => implode(", ", $iid),
            'module' => 'bill',
            'action' => self::TYPE_ACTION_CONTRACT_DOCTYPE_ADD,
            'mid' => Billing::MODUL_ACCOUNTING
        ];
        if ($this->setClientParams($cid, '', '', $addParams)) {
            return true;
        }
    }


    /**
     * Модуль "Бухгалтерия".
     * Открепляет документы
     * @param integer $cid
     * @param аrray $iid
     * @return bool | void
     * @throws ErrorException
     */
    public function unsetAccountingDocuments($cid, $iid)
    {
        $addParams = [
            'selectedItems' => implode(", ", $iid),
            'module' => 'bill',
            'action' => self::TYPE_ACTION_CONTRACT_DOCTYPE_DELETE,
            'mid' => Billing::MODUL_ACCOUNTING
        ];
        if ($this->setClientParams($cid, '', '', $addParams)) {
            return true;
        }
    }


    /**
     * Модуль "Прочие услуги"
     * Подключает выбранную услугу
     * @param integer $cid id клиента
     * @param integer $sid id услуги
     * @param integer $amount количество
     * @param string $comment
     * @param string $dateStart "yyyy-MM-dd"
     * @return bool | void
     * @throws ErrorException
     */
    public function setOtherService($cid, $sid, $amount, $comment, $dateStart)
    {
        return $this->doServiceRequest(
            self::TYPE_ACTION_OTHER_SERVICE_UPDATE,
            $this->getContractOtherServiceListUrl(),
            [
                'rscmContractService' =>
                    [
                        'amount' => $amount,
                        'comment' => $comment,
                        'contractId' => $cid,
                        'date' => $dateStart,
                        'id' => 0,
                        'serviceId' => $sid
                    ],
            ]
        );
    }


    /**
     * Модуль "Прочие услуги"
     * Отключает выбранную услугу
     * @param integer $cid id клиента
     * @param integer $id id подключенной услуги
     * @return bool | void
     * @throws ErrorException
     */
    public function unsetOtherService($cid, $id)
    {
        return $this->doServiceRequest(
            self::TYPE_ACTION_OTHER_SERVICE_DELETE,
            $this->getContractOtherServiceListUrl(),
            [
                'rscmContractServiceId' => $id,
                'contractId' => $cid,
                'month' => Yii::$app->formatter->asDate('now', 'yyyy-MM-dd')
            ]
        );
    }


    /**
     * Справочник. Тарифные планы
     * Создать новый тариф
     * @param string $title
     * @return bool | integer
     * @throws ErrorException
     */
    public function addTariffPlan($title)
    {
        $additionalParams = [
            'module' => 'tariff',
            'action' => self::TYPE_ACTION_TARIFF_PLAN_ADD,
            'used' => 2
        ];
        $params = $this->clientRequestParams('', '', '', $additionalParams);

        $response = $this->doRequest(self::POST_METHOD, $this->getNewUrl(), ['query' => $params]);
        $this->checkError($response);

        $data = new SimpleXMLElement($response);
        $id = (string)$data->tariffPlan["id"];
        if (isset($id) && $this->editTariffPlan($id, $title)) {
            return $id;
        }
        return false;
    }


    /**
     * Справочник. Тарифные планы
     * Задать параметры тарифа
     * filterFace = 2 - юридическое лицо
     *              1 - физическое лицо
     * TODO группы договоров
     * filterGroups = 0 - не выбрано
     *                1 - Юр. лица
     *                2 - Юр.лица постоплата
     *                4 - Юр лица предоплата
     *                 - Юр.лица трафик
     *                 - Нестандартные счета
     * @param integer $tpid id тарифа
     * @param string $title
     * @return bool | void
     * @throws ErrorException
     */
    public function editTariffPlan($tpid, $title)
    {
        return $this->doServiceRequest(
            "tariffPlanUpdate",
            $this->getTariffServiceUrl(),
            [
                'tariffPlan' => [
                    'id' => $tpid,
                    'title' => $title,
                    'filterFace' => 2,
                    'filterGroups' => 0,
                    'filterMask' => '',
                    'used' => true,
                    'useTitleInWeb' => true,
                ],
            ]
        );
    }


    /**
     * Справочник. Тарифные планы
     * Удалить тариф
     * @param integer $tpid id тарифа
     * @return bool | void
     * @throws ErrorException
     */
    public function deleteTariffPlan($tpid)
    {
        $addParams = [
            'module' => 'tariff',
            'action' => self::TYPE_ACTION_TARIFF_PLAN_DELETE,
            'tpid' => $tpid
        ];
        if ($this->setClientParams('', '', '', $addParams)) {
            return true;
        }
    }

    /**
     * Тарифные планы
     * Возвращает данные установленных тарифов
     * @param integer $cid
     * @return array
     * @throws ErrorException
     */
    public function getContractTariffPlan($cid)
    {
        $params = $this->clientRequestParams($cid, '', '', ['action' => self::TYPE_ACTION_TARIFF_PLAN_GET]);
        $response = $this->doRequest(self::POST_METHOD, $this->getNewUrl(), ['query' => $params]);
        $this->checkError($response);

        $data = new SimpleXMLElement($response);
        $tariffPlans = [];
        foreach ($data->table->data->row as $item) {
            $tariff = [];
            foreach ($item->attributes() as $key => $value) {
                $tariff[$key] = (string)$value;
            }
            $tariffPlans[] = $tariff;
        }

        return $tariffPlans;
    }

    /**
     * "Тарифные планы"
     * Подключает тариф к выбранному модулю
     * @param integer $cid id клиента
     * @param integer $tpid id тарифа
     * @param string $date1 "dd.MM.yyyy"
     * @param string $date2 "dd.MM.yyyy"
     * @param string $comment
     * @param integer $pos позиция
     * @return bool | void
     * @throws ErrorException
     */
    public function setContractTariffPlan($cid, $tpid, $date1, $date2 = '', $comment = '', $pos = '')
    {
        $addParams = [
            "id" => 0,
            "action" => self::TYPE_ACTION_CONTRACT_TARIFF_PLAN_UPDATE,
            "tpid" => $tpid,
            "date1" => $date1,
            "date2" => $date2,
            "pos" => $pos,
            "comment" => $comment
        ];
        if ($this->setClientParams($cid, '', '', $addParams)) {
            return true;
        }
    }


    /**
     * Тарифные планы
     * Удаление тарифа из списка подключенных
     * @param integer $cid
     * @param аrray $id
     * @return bool | void
     * @throws ErrorException
     */
    public function unsetContractTariffPlan($cid, $id)
    {
        $addParams = [
            'action' => self::TYPE_ACTION_CONTRACT_TARIFF_PLAN_DELETE,
            'id' => $id
        ];
        if ($this->setClientParams($cid, '', '', $addParams)) {
            return true;
        }
    }

    /**
     * Возвращает массив id субдоговоров
     * @param integer $cid id клиента
     * @param integer $self включить родителя
     * @return bool | array
     * @throws BadRequestHttpException
     */
    public function getContractSubList($cid, $self = false)
    {
        $response = $this->doServiceRequest(
            self::TYPE_ACTION_CONTRACT_SUB_LIST,
            $this->getContractServiceUrl(),
            [
                'contractId' => $cid,
                'subMode' => self::SUB_MODE_ALL,
                'withSuperCid' => $self
            ],
            $return = true
        );

        $result = json_decode($response);
        if ($result->status !== 'ok') {
            throw new BadRequestHttpException(implode(' ', [$result->status, $result->exception, $result->message]));
        }

        $subList = [];
        foreach ($result->data->return as $item) {
            $subList[] = $self ? $item : $item->id;
        }

        return $subList;
    }
}
