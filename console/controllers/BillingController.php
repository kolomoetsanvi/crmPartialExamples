<?php


namespace console\controllers;

use common\components\billings\wipline\bgbilling\Billing;
use common\components\Helper;
use common\models\bgbillingdb\BgbDbBillContractDocType7;
use common\models\bgbillingdb\BgbDbBillData7;
use common\models\bgbillingdb\BgbDbBillDocType7;
use common\models\bgbillingdb\BgbDbBillInvoiceData7;
use common\models\bgbillingdb\BgbDbContractModule;
use common\models\bgbillingdb\BgbDbContractTariff;
use common\models\bgbillingdb\BgbDbModule;
use common\models\bgbillingdb\BgbDbModuleConfig;
use common\models\bgbillingdb\BgbDbModuleTariffTree;
use common\models\bgbillingdb\BgbDbMtreeNode;
use common\models\bgbillingdb\BgbDbNpayServiceObject3;
use common\models\bgbillingdb\BgbDbRscmServiceAccount4;
use common\models\bgbillingdb\BgbDbService;
use common\models\bgbillingdb\BgbDbTariffPlan;
use common\models\bill\BillClientInvoiceType;
use common\models\bill\BillClientService;
use common\models\bill\BillClientServiceGroup;
use common\models\bill\BillClientTariff;
use common\models\bill\BillInvoiceTypeServices;
use common\models\bill\BillServiceGroupList;
use common\models\bill\BillService;
use common\models\bill\BillStatusList;
use common\models\bill\BillTariffService;
use common\models\billing\Billings;
use common\models\client\Client;
use common\models\client\ClientDetail;
use common\models\client\ClientTypeList;
use common\models\client\search\LegalEntitySearch;
use common\models\details\DetailType;
use common\models\invoice\Invoice;
use common\models\invoice\InvoiceStatusList;
use common\models\invoice\InvoiceTypeList;
use common\models\invoice\InvoiceTypeListTypeList;
use common\models\Organizations;
use common\models\Tariffs;
use common\models\tariffs\TariffGroups;
use SimpleXMLElement;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\Expression;
use yii\helpers\Console;
use yii\console\Controller;
use yii\helpers\Json;
use yii\i18n\Formatter;
use http\Exception;


class BillingController extends Controller
{
    const PAGE_COUNT = 500;

    /**
     * Экспорт аресов ФИАС из CRM в базу BGB(адрес установки оборудования)
     * @throws InvalidConfigException
     */
    public function actionExportFiasAddress()
    {
        $dataProvider = new LegalEntitySearch();
        $orders = $dataProvider->searchWithFias();
        $exportOrdersCount = 0;
        $countOrders = 0;

        if (!empty($orders)) {
            $countOrders = count($orders);
            $bgbilling = Yii::createObject(Yii::$app->components['bgbill']);
        }

        Console::stdout(Helper::currentTime() . ' Старт экспорта адресов ФИАС.' . PHP_EOL);
        Console::stdout('Необходимо обработать ' . $countOrders . ' записей.' . PHP_EOL);

        foreach ($orders as $order) {
            try {
                // записываем новый адрес в базу BGB
                $bgbilling->setClientParams($order['idBgB'], Billing::PID_ADDRESS, '', ['address' => $order['addressFIAS']]);
                // записываем адрес установки оборудования из BGB в CRM
                $contractParameters = $bgbilling->getContractParams($order['idBgB']);
                if (isset($contractParameters['address'])) {
                    ClientDetail::setDetail($order['client_id'], DetailType::TYPE_ADDRESS, $contractParameters['address']);
                }
            } catch (Exception $e) {
                Console::stdout(Helper::currentTime() . ' Ошибка!. ' . $e->getMessage() . PHP_EOL);
            }
            $exportOrdersCount++;
            if ($exportOrdersCount % 10 === 0) {
                Console::stdout('Обработано ' . $exportOrdersCount . ' адресов. Из ' . $countOrders . PHP_EOL);
            }
        }//foreach ($orders as $order)
        Console::stdout(Helper::currentTime() . ' Экспорт окончен.' . PHP_EOL);
        Console::stdout('Экспортировано ' . $exportOrdersCount . ' адресов ФИАС.' . PHP_EOL);
    }

    public function actionExportFiasParentContractsAddress()
    {
        $dataProvider = new LegalEntitySearch();
        $contracts = $dataProvider->searchParentContractsWithAddress();
        $exportContractsCount = 0;
        $countContracts = 0;

        if (!empty($contracts)) {
            $countContracts = count($contracts);
        }
        Console::stdout(Helper::currentTime() . ' Старт очистки адресов родительских договоров.' . PHP_EOL);
        Console::stdout('Необходимо обработать ' . $countContracts . ' записей.' . PHP_EOL);

        $bgbilling = Yii::createObject(Yii::$app->components['bgbill']);

        foreach ($contracts as $contract) {
            try {
                // записываем пустой адрес в базу BGB
                $bgbilling->setClientParams($contract['idBgB'], Billing::PID_ADDRESS, '', ['address' => '']);
                // записываем адрес установки оборудования из BGB в CRM
                $contractParameters = $bgbilling->getContractParams($contract['idBgB']);
                if (!isset($contractParameters['address'])) {
                    ClientDetail::setDetail($contract['client_id'], DetailType::TYPE_ADDRESS, "");
                }
            } catch (Exception $e) {
                Console::stdout(Helper::currentTime() . ' Ошибка!. ' . $e->getMessage() . PHP_EOL);
            }
            $exportContractsCount++;
            if ($exportContractsCount % 10 === 0) {
                Console::stdout('Обработано ' . $exportContractsCount . ' адресов. Из ' . $countContracts . PHP_EOL);
            }
        }//foreach ($orders as $order)
        Console::stdout(Helper::currentTime() . ' Очистка окончен.' . PHP_EOL);
        Console::stdout('Очищено ' . $exportContractsCount . ' адресов родительских договоров.' . PHP_EOL);
    }

    /**
     * Экспорт IP адресов из CRM в базу BGBilling
     * @throws InvalidConfigException
     */
    public function actionExportDevIp()
    {
        $dataProvider = new LegalEntitySearch();
        $orders = $dataProvider->searchWithDevIp();
        $exportOrdersCount = 0;
        $countOrders = 0;

        if (!empty($orders)) {
            $countOrders = count($orders);
            $bgbilling = Yii::createObject(Yii::$app->components['bgbill']);
        }

        Console::stdout(Helper::currentTime() . ' Старт экспорта IP адресов.' . PHP_EOL);
        Console::stdout('Необходимо обработать ' . $countOrders . ' записей.' . PHP_EOL);

        foreach ($orders as $order) {
            try {
                // записываем новый ip адрес в базу BGB
                $bgbilling->setClientParams($order['idBgB'], Billing::PID_DEVIP, $order['devip']);
                // записываем ip адрес из BGB в CRM
                $contractParameters = $bgbilling->getContractParams($order['idBgB']);
                if (isset($contractParameters['devip'])) {
                    ClientDetail::setDetail($order['client_id'], DetailType::TYPE_DEVIP, $contractParameters['devip']);
                }
                $exportOrdersCount++;
            } catch (Exception $e) {
                Console::stdout(Helper::currentTime() . ' Ошибка!. ' . $e->getMessage() . PHP_EOL);
            }
            if ($exportOrdersCount % 10 == 0) {
                Console::stdout('Обработано ' . $exportOrdersCount . ' IP адресов. Из ' . $countOrders . PHP_EOL);
            }
        }
        Console::stdout(Helper::currentTime() . ' Экспорт окончен.' . PHP_EOL);
        Console::stdout('Экспортировано ' . $exportOrdersCount . ' IP адресов.' . PHP_EOL);
    }

    /**
     * Импорт Типов документов из базы BGBilling в базу CRM
     * @throws \yii\db\Exception
     */
    public function actionImportInvoice()
    {
        $this->importInvoiceType();
        $this->importInvoiceAct();
        $this->importInvoiceInvoice();
    }

    private function importInvoiceType()
    {
        $separator = '{*}';
        $docTypesBGB = BgbDbBillDocType7::find()->select(["CONCAT_WS('$separator' ,title, type)"])->column();
        $countDocTypes = 0;
        $readDocTypes = 0;
        $importDocTypes = 0;

        if (!empty($docTypesBGB)) {
            $countDocTypes = count($docTypesBGB);
        }
        Console::stdout(Helper::currentTime() . ' Старт импорта Типов документов.' . PHP_EOL);
        Console::stdout('Необходимо обработать ' . $countDocTypes . ' записей.' . PHP_EOL);

        $alreadyExistsTypes = InvoiceTypeList::find()->select(["CONCAT_WS('$separator' ,invoice_type_descr, invoice_type_type)"])->column();
        $docTypes = array_diff($docTypesBGB, $alreadyExistsTypes);

        foreach ($docTypes as $type) {
            try {
                $value = explode($separator, $type);
                if (is_array($value)) {
                    $invoiceType = new InvoiceTypeList();
                    $invoiceType->invoice_type_descr = $value[0];
                    $invoiceType->invoice_type_type = (int)$value[1];
                    if ($invoiceType->save()) {
                        $importDocTypes++;
                    }
                }
                $readDocTypes++;
            } catch (Exception $e) {
                Console::stdout(Helper::currentTime() . ' Ошибка!. ' . $e->getMessage() . PHP_EOL);
            }
            if ($readDocTypes % 10 === 0) {
                Console::stdout('Обработано ' . $readDocTypes . ' записей. Из ' . $countDocTypes . PHP_EOL);
            }
        }
        Console::stdout(Helper::currentTime() . ' Импорт окончен.' . PHP_EOL);
        Console::stdout('Импортировано ' . $importDocTypes . ' Типов документов.' . PHP_EOL);
    }

    /**
     * Импорт АКТов из базы BGBilling в базу CRM
     * @throws \Exception
     */
    private function importInvoiceAct()
    {
        Console::stdout(Helper::currentTime() . ' Старт импорта АКТов.' . PHP_EOL);

        $query = BgbDbBillInvoiceData7::find()->alias('d');

        $countActs = $query->count();
        $formatter = new Formatter();
        $importActs = 0;

        Console::stdout('Необходимо обработать ' . $countActs . ' записей.' . PHP_EOL);

        if ($countActs > 0) {
            $readActs = 0;
            $actTypes = InvoiceTypeList::find()
                ->select('invoice_type_id')
                ->where(['invoice_type_type' => InvoiceTypeListTypeList::TYPE_ACT])
                ->indexBy('invoice_type_descr')
                ->column();

            $query->select([
                'd.id',
                'd.cid',
                'd.type',
                'd.number_in_year',
                'd.yy',
                'd.mm',
                'd.create_dt',
                'd.summ',
                'd.xml',
                'typeName' => 'dt.title'
            ])
                ->leftJoin(['dt' => 'bill_doc_type_7'], 'd.type = dt.id')
                ->asArray();

            for ($i = 0; $i < ceil($countActs / self::PAGE_COUNT); $i++) {

                $acts = $query->offset($i * self::PAGE_COUNT)
                    ->limit(self::PAGE_COUNT)
                    ->all();

                $rows = [];
                foreach ($acts as $actItem) {
                    $readActs++;
                    if ($readActs % 50 === 0) {
                        Console::stdout('Обработано ' . $readActs . ' записей. Из ' . $countActs . PHP_EOL);
                    }

                    if (Invoice::find()->alias('i')
                        ->where([
                            'i.external_id' => $actItem['id'],
                            'i.billing_id' => Billings::BILLING_BGBILLING,
                            'invoice_type' => array_values($actTypes)
                        ])->exists()) {
                        continue;
                    }

                    $client = Client::find()->alias('c')
                        ->select(['c.client_id', 'c.organization_id'])
                        ->addLeftActiveDetail('c.client_id', 'ls', DetailType::TYPE_PERSONAL_ACCOUNT)
                        ->where(['c.client_type' => ClientTypeList::TYPES_FOR_LEGAL, 'ls.detail_value' => $actItem['cid']])
                        ->asArray()
                        ->one();

                    if ($client !== null) {

                        $rows[] = [
                            'invoice_client_id' => (int)$client['client_id'],
                            'invoice_type' => (int)$actTypes[$actItem['typeName']],
                            'invoice_number_in_year' => (int)$actItem['number_in_year'],
                            'invoice_yy' => (int)$actItem['yy'],
                            'invoice_mm' => (int)$actItem['mm'],
                            'invoice_sum' => $actItem['summ'],
                            'invoice_status' => InvoiceStatusList::STATUS_ACTIVE,
                            'invoice_template_data' => $this->parseInvoiceXML($actItem['xml'], (int)$client['organization_id']),
                            'create_date' => $formatter->asTimestamp($actItem['create_dt']),
                            'created_by_user' => 1,
                            'billing_id' => Billings::BILLING_BGBILLING,
                            'external_id' => (int)$actItem['id'],
                            'external_number_in_year' => (int)$actItem['number_in_year']
                        ];
                    }
                }

                if (!empty($rows)) {
                    $importActs += Yii::$app->db->createCommand()->batchInsert(Invoice::tableName(), [
                        'invoice_client_id',
                        'invoice_type',
                        'invoice_number_in_year',
                        'invoice_yy',
                        'invoice_mm',
                        'invoice_sum',
                        'invoice_status',
                        'invoice_template_data',
                        'create_date',
                        'created_by_user',
                        'billing_id',
                        'external_id',
                        'external_number_in_year'
                    ], $rows)->execute();
                }
            }
        }

        Console::stdout(Helper::currentTime() . ' Импорт окончен.' . PHP_EOL);
        Console::stdout('Импортировано ' . $importActs . ' АКТов.' . PHP_EOL);
    }

    /**
     * Импорт Счетов из базы BGBilling в базу CRM
     * @throws \yii\db\Exception
     */
    private function importInvoiceInvoice()
    {
        Console::stdout(Helper::currentTime() . ' Старт импорта Счетов.' . PHP_EOL);

        $query = BgbDbBillData7::find()->alias('d');

        $countInvoices = $query->count();
        $formatter = new Formatter();
        $importInvoices = 0;

        Console::stdout('Необходимо обработать ' . $countInvoices . ' записей.' . PHP_EOL);

        if ($countInvoices > 0) {
            $readInvoices = 0;
            $invoiceTypes = InvoiceTypeList::find()
                ->select('invoice_type_id')
                ->where(['invoice_type_type' => InvoiceTypeListTypeList::TYPE_INVOICE])
                ->indexBy('invoice_type_descr')
                ->column();

            $query->select([
                'd.id',
                'd.cid',
                'd.type',
                'd.number_in_year',
                'd.yy',
                'd.mm',
                'd.create_dt',
                'd.summ',
                'd.xml',
                'typeName' => 'dt.title'
            ])
                ->leftJoin(['dt' => 'bill_doc_type_7'], 'd.type = dt.id')
                ->asArray();

            for ($i = 0; $i < ceil($countInvoices / self::PAGE_COUNT); $i++) {

                $invoices = $query->offset($i * self::PAGE_COUNT)
                    ->limit(self::PAGE_COUNT)
                    ->all();

                $rows = [];
                foreach ($invoices as $invoiceItem) {
                    $readInvoices++;
                    if ($readInvoices % 50 === 0) {
                        Console::stdout('Обработано ' . $readInvoices . ' записей. Из ' . $countInvoices . PHP_EOL);
                    }

                    if (Invoice::find()->alias('i')
                        ->where([
                            'i.external_id' => (int)$invoiceItem['id'],
                            'i.billing_id' => Billings::BILLING_BGBILLING,
                            'invoice_type' => array_values($invoiceTypes)
                        ])->exists()) {
                        continue;
                    }

                    $client = Client::find()->alias('c')
                        ->select(['c.client_id', 'c.organization_id'])
                        ->addLeftActiveDetail('c.client_id', 'ls', DetailType::TYPE_PERSONAL_ACCOUNT)
                        ->where(['c.client_type' => ClientTypeList::TYPES_FOR_LEGAL, 'ls.detail_value' => $invoiceItem['cid']])
                        ->asArray()
                        ->one();

                    if ($client !== null) {

                        $rows[] = [
                            'invoice_client_id' => (int)$client['client_id'],
                            'invoice_type' => (int)$invoiceTypes[$invoiceItem['typeName']],
                            'invoice_number_in_year' => (int)$invoiceItem['number_in_year'],
                            'invoice_yy' => (int)$invoiceItem['yy'],
                            'invoice_mm' => (int)$invoiceItem['mm'],
                            'invoice_sum' => $invoiceItem['summ'],
                            'invoice_status' => InvoiceStatusList::STATUS_ACTIVE,
                            'invoice_template_data' => $this->parseInvoiceXML($invoiceItem['xml'], (int)$client['organization_id']),
                            'create_date' => $formatter->asTimestamp($invoiceItem['create_dt']),
                            'created_by_user' => 1,
                            'billing_id' => Billings::BILLING_BGBILLING,
                            'external_id' => (int)$invoiceItem['id'],
                            'external_number_in_year' => (int)$invoiceItem['number_in_year']
                        ];

                    }
                }

                if (!empty($rows)) {
                    $importInvoices += Yii::$app->db->createCommand()->batchInsert(Invoice::tableName(), [
                        'invoice_client_id',
                        'invoice_type',
                        'invoice_number_in_year',
                        'invoice_yy',
                        'invoice_mm',
                        'invoice_sum',
                        'invoice_status',
                        'invoice_template_data',
                        'create_date',
                        'created_by_user',
                        'billing_id',
                        'external_id',
                        'external_number_in_year'
                    ], $rows)->execute();
                }

            }
        }

        Console::stdout(Helper::currentTime() . ' Импорт окончен.' . PHP_EOL);
        Console::stdout('Импортировано ' . $importInvoices . ' Счетов.' . PHP_EOL);
    }

    /**
     * @param string $xmlString
     * @param int $organizationId
     * @return string
     * @throws \Exception
     */
    private function parseInvoiceXML($xmlString, $organizationId)
    {
        $dataXML = new SimpleXMLElement($xmlString);

        $organization = '';
        $organizationAddress = '';
        $organizationInn = '';
        $organizationKpp = '';
        $organizationExecutor = '';
        $organizationAccountant = '';
        $executorDescr = '';
        $stampFileName = '';

        if ($organizationId === Organizations::ORG_ORDEN || $organizationId === Organizations::ORG_WIPLINE) {
            $organization = 'ООО "ВИПЛАЙН"';
            $organizationAddress = '394077, Воронежская обл., г. Воронеж, Московский пр-кт, дом No 97, офис 15-08, тел.: +7 (473) 274-88-54';
            $organizationInn = '3662209835';
            $organizationKpp = '366201001';
            $organizationExecutor = 'Яцкина А.В.';
            $organizationAccountant = Invoice::getAccountant($dataXML->bill['date']);
            $executorDescr = 'Представитель ООО "Виплайн" по доверенности б/н от 08.09.2016';
            $stampFileName = 'wipline.png';
        }

        if ($organizationId === Organizations::ORG_WIPLINE_ROSTOV) {
            $organization = '';
            $organizationAddress = '';
            $organizationInn = '';
            $organizationKpp = '';
            $organizationExecutor = '';
            $organizationAccountant = '';
            $executorDescr = '';
            $stampFileName = '';
        }

        $legalEntityTitle = '';
        $legalEntityAddress = '';
        $leINN = '';
        $additionalParameters = '';

        foreach ($dataXML->bill->contract_params as $item) {
            foreach ($item as $it) {
                if (isset($it['pid']) && (int)$it['pid'] === 1) {
                    $legalEntityTitle = isset($it['value']) ? (string)$it['value'] : '';
                }
                if (isset($it['pid']) && (int)$it['pid'] === 9) {
                    $leINN = isset($it['value']) ? (int)$it['value'] : '';
                }
                if (isset($it['pid']) && (int)$it['pid'] === 7) {
                    $legalEntityAddress = isset($it['value']) ? (string)$it['value'] : '';
                }

                if (isset($it['pid']) && (int)$it['pid'] === 11) {
                    $additionalParameters = isset($it['value']) ? (string)$it['value'] : '';
                }
            }
        }

        if (isset($dataXML->bill->pos)) {
            $tDescr = isset($dataXML->bill->pos['name']) ? (string)$dataXML->bill->pos['name'] : '';
            $tSum = isset($dataXML->bill->pos['summ']) ? (double)$dataXML->bill->pos['summ'] : '';
            $subscriptions[] = [$tDescr . ' ' . $additionalParameters => $tSum];
        }

        //Данные из Заказов
        if (isset($dataXML->bill->sub_bill)) {
            foreach ($dataXML->bill->sub_bill as $item) {
                if (isset($item->pos)) {
                    $descr = isset($item->pos['name']) ? (string)$item->pos['name'] : '';
                    $sum = isset($item->pos['summ']) ? (double)$item->pos['summ'] : '';

                    $subCid = $item['cid'];
                    $addParameters = '';
                    foreach ($dataXML->bill->contract_data->sub_contract as $subItem) {
                        if ($subItem['cid'] === $subCid) {
                            foreach ($subItem->parameters as $parameterItem) {
                                if (isset($parameterItem['pid']) && (int)$parameterItem['pid'] === 11) {
                                    $addParameters = isset($parameterItem['value']) ? (string)$parameterItem['value'] : '';
                                }
                            }
                        }
                    }
                    $subscriptions[] = [$descr . ' ' . $addParameters => $sum];
                }
            }
        }

        return Json::encode([
            'docNumber' => isset($dataXML->bill['bill_number']) ? (string)$dataXML->bill['bill_number'] : '',
            'docDate' => isset($dataXML->bill['date']) ? (string)$dataXML->bill['date'] : '',
            'organizationBik' => isset($dataXML->bill['bik']) ? (int)$dataXML->bill['bik'] : '',
            'organizationCorrAccount' => isset($dataXML->bill['corr_account']) ? (int)$dataXML->bill['corr_account'] : '',
            'organizationAccount' => isset($dataXML->bill['account']) ? (int)$dataXML->bill['account'] : '',
            'organizationBank' => isset($dataXML->bill['bank_title']) ? (string)$dataXML->bill['bank_title'] : '',
            'organization' => $organization,
            'organizationAddress' => $organizationAddress,
            'organizationInn' => $organizationInn,
            'organizationKpp' => $organizationKpp,
            'organizationExecutor' => $organizationExecutor,
            'organizationAccountant' => $organizationAccountant,
            'executorDescr' => $executorDescr,
            'stampFileName' => $stampFileName,
            'legalEntityTitle' => $legalEntityTitle,
            'legalEntityAddress' => $legalEntityAddress,
            'leINN' => $leINN,
            'services' => $subscriptions,
        ]);
    }

    /**
     * Импорт данных из базы BGBilling в базу CRM
     * для реализации Биллинга
     */
    public function actionImportBill()
    {
        $this->importServiceGroup();
        $this->importServices();
        $this->importTariffs();
        $this->importClientServiceGroup();
        $this->importClientTariff();
        $this->importClientServiceSubscription();
        $this->importClientServiceOther();
        $this->importClientInvoiceType();
        $this->importInvoiceTypeService();
    }

    /**
     * Импорт Модулей из базы BGBilling в базу CRM
     * В CRM модули - группа тарифов
     */
    private function importServiceGroup()
    {
        $servicesGroup = BgbDbModule::find()->all();
        $countServicesGroup = 0;
        $readServicesGroup = 0;
        $importServicesGroup = 0;

        if (isset($servicesGroup)) {
            $countServicesGroup = count($servicesGroup);
        }
        Console::stdout(Helper::currentTime() . ' Старт импорта Групп услуг (модулей).' . PHP_EOL);
        Console::stdout('Необходимо обработать ' . $countServicesGroup . ' записей.' . PHP_EOL);

        foreach ($servicesGroup as $serviceGroupItem) {
            if ($readServicesGroup % 10 === 0) {
                Console::stdout('Обработано ' . $readServicesGroup . ' записей. Из ' . $countServicesGroup . PHP_EOL);
            }
            $readServicesGroup++;

            if (BillServiceGroupList::find()
                ->where([
                    'service_group_title' => $serviceGroupItem->title,
                    'external_id' => $serviceGroupItem->id,
                    'billing_id' => Billings::BILLING_BGBILLING,
                ])->exists()) {
                continue;
            }

            try {
                $serviceGroup = new BillServiceGroupList();
                $serviceGroup->service_group_title = $serviceGroupItem->title;
                $serviceGroup->external_id = $serviceGroupItem->id;
                $serviceGroup->billing_id = Billings::BILLING_BGBILLING;
                if ($serviceGroup->save()) {
                    $importServicesGroup++;
                }
            } catch (Exception $e) {
                Console::stdout(Helper::currentTime() . ' Ошибка!. ' . $e->getMessage() . PHP_EOL);
            }
        }
        Console::stdout(Helper::currentTime() . ' Импорт окончен.' . PHP_EOL);
        Console::stdout('Импортировано ' . $importServicesGroup . ' Групп услуг (модулей).' . PHP_EOL);
    }

    /**
     * Импорт Услуг по тарифам из базы BGBilling в базу CRM
     */
    private function importServices()
    {
        $services = BgbDbService::find()->all();
        $countItem = 0;
        $readItem = 0;
        $importItem = 0;

        if (!empty($services)) {
            $countItem = count($services);
        }
        Console::stdout(Helper::currentTime() . ' Старт импорта Услуг.' . PHP_EOL);
        Console::stdout('Необходимо обработать ' . $countItem . ' записей.' . PHP_EOL);

        foreach ($services as $serviceItem) {
            if ($readItem % 10 === 0) {
                Console::stdout('Обработано ' . $readItem . ' записей. Из ' . $countItem . PHP_EOL);
            }
            $readItem++;

            $titleModuleBgB = BgbDbModule::getTitleById($serviceItem->mid);
            $serviceGroupIdCrm = BillServiceGroupList::getIdByTitle(isset($titleModuleBgB) ? $titleModuleBgB : '');

            if (BillService::find()
                ->where([
                    'service_title' => $serviceItem->title,
                    'service_group_id' => isset($serviceGroupIdCrm) ? $serviceGroupIdCrm : 0,
                    'external_id' => $serviceItem->id,
                    'billing_id' => Billings::BILLING_BGBILLING,
                ])->exists()) {
                continue;
            }

            if (!isset($serviceGroupIdCrm)) {
                continue;
            }
            try {
                $service = new BillService();
                $service->service_title = $serviceItem->title;
                $service->service_group_id = $serviceGroupIdCrm;
                $service->external_id = $serviceItem->id;
                $service->billing_id = Billings::BILLING_BGBILLING;
                $service->create_date = time();
                $service->created_by_user = 1;
                if ($service->save()) {
                    $importItem++;
                }
            } catch (Exception $e) {
                Console::stdout(Helper::currentTime() . ' Ошибка!. ' . $e->getMessage() . PHP_EOL);
            }
        }
        Console::stdout(Helper::currentTime() . ' Импорт окончен.' . PHP_EOL);
        Console::stdout('Импортировано ' . $importItem . ' Услуг.' . PHP_EOL);
    }


    /**
     * Импорт Тарифов из базы BGBilling (Модуль Абонплаты, Прочие услуги) в базу CRM
     */
    private function importTariffs()
    {
        $moduleTariffTree = BgbDbModuleTariffTree::find()->alias('mtt')
            ->select([
                'mtt.id as mttId',
                'mtt.tree_id as tariffId',
                'tariff.title as tariffTitle'
            ])
            ->leftJoin(['tariff' => 'tariff_plan'], [
                'tariff.id' => new Expression('mtt.tree_id')
            ])
            ->where(['in', 'mid', [BgbDbModule::MODULE_SUBSCRIPTION, BgbDbModule::MODULE_OTHER_SERVICE]])
            ->asArray()
            ->all();

        $countTariff = 0;
        $readTariff = 0;
        $importTariff = 0;

        if (isset($moduleTariffTree)) {
            $countTariff = count($moduleTariffTree);
        }
        Console::stdout(Helper::currentTime() . ' Старт импорта Тарифов.' . PHP_EOL);
        Console::stdout('Необходимо обработать ' . $countTariff . ' записей.' . PHP_EOL);

        foreach ($moduleTariffTree as $tariffItem) {
            if ($readTariff % 10 === 0) {
                Console::stdout('Обработано ' . $readTariff . ' записей. Из ' . $countTariff . PHP_EOL);
            }
            $readTariff++;

            $crmTariff = Tariffs::find()
                ->where([
                    'tarif_external_id' => $tariffItem['tariffId'],
                    'tarif_external_group_id' => TariffGroups::getBgbLeGroupId(),
                ])->one();

            if ($crmTariff !== null) {
                if ($crmTariff->tarif_status === Tariffs::STATUS_DELETE) {
                    $crmTariff->tarif_status = Tariffs::STATUS_ACTIVE;
                    if ($crmTariff->save()) {
                        $importTariff++;
                    }
                }
                continue;
            }

            $tariffData = BgbDbMtreeNode::find()->where(['mtree_id' => $tariffItem['mttId']])->orderBy('id')->all();

            try {
                $tariff = new Tariffs();
                $tariff->tarif_name = $tariffItem['tariffTitle'];
                $tariff->tarif_speed = $this->parseTariffSpeed($tariffItem['tariffTitle']);
                $tariff->tarif_cost = isset($tariffData) ? $this->parseTariffCost($tariffData) : 0;
                $tariff->tarif_status = Tariffs::STATUS_ACTIVE;
                $tariff->tarif_external_id = $tariffItem['tariffId'];
                $tariff->created_at = time();
                $tariff->created_by_user = 1;
                $tariff->tarif_external_group_id = TariffGroups::getBgbLeGroupId();
                if ($tariff->save()) {
                    $importTariff++;
                    $serviceCRM = isset($tariffData) ? $this->parseTariffService($tariffData) : null;

                    $tariffService = new BillTariffService();
                    $tariffService->tariff_id = $tariff->tarif_id;
                    $tariffService->service_group_id = isset($serviceCRM) ? $serviceCRM->service_group_id : 0;
                    $tariffService->service_id = isset($serviceCRM) ? $serviceCRM->service_id : 0;
                    $tariffService->status = BillStatusList::STATUS_ACTIVE;
                    $tariffService->create_date = time();
                    $tariffService->created_by_user = 1;
                    $tariffService->save();
                }

            } catch (Exception $e) {
                Console::stdout(Helper::currentTime() . ' Ошибка!. ' . $e->getMessage() . PHP_EOL);
            }
        }
        Console::stdout(Helper::currentTime() . ' Импорт окончен.' . PHP_EOL);
        Console::stdout('Импортировано ' . $importTariff . ' Тарифов.' . PHP_EOL);
    }

    /**
     * @param string $title
     * @return float|int
     */
    private function parseTariffSpeed($title)
    {
        if (strpos($title, 'Мбит/с') !== false) {
            $str = substr($title, 0, stripos($title, 'Мбит/с'));
            $speed = filter_var($str, FILTER_SANITIZE_NUMBER_INT);
            return $speed ? $speed * 1000 : 0;
        }

        if (strpos($title, 'Кбит/с') !== false) {
            $str = substr($title, 0, stripos($title, 'Кбит/с'));
            $speed = filter_var($str, FILTER_SANITIZE_NUMBER_INT);
            return $speed ?: 0;
        }

        return 0;
    }

    /**
     * @param array $tariffData
     * @return int|mixed
     */
    private function parseTariffCost($tariffData)
    {
        $cost = 0;
        foreach ($tariffData as $dataItem) {
            switch ($dataItem->type) {
                case 'cost':
                case 'costout':
                    $str = substr($dataItem->data, strripos($dataItem->data, '&') + 1);
                    $cost = filter_var($str, FILTER_VALIDATE_FLOAT);
                    break;
                case 'month_cost':
                    $str = substr($dataItem->data, strpos($dataItem->data, '&') + 1);
                    $str = substr($str, 0, strpos($str, '%'));
                    $cost = filter_var($str, FILTER_VALIDATE_FLOAT);
                    break;
            }
        }
        return $cost;
    }

    /**
     * @param array $tariffData
     * @return BillService|null
     */
    private function parseTariffService($tariffData)
    {
        $crmService = null;

        foreach ($tariffData as $dataItem) {
            $sid = null;

            switch ($dataItem->type) {
                case 'month_mode':
                case 'serviceSet':
                    $str = substr($dataItem->data, strripos($dataItem->data, '&') + 1);
                    $sid = filter_var($str, FILTER_VALIDATE_INT);
                    break;
                case 'service':
                    $sid = (int)$dataItem->data;
                    break;
            }

            if ($sid) {
                $titleBGB = BgbDbService::getTitleById($sid);
                $mid = BgbDbModuleTariffTree::find()
                    ->select(['mid'])
                    ->where(['id' => $dataItem->mtree_id])
                    ->scalar();
                $groupId = BillServiceGroupList::getIdByTitle(BgbDbModule::getTitleById($mid));

                $crmService = BillService::getByTitleAndGroupId($titleBGB, $groupId);
            }
        }

        return $crmService;
    }

    /**
     * Импорт связей Клиент - Группа Услуг (Модуль)
     */
    private function importClientServiceGroup()
    {
        $contractsServiceGroup = BgbDbContractModule::find()->all();

        $countItem = 0;
        $readItem = 0;
        $importItem = 0;
        if (!empty($contractsServiceGroup)) {
            $countItem = count($contractsServiceGroup);
        }
        Console::stdout(Helper::currentTime() . ' Старт импорта связей Клиент - Группа Услуг.' . PHP_EOL);
        Console::stdout('Необходимо обработать ' . $countItem . ' записей.' . PHP_EOL);

        foreach ($contractsServiceGroup as $item) {
            if ($readItem % 10 === 0) {
                Console::stdout('Обработано ' . $readItem . ' записей. Из ' . $countItem . PHP_EOL);
            }
            $readItem++;

            $clientCrmId = ClientDetail::getClientByLS($item->cid);
            if ($clientCrmId === 0) {
                Console::stdout(' Ошибка!. Нет клиента с ИД ' . $item->cid . PHP_EOL);
                continue;
            }

            if (!Client::find()->where([
                'client_id' => $clientCrmId,
                'client_type' => ClientTypeList::TYPES_FOR_LEGAL
            ])->exists()) {
                Console::stdout(' Ошибка!. Клиент с ИД ' . $clientCrmId . ' НЕ юр. лицо!' . PHP_EOL);
                continue;
            }

            $serviceGroupIdCRM = BillServiceGroupList::getIdByTitle(BgbDbModule::getTitleById($item->mid));
            if (!isset($serviceGroupIdCRM)) {
                continue;
            }

            if (BillClientServiceGroup::find()
                ->where([
                    'client_id' => $clientCrmId,
                    'service_group_id' => $serviceGroupIdCRM,
                    'status' => BillStatusList::STATUS_ACTIVE,
                ])->exists()) {
                continue;
            }

            try {
                $clientServiceGroup = new BillClientServiceGroup();
                $clientServiceGroup->client_id = $clientCrmId;
                $clientServiceGroup->service_group_id = $serviceGroupIdCRM;
                $clientServiceGroup->status = BillStatusList::STATUS_ACTIVE;
                $clientServiceGroup->billing_id = Billings::BILLING_BGBILLING;
                $clientServiceGroup->create_date = time();
                $clientServiceGroup->created_by_user = 1;
                if ($clientServiceGroup->save()) {
                    $importItem++;
                }
            } catch (Exception $e) {
                Console::stdout(Helper::currentTime() . ' Ошибка!. ' . $e->getMessage() . PHP_EOL);
            }
        }
        Console::stdout(Helper::currentTime() . ' Импорт окончен.' . PHP_EOL);
        Console::stdout('Импортировано ' . $importItem . ' связей.' . PHP_EOL);
    }

    /**
     * Импорт связей Клиент - Тариф
     */
    private function importClientTariff()
    {
        $formatter = new Formatter();
        $contractTariff = BgbDbContractTariff::find()->all();

        $countItem = 0;
        $readItem = 0;
        $importItem = 0;
        if (!empty($contractTariff)) {
            $countItem = count($contractTariff);
        }
        Console::stdout(Helper::currentTime() . ' Старт импорта связей Клиент - Тариф.' . PHP_EOL);
        Console::stdout('Необходимо обработать ' . $countItem . ' записей.' . PHP_EOL);

        foreach ($contractTariff as $item) {
            if ($readItem % 10 === 0) {
                Console::stdout('Обработано ' . $readItem . ' записей. Из ' . $countItem . PHP_EOL);
            }
            $readItem++;

            $clientCrmId = ClientDetail::getClientByLS($item->cid);
            if ($clientCrmId === 0) {
                Console::stdout(' Ошибка!. Нет клиента с ИД ' . $item->cid . PHP_EOL);
                continue;
            }

            if (!Client::find()->where([
                'client_id' => $clientCrmId,
                'client_type' => ClientTypeList::TYPES_FOR_LEGAL
            ])->exists()) {
                Console::stdout(' Ошибка!. Клиент с ИД ' . $clientCrmId . ' НЕ юр. лицо!' . PHP_EOL);
                continue;
            }

            $tariffBgBTitle = BgbDbTariffPlan::getTariffTitleById($item->tpid);
            $tariffCrmId = Tariffs::getLeTariffIdByTitle($tariffBgBTitle);
            if (!isset($tariffCrmId)) {
                Console::stdout(' Ошибка!. Данного тарифа нет в СРМ. Тариф в БГБ ' . $item->tpid . PHP_EOL);
                continue;
            }

            if (BillClientTariff::find()
                ->where([
                    'client_id' => $clientCrmId,
                    'tariff_id' => $tariffCrmId,
                    'date_start' => $formatter->asTimestamp(isset($item->date1) ? $item->date1 : 0),
                    'date_end' => $formatter->asTimestamp(isset($item->date2) ? $item->date2 : 0),
                    'status' => BillStatusList::STATUS_ACTIVE,
                    'external_id' => $item->id,
                    'billing_id' => Billings::BILLING_BGBILLING,
                ])->exists()) {
                continue;
            }

            try {
                $clientTariff = new BillClientTariff();
                $clientTariff->client_id = $clientCrmId;
                $clientTariff->tariff_id = $tariffCrmId;
                $clientTariff->date_start = $formatter->asTimestamp(isset($item->date1) ? $item->date1 : 0);
                $clientTariff->date_end = $formatter->asTimestamp(isset($item->date2) ? $item->date2 : 0);
                $clientTariff->status = BillStatusList::STATUS_ACTIVE;
                $clientTariff->external_id = $item->id;
                $clientTariff->billing_id = Billings::BILLING_BGBILLING;
                $clientTariff->create_date = time();
                $clientTariff->created_by_user = 1;
                if ($clientTariff->save()) {
                    $importItem++;
                }
            } catch (Exception $e) {
                Console::stdout(Helper::currentTime() . ' Ошибка!. ' . $e->getMessage() . PHP_EOL);
            }
        }
        Console::stdout(Helper::currentTime() . ' Импорт окончен.' . PHP_EOL);
        Console::stdout('Импортировано ' . $importItem . ' связей.' . PHP_EOL);
    }

    /**
     * Импорт связей Клиент - Услуги (Абонплата)
     */
    private function importClientServiceSubscription()
    {
        $formatter = new Formatter();
        $contractsService = BgbDbNpayServiceObject3::find()->all();

        $countItem = 0;
        $readItem = 0;
        $importItem = 0;
        if (!empty($contractsService)) {
            $countItem = count($contractsService);
        }
        Console::stdout(Helper::currentTime() . ' Старт импорта связей Клиент - Услуги (Абонплаты).' . PHP_EOL);
        Console::stdout('Необходимо обработать ' . $countItem . ' записей.' . PHP_EOL);

        foreach ($contractsService as $item) {
            if ($readItem % 10 === 0) {
                Console::stdout('Обработано ' . $readItem . ' записей. Из ' . $countItem . PHP_EOL);
            }
            $readItem++;

            $clientCrmId = ClientDetail::getClientByLS($item->cid);
            if ($clientCrmId === 0) {
                Console::stdout(' Ошибка!. Нет клиента с ИД ' . $item->cid . PHP_EOL);
                continue;
            }

            if (!Client::find()->where([
                'client_id' => $clientCrmId,
                'client_type' => ClientTypeList::TYPES_FOR_LEGAL
            ])->exists()) {
                Console::stdout(' Ошибка!. Клиент с ИД ' . $clientCrmId . ' НЕ юр. лицо!' . PHP_EOL);
                continue;
            }

            $serviceBgB = BgbDbService::findOne($item->sid);
            $serviceCrmId = isset($serviceBgB) ? BillService::getByTitleAndGroupId($serviceBgB->title, $serviceBgB->mid) : null;
            if (!isset($serviceCrmId)) {
                Console::stdout(' Ошибка!. Данной услуги нет в СРМ. Услуга в БГБ ' . $item->sid . PHP_EOL);
                continue;
            }

            $clientServiceCRM = BillClientService::find()
                ->where([
                    'client_id' => $clientCrmId,
                    'service_id' => $serviceCrmId->service_id,
                    'date_start' => $formatter->asTimestamp(isset($item->date1) ? $item->date1 : 0),
                    'date_end' => $formatter->asTimestamp(isset($item->date2) ? $item->date2 : 0),
                    'status' => BillStatusList::STATUS_ACTIVE,
                    'external_id' => $item->id,
                    'billing_id' => Billings::BILLING_BGBILLING,
                ])->one();
            if ($clientServiceCRM !== null) {
                continue;
            }

            try {
                $clientService = new BillClientService();
                $clientService->client_id = $clientCrmId;
                $clientService->service_id = $serviceCrmId->service_id;
                $clientService->date_start = $formatter->asTimestamp(isset($item->date1) ? $item->date1 : 0);
                $clientService->date_end = $formatter->asTimestamp(isset($item->date2) ? $item->date2 : 0);
                $clientService->status = BillStatusList::STATUS_ACTIVE;
                $clientService->external_id = $item->id;
                $clientService->billing_id = Billings::BILLING_BGBILLING;
                $clientService->create_date = time();
                $clientService->created_by_user = 1;
                if ($clientService->save()) {
                    $importItem++;
                }
            } catch (Exception $e) {
                Console::stdout(Helper::currentTime() . ' Ошибка!. ' . $e->getMessage() . PHP_EOL);
            }
        }
        Console::stdout(Helper::currentTime() . ' Импорт окончен.' . PHP_EOL);
        Console::stdout('Импортировано ' . $importItem . ' связей.' . PHP_EOL);
    }

    /**
     * Импорт связей Клиент - Услуги (Прочие услуги)
     */
    private function importClientServiceOther()
    {
        $formatter = new Formatter();
        $contractsService = BgbDbRscmServiceAccount4::find()->all();

        $countItem = 0;
        $readItem = 0;
        $importItem = 0;
        if (!empty($contractsService)) {
            $countItem = count($contractsService);
        }
        Console::stdout(Helper::currentTime() . ' Старт импорта связей Клиент - Услуги (Прочие услуги).' . PHP_EOL);
        Console::stdout('Необходимо обработать ' . $countItem . ' записей.' . PHP_EOL);

        foreach ($contractsService as $item) {
            if ($readItem % 10 === 0) {
                Console::stdout('Обработано ' . $readItem . ' записей. Из ' . $countItem . PHP_EOL);
            }
            $readItem++;

            $clientCrmId = ClientDetail::getClientByLS($item->cid);
            if ($clientCrmId === 0) {
                Console::stdout(' Ошибка!. Нет клиента с ИД ' . $item->cid . PHP_EOL);
                continue;
            }

            if (!Client::find()->where([
                'client_id' => $clientCrmId,
                'client_type' => ClientTypeList::TYPES_FOR_LEGAL
            ])->exists()) {
                Console::stdout(' Ошибка!. Клиент с ИД ' . $clientCrmId . ' НЕ юр. лицо!' . PHP_EOL);
                continue;
            }

            $serviceBgB = BgbDbService::findOne($item->sid);
            $serviceCrmId = isset($serviceBgB) ? BillService::getByTitleAndGroupId($serviceBgB->title, $serviceBgB->mid) : null;
            if (!isset($serviceCrmId)) {
                Console::stdout(' Ошибка!. Данной услуги нет в СРМ. Услуга в БГБ ' . $item->sid . PHP_EOL);
                continue;
            }

            if (BillClientService::find()
                ->where([
                    'client_id' => $clientCrmId,
                    'service_id' => $serviceCrmId->service_id,
                    'date_start' => $formatter->asTimestamp(isset($item->date) ? $item->date : 0),
                    'date_end' => $formatter->asTimestamp(0),
                    'status' => BillStatusList::STATUS_ACTIVE,
                    'external_id' => $item->id,
                    'billing_id' => Billings::BILLING_BGBILLING,
                ])->exists()) {
                continue;
            }

            try {
                $clientService = new BillClientService();
                $clientService->client_id = $clientCrmId;
                $clientService->service_id = $serviceCrmId->service_id;
                $clientService->date_start = $formatter->asTimestamp(isset($item->date) ? $item->date : 0);
                $clientService->date_end = $formatter->asTimestamp(0);
                $clientService->status = BillStatusList::STATUS_ACTIVE;
                $clientService->external_id = $item->id;
                $clientService->billing_id = Billings::BILLING_BGBILLING;
                $clientService->create_date = time();
                $clientService->created_by_user = 1;
                if ($clientService->save()) {
                    $importItem++;
                }
            } catch (Exception $e) {
                Console::stdout(Helper::currentTime() . ' Ошибка!. ' . $e->getMessage() . PHP_EOL);
            }
        }
        Console::stdout(Helper::currentTime() . ' Импорт окончен.' . PHP_EOL);
        Console::stdout('Импортировано ' . $importItem . ' связей.' . PHP_EOL);
    }

    /**
     * Импорт связей Клиент - Тип документа
     */
    private function importClientInvoiceType()
    {
        $contractInvoiceType = BgbDbBillContractDocType7::find()->all();

        $countItem = 0;
        $readItem = 0;
        $importItem = 0;
        if (!empty($contractInvoiceType)) {
            $countItem = count($contractInvoiceType);
        }
        Console::stdout(Helper::currentTime() . ' Старт импорта связей Клиент - Тип документа.' . PHP_EOL);
        Console::stdout('Необходимо обработать ' . $countItem . ' записей.' . PHP_EOL);

        foreach ($contractInvoiceType as $item) {
            if ($readItem % 10 === 0) {
                Console::stdout('Обработано ' . $readItem . ' записей. Из ' . $countItem . PHP_EOL);
            }
            $readItem++;

            $clientCrmId = ClientDetail::getClientByLS($item->cid);
            if ($clientCrmId === 0) {
                Console::stdout(' Ошибка!. Нет клиента с ИД ' . $item->cid . PHP_EOL);
                continue;
            }

            if (!Client::find()->where([
                'client_id' => $clientCrmId,
                'client_type' => ClientTypeList::TYPES_FOR_LEGAL
            ])->exists()) {
                Console::stdout(' Ошибка!. Клиент с ИД ' . $clientCrmId . ' НЕ юр. лицо!' . PHP_EOL);
                continue;
            }

            $docTypeBgB = BgbDbBillDocType7::findOne($item->doc_type);
            $invoiceTypeCrmId = isset($docTypeBgB) ? InvoiceTypeList::getTypeByDescr($docTypeBgB->title, $docTypeBgB->type) : null;
            if (!isset($invoiceTypeCrmId)) {
                Console::stdout(' Ошибка!. Данного типа документов нет в СРМ. Тип документа в БГБ ' . $item->doc_type . PHP_EOL);
                continue;
            }

            if (BillClientInvoiceType::find()
                ->where([
                    'client_id' => $clientCrmId,
                    'invoice_type_id' => $invoiceTypeCrmId,
                    'status' => BillStatusList::STATUS_ACTIVE,
                    'external_id' => $item->id,
                    'billing_id' => Billings::BILLING_BGBILLING,
                ])->exists()) {
                continue;
            }

            try {
                $clientInvoiceType = new BillClientInvoiceType();
                $clientInvoiceType->client_id = $clientCrmId;
                $clientInvoiceType->invoice_type_id = $invoiceTypeCrmId;
                $clientInvoiceType->status = BillStatusList::STATUS_ACTIVE;
                $clientInvoiceType->external_id = $item->id;
                $clientInvoiceType->billing_id = Billings::BILLING_BGBILLING;
                $clientInvoiceType->create_date = time();
                $clientInvoiceType->created_by_user = 1;
                if ($clientInvoiceType->save()) {
                    $importItem++;
                }
            } catch (Exception $e) {
                Console::stdout(Helper::currentTime() . ' Ошибка!. ' . $e->getMessage() . PHP_EOL);
            }
        }
        Console::stdout(Helper::currentTime() . ' Импорт окончен.' . PHP_EOL);
        Console::stdout('Импортировано ' . $importItem . ' связей.' . PHP_EOL);
    }


    /**
     * Импорт связей Тип документа - Услуги
     */
    private function importInvoiceTypeService()
    {
        $configService = $this->parseModuleBillConfig();

        if (!isset($configService)) {
            Console::stdout(' Ошибка парсинга конфигурации модуля Бухгалтерия ' . PHP_EOL);
            return false;
        }

        $invoiceType = BgbDbBillDocType7::find()->all();

        $countItem = 0;
        $readItem = 0;
        $importItem = 0;
        if (!empty($invoiceType)) {
            $countItem = count($invoiceType);
        }
        Console::stdout(Helper::currentTime() . ' Старт импорта связей Тип документа - Услуги.' . PHP_EOL);
        Console::stdout('Необходимо обработать ' . $countItem . ' записей.' . PHP_EOL);

        foreach ($invoiceType as $item) {
            if ($readItem % 10 === 0) {
                Console::stdout('Обработано ' . $readItem . ' записей. Из ' . $countItem . PHP_EOL);
            }
            $readItem++;

            $configType = ((int)$item->type === InvoiceTypeListTypeList::TYPE_INVOICE) ? BgbDbModuleConfig::TYPE_INVOICE : BgbDbModuleConfig::TYPE_ACT;

            $invoiceTypeCrmId = InvoiceTypeList::getTypeByDescr($item->title, $item->type);
            if (!isset($invoiceTypeCrmId)) {
                Console::stdout(' Ошибка!. Данного типа документов нет в СРМ. Тип документа в БГБ ' . $item->id . PHP_EOL);
                continue;
            }

            /**
             * В БГБ каждая запись с типом документа содержит список (поле pos_list),
             * в котором содержатся порядковые номера Записей в конфиге модуля Бухгалтерия (таблица module_config).
             * Каждая запись хранит ID услуги из таблицы Service
             */
            $itemPosList = explode(",", $item->pos_list);
            foreach ($itemPosList as $posItem) {

                $serviceBgb = BgbDbService::getServiceById(isset($configService[$configType][(int)$posItem]) ? $configService[$configType][(int)$posItem] : 0);
                if (!isset($serviceBgb)) {
                    continue;
                }
                $titleModuleBgb = BgbDbModule::getTitleById($serviceBgb->mid);
                if (!isset($titleModuleBgb)) {
                    continue;
                }
                $groupIdCrm = BillServiceGroupList::getIdByTitle($titleModuleBgb);
                if (!isset($groupIdCrm)) {
                    continue;
                }
                $serviceCRM = BillService::getByTitleAndGroupId($serviceBgb->title, $groupIdCrm);
                if (!isset($serviceCRM)) {
                    continue;
                }

                if (BillInvoiceTypeServices::find()
                    ->where([
                        'invoice_type_id' => $invoiceTypeCrmId,
                        'service_id' => $serviceCRM->service_id,
                        'status' => BillStatusList::STATUS_ACTIVE,
                    ])->exists()) {
                    continue;
                }

                try {
                    $invoiceTypeService = new BillInvoiceTypeServices();
                    $invoiceTypeService->invoice_type_id = $invoiceTypeCrmId;
                    $invoiceTypeService->service_id = $serviceCRM->service_id;
                    $invoiceTypeService->status = BillStatusList::STATUS_ACTIVE;
                    $invoiceTypeService->create_date = time();
                    $invoiceTypeService->created_by_user = 1;
                    if ($invoiceTypeService->save()) {
                        $importItem++;
                    }

                } catch (Exception $e) {
                    Console::stdout(Helper::currentTime() . ' Ошибка!. ' . $e->getMessage() . PHP_EOL);
                }
            }
        }
        Console::stdout(Helper::currentTime() . ' Импорт окончен.' . PHP_EOL);
        Console::stdout('Импортировано ' . $importItem . ' связей.' . PHP_EOL);
    }

    /**
     * Парсинг конфигурации модуля Бухгалтерия
     * Получаем массив:
     * - ключ - позиция в конфигурации модуля (на нее ссылаются Типы документов)
     * - значение - ИД услуги в таблице Service БГБ
     *
     * @return array
     */
    private function parseModuleBillConfig()
    {
        $config = BgbDbModuleConfig::find()
            ->select('config')
            ->where([
                'mid' => BgbDbModule::MODULE_BILL,
            ])
            ->scalar();
        /**
         * Разбивает конфигурационный текст на строки,
         * Исключает закомментированные строки
         * Выбирает строки содержащие необходимую информацию
         * пр. "bill.pos.1.summ=SERVICE_ACCOUNT($month, 4)" || "invoice.pos.1.summ=SERVICE_ACCOUNT($month, 4)"
         * Далее получаем:
         *  - Значение Позиции ({'bill_doc_type_7'}) - целое число слева от знака равенства;
         *  - ИД Услуги ({'service'}) - целое число справа от знака равенства.
         * Результат возвращаем в виде массива значений для АКТов и Счетов
         */
        $stringArr = explode(PHP_EOL, $config);
        $parseArr = [];
        foreach ($stringArr as $item) {
            if (strpos($item, '#') !== false) {
                continue;
            }
            if ((strpos($item, BgbDbModuleConfig::TYPE_INVOICE) !== false || strpos($item, BgbDbModuleConfig::TYPE_ACT) !== false) && strpos($item, 'summ') !== false) {
                parse_str($item, $parseArr[]);
            }
        }

        $resultArr = [];
        foreach ($parseArr as $key => $value) {
            if (strpos(key($value), BgbDbModuleConfig::TYPE_INVOICE) !== false) {
                $resultArr[BgbDbModuleConfig::TYPE_INVOICE][filter_var(key($value), FILTER_SANITIZE_NUMBER_INT)] =
                    filter_var($value[key($value)], FILTER_SANITIZE_NUMBER_INT);
            }

            if (strpos(key($value), BgbDbModuleConfig::TYPE_ACT) !== false) {
                $resultArr[BgbDbModuleConfig::TYPE_ACT][filter_var(key($value), FILTER_SANITIZE_NUMBER_INT)] =
                    filter_var($value[key($value)], FILTER_SANITIZE_NUMBER_INT);
            }
        }
        return $resultArr;
    }
}