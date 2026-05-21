<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponser;
use App\Traits\ConsumeExternalService;
use Barryvdh\DomPDF\Facade as PDF;
use Cache;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GenerateDataController extends Controller
{
    use ConsumeExternalService;
    use ApiResponser;

    /**
     * The base uri to consume middleware service
     * @var string
     */
    public $baseUri;

    /**
     * The base uri to consume oauth service
     * @var string
     */
    public $baseoAuth;

    /**
     * The base uri to consume oauth service
     * @var string
     */
    public $baseS4A;

    /**
     * The secret to consume oauth service
     * @var string
     */
    public $secretOAuth;

    /**
     * The id to consume oauth service
     * @var string
     */
    public $idOAuth;

    /**
     * The id to consume oauth service
     * @var string
     */
    public $appUuid;

    /**
     * Authorization secret to pass to middleware api
     * @var string
     */
    public $secret;


    const USERS= [
        "65b51cc2-cd0a-11ec-9272-ae7db509ceb8",
        "a9ac10ab-cd0a-11ec-9272-ae7db509ceb8",
        /*"a4edd515-8f5a-11ec-9c42-ae7db509ceb8",
        "af4a4da7-8f49-11ec-9c42-ae7db509ceb8",
        "93073e65-9e2c-11ec-9272-ae7db509ceb8",
        "20cd0ef9-8f92-11ec-9c42-ae7db509ceb8",
        "a526e4cb-8f5a-11ec-9c42-ae7db509ceb8",
        "c9d8fed8-8f55-11ec-9c42-ae7db509ceb8",
        "ac48b170-9e2c-11ec-9272-ae7db509ceb8",
        "d447a8eb-9e2c-11ec-9272-ae7db509ceb8",
        "5895b9c1-8f4f-11ec-9c42-ae7db509ceb8",
        "210c254b-8f92-11ec-9c42-ae7db509ceb8",
        "381e78f7-8f9c-11ec-9c42-ae7db509ceb8",
        "02162c78-9e2d-11ec-9272-ae7db509ceb8",
        "974d7a60-8f4a-11ec-9c42-ae7db509ceb8",
        "861dbfa4-8f50-11ec-9c42-ae7db509ceb8",
        "c1a705d0-9628-11ec-9272-ae7db509ceb8",
        "1cf8bd39-8f93-11ec-9c42-ae7db509ceb8",
        "ff742763-8f92-11ec-9c42-ae7db509ceb8",
        "e62bb057-8f5a-11ec-9c42-ae7db509ceb8",
        "6d16a333-8f4a-11ec-9c42-ae7db509ceb8",
        "1bfe635f-8f93-11ec-9c42-ae7db509ceb8",
        "5e66a914-9e2d-11ec-9272-ae7db509ceb8",
        "c1dfe85d-8f4d-11ec-9c42-ae7db509ceb8",
        "1cba9de2-8f93-11ec-9c42-ae7db509ceb8",
        "e921869f-9624-11ec-9272-ae7db509ceb8",
        "2148c689-8f92-11ec-9c42-ae7db509ceb8",
        "21876781-8f92-11ec-9c42-ae7db509ceb8",
        "c5fef377-8f98-11ec-9c42-ae7db509ceb8",
        "bf516264-8f49-11ec-9c42-ae7db509ceb8",
        "583c6d36-8f6b-11ec-9c42-ae7db509ceb8",
        "da0f0f73-b759-11ec-9272-ae7db509ceb8",
        "f60a5c93-b759-11ec-9272-ae7db509ceb8",
        "1250df9d-b75a-11ec-9272-ae7db509ceb8",
        "3d0ec2b6-b75a-11ec-9272-ae7db509ceb8",
        "b643d553-8f4c-11ec-9c42-ae7db509ceb8",
        '2119fb5a-bff0-11ec-9272-ae7db509ceb8',
        'b336fa9d-c9ed-11ec-9272-ae7db509ceb8'*/
    ];

    const APP = [
        "02771b67-2b50-11ec-8bc9-4622b9253aa2",
        "26eeb83c-9b00-11ec-9272-ae7db509ceb8",
        "e51efeb3-2b4f-11ec-8bc9-4622b9253aa2",
        "ef14e403-2b4f-11ec-8bc9-4622b9253aa2",
        "f1f63cd2-2b4f-11ec-8bc9-4622b9253aa2",
        "fdbbc3e5-3cbc-11ec-b187-00d86109285c",
    ];

    const PARNERS= [
        "90bc2f7a-9582-11ec-9272-ae7db509ceb8",
        "abc2fd3b-9582-11ec-9272-ae7db509ceb8",
        "2c37cf4b-9b00-11ec-9272-ae7db509ceb8",
        "abc2fd3b-9582-11ec-9272-ae7db509ceb8",
    ];

    const PATIENTS = [
        "2e497cff-9583-11ec-9272-ae7db509ceb8",
        "0bb019c3-9583-11ec-9272-ae7db509ceb8",
        "4724b080-9583-11ec-9272-ae7db509ceb8",
        "59c92ca7-8b47-11ec-9c42-ae7db509ceb8",
        "7263df4e-9967-11ec-9272-ae7db509ceb8"
    ];


    const CREDITS = [
        [
            "credit" => "protheses",
            "app" => "02771b67-2b50-11ec-8bc9-4622b9253aa2"],
        [
            "credit" => "implants_ci",
            "app" => "02771b67-2b50-11ec-8bc9-4622b9253aa2"],
        [
            "credit" => "implants_cipl",
            "app" => "02771b67-2b50-11ec-8bc9-4622b9253aa2"],
        [
            "credit" => "implants_cicp",
            "app" => "02771b67-2b50-11ec-8bc9-4622b9253aa2"],
        [
            "credit" => "implants_cin",
            "app" => "02771b67-2b50-11ec-8bc9-4622b9253aa2"],
        [
            "credit" => "chirurgie_guidee",
            "app" => "e51efeb3-2b4f-11ec-8bc9-4622b9253aa2"],
        [
            "credit" => "biotech_dental",
            "app" => "f1f63cd2-2b4f-11ec-8bc9-4622b9253aa2"]

    ];


    public function __construct()
    {
        $this->baseUri = config('services.middleware.base_uri');
        $this->secret = config('services.middleware.secret');
        $this->baseoAuth = config('services.oauth.base_oauth');
        $this->secretOAuth = config('services.oauth.secret_oauth');
        $this->idOAuth = config('services.oauth.id_oauth');
        $this->baseS4A = config('services.s4a.base_uri');
        $this->appUuid = config('service.s4a.app_uuid');
    }

    /**
     * Obtain the catalogue of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function getToken()
    {
        $url = $this->baseoAuth . 'auth/realms/s4a/protocol/openid-connect/token';

        $promise = Http::asForm()->withOptions(["verify" => false])->post($url, [
            'client_id' => $this->idOAuth,
            'client_secret' => $this->secretOAuth,
            'grant_type' => 'client_credentials'
        ]);

        $response = $promise->getBody()->getContents();

        $array = json_decode($response, true);

        return $array['access_token'];

    }

    /**
     * Obtain the catalogue of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function createInvoice()
    {
        for($i=0; $i < count(SELF::APP); $i++) {

            for ($j = 0; $j < count(SELF::USERS); $j++) {
                $arrayVar = [
                    "reference" => "BIOF2201004069",
                    "globalStatus" => "UNKNOWN",
                    "status" => "UNKNOWN",
                    "clientCode" => "SF097798",
                    "clientReference" => "FAC006151",
                    "billingLabel" => "FERRIER CLEMENCE",
                    "billingLine1" => "3 BOULEVARD GAMBETTA",
                    "billingLine2" => null,
                    "billingCity" => "LAMBESC",
                    "billingCountry" => "FRANCE",
                    "billingZipCode" => "13410",
                    "shippingLabel" => "FERRIER CLEMENCE",
                    "shippingLine1" => null,
                    "shippingLine2" => null,
                    "shippingLine3" => null,
                    "shippingCity" => "",
                    "shippingCountry" => "",
                    "shippingZipCode" => "",
                    "metadata" => null,
                    "application" => ["uuid" => SELF::APP[$i]],
                    "invoiceRecipients" => [
                        [
                            "application" => ["uuid" => SELF::APP[$i]],
                            "recipient" => ["uuid" => SELF::USERS[$j]],
                        ],
                    ],
                    "userCreatedBy" => ["uuid" => SELF::USERS[$j]],
                    "paymentType" => "UNKNOWN",
                    "paymentCondition" => "NOPE",
                    "paymentDeadline" => null,
                    "type" => "UNKNOWN",
                    "currentDeliveryDate" => "2022-01-25",
                    "previousDeliveryDate" => "2022-01-25",
                    "createdAt" => "2022-01-25",
                    "url" => null,
                    "invoiceFile" => [
                        "file" => [
                            "fileName" => "FAC006151.pdf",
                            "path" => "",
                            "apiUrl" =>
                                "https://api-galaxy.biotech-dental.com/api/factureGalaxyPdf/0016900002pCdyxAAC/FAC006151",
                        ],
                        "deletedAt" => null,
                    ],
                    "salesLines" => [
                        [
                            "reference" => "KECEEATPU",
                            "designation" => "Transfert pick up pour pilier conique étroit + vis KECEEAVTPU/KECEEAVTPUL",
                            "quantity" => 5,
                            "unitPriceInclTaxes" => 6600,
                            "discount1" => 0,
                            "discount2" => 0,
                            "priceExclTaxes" => 27500,
                            "priceInclTaxes" => 33000,
                            "lot" => null,
                            "comments" => null,
                            "unitVAT" => 5500
                        ],
                        [
                            "reference" => "KPSCC502",
                            "designation" => "Pilier surcoulable droit en CrCo Ø5.0mm H2mm + vis KVP",
                            "quantity" => 1,
                            "unitPriceInclTaxes" => 9400,
                            "discount1" => 0,
                            "discount2" => 0,
                            "priceExclTaxes" => 7833,
                            "priceInclTaxes" => 9400,
                            "lot" => null,
                            "comments" => null,
                            "unitVAT" => 1567
                        ],
                    ],
                    "totalVAT" => 0,
                    "totalPriceExclTaxes" => 900,
                    "totalPriceInclTaxes" => 900,
                ];


                $url = $this->baseS4A . 'invoices';

                $promise = Http::withToken($this->getToken())->withOptions(["verify" => false])->withBody(json_encode($arrayVar), 'application/json')->post($url);
            }
        }
    }

    /**
     * Obtain the catalogue of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function createPartner()
    {
        for($i=0; $i < count(SELF::PARNERS); $i++) {

            for ($j = 0; $j < count(SELF::USERS); $j++) {


                $url = $this->baseS4A . 'users/application-add/' . SELF::USERS[$j] . '/' . SELF::PARNERS[$i];

                $promise = Http::withToken($this->getToken())->withOptions(["verify" => false])->post($url);
            }
        }
    }

    /**
     * Obtain the catalogue of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function addPatient()
    {
        for($i=0; $i < count(SELF::PATIENTS); $i++) {

            for ($j = 0; $j < count(SELF::USERS); $j++) {


                $url = $this->baseS4A . 'users/patient-add/' . SELF::USERS[$j] . '/' . SELF::PATIENTS[$i];

                $promise = Http::withToken($this->getToken())->withOptions(["verify" => false])->post($url);
            }
        }
    }

    /**
     * Obtain the catalogue of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function createOrder()
    {
        for($i=0; $i < count(SELF::APP); $i++) {

            for ($j = 0; $j < count(SELF::USERS); $j++) {
                $arrayVar = [
                    "reference" => "BIOF2201004069",
                    "globalStatus" => "delivered",
                        "status" => "delivered",
                    "clientCode" => "SF097798",
                    "clientReference" => "FAC006151",
                    "billingLabel" => "FERRIER CLEMENCE",
                    "billingLine1" => "3 BOULEVARD GAMBETTA",
                    "billingLine2" => null,
                    "billingCity" => "LAMBESC",
                    "billingCountry" => "FRANCE",
                    "billingZipCode" => "13410",
                    "shippingLabel" => "FERRIER CLEMENCE",
                    "shippingLine1" => null,
                    "shippingLine2" => null,
                    "shippingLine3" => null,
                    "shippingCity" => "",
                    "shippingCountry" => "",
                    "shippingZipCode" => "",
                    "metadata" => null,
                    "application" => ["uuid" => SELF::APP[$i]],
                    "invoiceRecipients" => [
                        [
                            "application" => ["uuid" => SELF::APP[$i]],
                            "recipient" => ["uuid" => SELF::USERS[$j]],
                        ],
                    ],
                    "userCreatedBy" => ["uuid" => SELF::USERS[$j]],
                    "paymentType" => "UNKNOWN",
                    "paymentCondition" => "NOPE",
                    "paymentDeadline" => null,
                    "type" => "UNKNOWN",
                    "currentDeliveryDate" => "2022-01-25",
                    "previousDeliveryDate" => "2022-01-25",
                    "createdAt" => "2022-01-25",
                    "url" => null,
                    "orderFiles" => [
                            [
                                "file" => [
                                "fileName" => "test.png",
                                    "path" => "string",
                                    "apiUrl" =>  "https://api-galaxy.biotech-dental.com/api/factureGalaxyPdf/0016900002pCdyxAAC/FAC006151",
                                ],
                                "deletedAt" => null
                            ]
                        ],
                    "salesLines" => [
                        [
                            "reference" => "KECEEATPU",
                            "designation" => "Transfert pick up pour pilier conique étroit + vis KECEEAVTPU/KECEEAVTPUL",
                            "quantity" => 5,
                            "unitPriceInclTaxes" => 6600,
                            "discount1" => 0,
                            "discount2" => 0,
                            "priceExclTaxes" => 27500,
                            "priceInclTaxes" => 33000,
                            "lot" => null,
                            "comments" => null,
                            "unitVAT" => 5500
                        ],
                        [
                            "reference" => "KPSCC502",
                            "designation" => "Pilier surcoulable droit en CrCo Ø5.0mm H2mm + vis KVP",
                            "quantity" => 1,
                            "unitPriceInclTaxes" => 9400,
                            "discount1" => 0,
                            "discount2" => 0,
                            "priceExclTaxes" => 7833,
                            "priceInclTaxes" => 9400,
                            "lot" => null,
                            "comments" => null,
                            "unitVAT" => 1567
                        ],
                    ],
                    "totalVAT" => 0,
                    "totalPriceExclTaxes" => 42400,
                    "totalPriceInclTaxes" => 42400,
                ];


                $url = $this->baseS4A . 'orders';

                $promise = Http::withToken($this->getToken())->withOptions(["verify" => false])->withBody(json_encode($arrayVar), 'application/json')->post($url);
            }
        }
    }

    /**
     * Obtain the catalogue of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function createSmileys()
    {
        for($i=0; $i < count(SELF::APP); $i++) {

            for ($j = 0; $j < count(SELF::USERS); $j++) {

                $arrayVar = [
                    "act" => "credit",
                    "amount" => rand(20, 500),
                    "userCreatedBy" => ["uuid" => SELF::USERS[$j]],
                    "seller" => ["uuid" => SELF::APP[$i]],
                ];


                $url = $this->baseS4A . 'smileys' ;

                $promise = Http::withToken($this->getToken())->withOptions(["verify" => false])->withBody(json_encode($arrayVar), 'application/json')->post($url);
            }
        }
    }

    /**
     * Obtain the catalogue of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function consumeSmileys()
    {
        for($i=0; $i < 5; $i++) {

            for ($j = 0; $j < count(SELF::USERS); $j++) {

                $arrayVar = [
                    "act" => "debit",
                    "amount" => rand(10, 50),
                    "userCreatedBy" => ["uuid" => SELF::USERS[$j]],
                    "docReference" => "FAC0256" . rand(1, 9),
                    "buyer" => ["uuid" => SELF::APP[$i]],
                ];


                $url = $this->baseS4A . 'smileys/consume/' . SELF::USERS[$j];

                $promise = Http::withToken($this->getToken())->withOptions(["verify" => false])->withBody(json_encode($arrayVar), 'application/json')->post($url);
            }
        }
    }

    /**
     * Obtain the catalogue of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function postCredits()
    {
        for($i=0; $i < count(SELF::CREDITS); $i++) {

            for ($j = 0; $j < count(SELF::USERS); $j++) {

                $arrayVar = [
                    "type" => SELF::CREDITS[$i]['credit'],
                    "sold" => rand(0, 2000),
                    "userCreatedBy" => ["uuid" => SELF::USERS[$j]],
                    "application" => [SELF::CREDITS[$i]['app']],
                ];



                $url = $this->baseS4A . 'credits';

                $promise = Http::withToken($this->getToken())->withOptions(["verify" => false])->withBody(json_encode($arrayVar), 'application/json')->post($url);
            }
        }
    }
}
