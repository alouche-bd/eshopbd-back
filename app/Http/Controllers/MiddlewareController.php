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
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class MiddlewareController extends Controller
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
     * Obtain the full list of shippingAddresses from the middleware service
     * @return JsonResponse
     */
    public function getShippingAddresses(string $clientCode)
    {
        try {
            $url = '/api/lireAdressesLivraison/' . env('BASE_MIDDLEWARE') . '/' . $clientCode;

            return $this->successResponse($this->performRequest('GET', $url));

        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain the full list of bills from the middleware service
     * @return JsonResponse
     */
    public function getBills(string $clientCode)
    {
        try {
            $url = '/api/lireFacturesListe/' . env('BASE_MIDDLEWARE') . '/' . $clientCode;

            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    /**
     * Obtain the full list of bills and their payment status from the middleware service
     * @return JsonResponse
     */
    public function getBillsAndStatus(string $clientCode)
    {
        try {
            $urlBills = '/api/lireFacturesListe/' . env('BASE_MIDDLEWARE') . '/' . $clientCode;
            $bills = json_decode($this->performRequest('GET', $urlBills), true);

            if ($bills['success'] === false || $bills['nbresultats'] === 0) {
                return $this->errorResponse('Aucune facture ne peut être affichée.', Response::HTTP_NOT_FOUND);
            }

            $bills = $bills['documents']['document'];

            $billsAndStatus = [];

            foreach ($bills as $bill) {
                $bill['status'] = json_decode($this->getBillingStatus($bill['numero']), true)['statut'];
                array_push($billsAndStatus, $bill);
            }

            return $this->successResponse(json_encode($billsAndStatus));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain payment status of a bill
     * @return JsonResponse
     */
    public function getBillingStatus(string $billNumber)
    {
        try {
            $url = '/api/lireFactureInfoComptable/' . env('BASE_MIDDLEWARE') . '/' . $billNumber;
            return $this->successResponse($this->performRequest('GET', $url));

        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    /**
     * Obtain the lines of a given order from the middleware service
     * @return JsonResponse
     */
    public function getOrderLines(string $clientCode, string $billNumber)
    {
        try {
            $url = '/api/lireCommandesLignes/' . env('BASE_MIDDLEWARE') . '/' . $clientCode . '/' . $billNumber;
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain the full list of credits from the middleware service
     * @return JsonResponse
     */
    public function getCredits(string $clientCode)
    {
        try {
            $url = '/api/lireCreditsDocuments/' . env('BASE_MIDDLEWARE') . '/' . $clientCode;
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain the full sold of each credits for a given client from the middleware service
     * @return JsonResponse
     */
    public function getCreditsSold(string $clientCode)
    {
        try {
            $url = '/api/lireCredits/' . env('BASE_MIDDLEWARE') . '/' . $clientCode;
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain a bill in pdf from the middleware service
     * @return JsonResponse
     */
    public function getBillInPdf(string $clientCode, $billNumber)
    {
        try {
            $url = '/api/factureInPdfSecure/' . $billNumber. '/' . $clientCode . '/fr/true/true/true';
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain the lines of a given bill from the middleware service
     * @return JsonResponse
     */
    public function getBillLines(string $clientCode, string $billNumber)
    {
        try {
            $url = '/api/lireFacturesLignes/' . env('BASE_MIDDLEWARE') . '/' . $clientCode . '/' . $billNumber;
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain a credit in pdf from the middleware service
     * @return JsonResponse
     */
    public function getCreditInPdf(string $clientCode, $billNumber)
    {
        $lines = $this->getCreditLines($clientCode, $billNumber);

        $content = json_decode($lines, true);

        $options = ['defaultFont' => 'helvetica', 'isRemoteEnabled' => true, 'enable_php' => true];

        $data = [
            'numero' => $billNumber,
            'docCredits' => $content,
            'codeClient' => $clientCode,
        ];

        $pdf = PDF::loadView('pdf.creditDetails', compact('data'));
        $pdf->setOptions($options);
        $pdf->setPaper('A4', 'portrait');

        $output = base64_encode($pdf->output());

        return response()->json(['success' => 1, 'credit' => $output]);
    }

    /**
     * Obtain the lines of a given credit from the middleware service
     * @return JsonResponse
     */
    public function getCreditLines(string $clientCode, string $billNumber)
    {
        try {
            $url = '/api/lireCreditsDocumentsLignes/' . env('BASE_MIDDLEWARE') . '/' . $clientCode . '/' . $billNumber;
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain the repartition of products inside a cart from the middleware service
     * @return JsonResponse
     */
    public
    function postAddress(Request $request)
    {
        try {
            $params = $request->all();

            $url = $this->baseUri . '/api/UtiliseX3GenericWSSoap';

            $updateAddress = [
                "action" => "ZBPDLVADDR",
                "base" => "BIOTECH",
                "donnees" => "B;" . $params['clientCode'] . ";" . $params['addressNumber'] . "|A;" . $params['addressNumber'] . ";" . $params['designation'] . ";" . $params['address'] . ";" . $params['address2'] . ";" . $params['address3'] . ";" . $params['zipCode'] . ";" . $params['city'] . ";FR;FR;" . $params['phone'] . ";;Non|D;CRAP;" . $params['clientCode'] . ";TNT;" . $params['addressNumber'] . "|END"];
            $promise = Http::withToken("B3n5Hs4cBtEw349P4u3bPyK773bYYbQf")->async()->withOptions(["verify" => false])->withBody(json_encode($updateAddress), 'application/json')->post($url);

            $response = $promise->wait();

            return response()->json(['success' => 1, 'address' => $response->getBody()->getContents()], 200);
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Post order to adv from the middleware service.
     *
     * Non-FR users get routed to the ADV International workflow instead of
     * the direct Sage push: the order is persisted locally, an Excel export
     * is generated and emailed to ADV_INTER, and ADV staff then forward it
     * to Sage X3 from /adv-inter/orders. FR users keep the unchanged direct
     * /api/creerCommandesWeb path.
     */
    public
    function postOrder(Request $request)
    {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $billingCountry = strtoupper((string) ($user?->billing_country_code ?? ''));
            $isNonFr = $billingCountry !== '' && $billingCountry !== 'FR';

            if ($isNonFr) {
                $diversion = $this->routeOrderToAdvInter($request, $user);
                if ($diversion !== null) {
                    return $diversion;
                }
            }

            $params = $request->all();

            $url = $this->baseUri . '/api/creerCommandesWeb';

            $promise = Http::withToken("B3n5Hs4cBtEw349P4u3bPyK773bYYbQf")->async()->withOptions(["verify" => false])->withBody(json_encode($params), 'application/json')->put($url);

            $response = $promise->wait();

            if ($request->get('smileyamount') > 0) {
                $params = [

                    "act" => "debit",
                    "amount" => $request->get('smileyamount'),
                    "buyer" => [
                        "uuid" => env('PLATFORM_UUID')
                    ]
                ];

                $this->consumeSmileys($params, $request->get('uuid'));
            }
            return response()->json(['success' => 1, 'address' => $response->getBody()->getContents()], 200);
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Persist the order locally, generate the Excel export, and email ADV.
     *
     * Returns the JsonResponse to short-circuit the regular Sage push, or
     * null if persistence failed (in which case postOrder continues with the
     * direct middleware push as a fallback — better to over-deliver than to
     * lose the order entirely).
     */
    private function routeOrderToAdvInter(Request $request, $user)
    {
        try {
            $panier  = (array) $request->input('panier', []);
            if (empty($panier)) {
                return null;
            }

            $clientCode = (string) ($request->input('clientcode') ?: $user->codeclientGC);
            $clientName = (string) ($request->input('clientrs')   ?: $user->raisonsociale);

            $billing  = is_array($user->sage_facturation_address ?? null) ? $user->sage_facturation_address : null;
            $delivery = [
                'code'       => (string) $request->input('adresselivraisoncode', 'L0'),
                'codepostal' => (string) $request->input('codepostal', ''),
                'codepays'   => strtoupper((string) ($user->billing_country_code ?? '')),
            ];

            $order = \Illuminate\Support\Facades\DB::transaction(function () use ($user, $request, $clientCode, $clientName, $billing, $delivery, $panier) {
                $order = \App\Models\Order::create([
                    'user_id'              => $user->id,
                    'order_type'           => \App\Constants\OrderType::DISTRIBUTOR,
                    'client_code'          => $clientCode,
                    'raison_sociale'       => $clientName,
                    'finalClientCode'      => $clientCode,
                    'finalClient'          => $clientName,
                    'shippingAddress'      => (string) $request->input('adresselivraisoncode', ''),
                    'currency'             => (string) ($user->currency ?? 'EUR'),
                    'billing_country_code' => $user->billing_country_code,
                    'billing_address'      => $billing,
                    'delivery_address'     => $delivery,
                    'export_status'        => \App\Constants\OrderExportStatus::PENDING,
                ]);

                foreach ($panier as $line) {
                    \App\Models\Product::create([
                        'order_id'     => $order->id,
                        'reference'    => (string) ($line['article'] ?? ''),
                        'designation'  => $line['designation'] ?? null,
                        'sales_unit'   => 'UN',
                        'cartQuantity' => (int) ($line['quantite'] ?? 0),
                        'comment'      => $line['commentaire'] ?? null,
                    ]);
                }

                $order->customer_reference = $order->generateCustomerReference();
                $order->save();
                return $order;
            });

            try {
                $excelService = app(\App\Services\Distributor\ExcelOrderExportService::class);
                $excelPath    = $excelService->generate($order->fresh('product'));
                $recipient    = config('x3.adv_inter_email');
                if ($recipient) {
                    \Illuminate\Support\Facades\Mail::to($recipient)
                        ->send(new \App\Mail\AdvInterOrderMailer($order, $excelPath));
                }
                $order->update([
                    'export_status' => \App\Constants\OrderExportStatus::EXPORTED,
                    'exported_at'   => \Illuminate\Support\Carbon::now(),
                ]);
            } catch (Exception $e) {
                \Illuminate\Support\Facades\Log::error('MiddlewareController: ADV diversion excel/email failed', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
                $order->update(['export_error' => substr($e->getMessage(), 0, 1000)]);
            }

            return response()->json([
                'success'        => 1,
                'routedToAdv'    => true,
                'order_id'       => $order->id,
                'customer_reference' => $order->customer_reference,
            ], 200);
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('MiddlewareController: ADV diversion failed, falling back to direct push', [
                'error' => $e->getMessage(),
            ]);
            return null;  // fall back to the regular /creerCommandesWeb push
        }
    }

    /**
     * Obtain the catalogue of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function consumeSmileys($params, $uuid)
    {

        try {
            $url = $this->baseS4A . 'smileys/consume/' . $uuid;

            $promise = Http::withToken($this->getToken())->withOptions(["verify" => false])->withBody(json_encode($params), 'application/json')->post($url);

            return $promise->getBody()->getContents();
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain the catalogue of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function getToken()
    {
        try {
            $url = $this->baseoAuth . 'auth/realms/s4a/protocol/openid-connect/token';


            $promise = Http::asForm()->withOptions(["verify" => false])->post($url, [
                'client_id' => $this->idOAuth,
                'client_secret' => $this->secretOAuth,
                'grant_type' => 'client_credentials'
            ]);

            $response = $promise->getBody()->getContents();

            $array = json_decode($response, true);

            return $array['access_token'];
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    public function postRepartion(Request $request)
    {
        try {
            $params = $request->all();

            $url = $this->baseUri . '/api/lireRepartitionArticles';

            $promise = Http::withToken("B3n5Hs4cBtEw349P4u3bPyK773bYYbQf")->async()->withOptions(["verify" => false])->withBody(json_encode($params), 'application/json')->post($url);

            $response = $promise->wait();

            $repartition = json_decode($response->getBody()->getContents(), true);

            $articlesRepartitions = $repartition['articles']['article'];

            $articlesRepartitions = array_filter($articlesRepartitions, function ($var) {
                return ($var['valeurdefaut'] == true);
            });

            $articlesRepartitions = array_values($articlesRepartitions);

            $urlDevis = $this->baseUri . '/api/lireTarifClientsParArticleEshop';

            $newRequest = [];

            $newRequest['base'] = $params['nomBaseSAGE'];

            $newRequest['client'] = $params['codeclient'];

            $articles = $params['articles']['article'];

            foreach ( $articles as $k=>$v )
            {
                $articles[$k]['codearticle'] = $articles[$k]['code'];
                unset($articles[$k]['code']);
            }

            $newRequest['articles'] = $articles;

            $secondPromise = Http::withToken("B3n5Hs4cBtEw349P4u3bPyK773bYYbQf")->async()->withOptions(["verify" => false])->withBody(json_encode($newRequest), 'application/json')->post($urlDevis);

            $newResponse = $secondPromise->wait();

            $decodedNewResponse = json_decode($newResponse->getBody()->getContents(), true);

            if ($decodedNewResponse['success'] === true) {

                $prices = $decodedNewResponse['articles'];

                $devisAndRepartition = array_merge($prices, $articlesRepartitions);
                $devisAndRepartition = array_values(array_reduce($devisAndRepartition, function ($rows, $item) {
                    if (array_key_exists('code', $item) && is_scalar($item['code'])) {
                        $rows = array_replace_recursive($rows ?? [], [$item['code'] => $item]);
                    }
                    return $rows;
                }));

                return response()->json(['success' => 1, 'products' => $devisAndRepartition], 200);

            }
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain the repartition of products inside a cart from the middleware service
     * @return JsonResponse
     */
    public
    function old(Request $request)
    {
        try{
            $params = $request->all();

            $url = $this->baseUri . '/api/lireRepartitionArticles';

            $promise = Http::withToken("B3n5Hs4cBtEw349P4u3bPyK773bYYbQf")->async()->withOptions(["verify" => false])->withBody(json_encode($params), 'application/json')->post($url);

            $response = $promise->wait();

            $repartition = json_decode($response->getBody()->getContents(), true);
            $articlesRepartitions = $repartition['articles']['article'];
            $articlesRepartitions = array_filter($articlesRepartitions, function ($var) {
                return ($var['valeurdefaut'] == true);
            });
            $articlesRepartitions = array_values($articlesRepartitions);
            $joinLine = $this->getJoindedLines($articlesRepartitions);
            $devisQuery = [
                "action" => "ZSQH",
                "donnees" => "E;CRAP;SQN;;" . $repartition['codeclient'] . ";" . date("Ymd") . ";WSEC" . date("Ymd") . ";CRAP;EUR;;;;;|" . $joinLine . "END"
            ];

            $urlDevis = $this->baseUri . '/api/UtiliseX3GenericWSSoap?act=UtiliseX3GenericWSSoap&base=BIOTECH';

            $promise = Http::withToken("B3n5Hs4cBtEw349P4u3bPyK773bYYbQf")->async()->withOptions(["verify" => false])->withBody(json_encode($devisQuery), 'application/json')->post($urlDevis);

            $responseDevis = $promise->wait();
            $devis = json_decode($responseDevis->getBody()->getContents(), true);

            if ($devis['success'] === true) {

                $response = $devis['reponse'];
                $devisIndex = array_search("1", array_column($response, 'type'));
                $devisNumber = str_replace("|Création de ", "", $response[$devisIndex]['message']);
                $devisLines = $this->getQuoteLines($repartition['codeclient'], $devisNumber);
                $lines = json_decode($devisLines->getContent(), true);
                $articleDevis = $lines['devis']['devis'][0]['articles']['article'];
                $devisAndRepartition = array_merge($articleDevis, $articlesRepartitions);
                $devisAndRepartition = array_values(array_reduce($devisAndRepartition, function ($rows, $item) {
                    if (array_key_exists('code', $item) && is_scalar($item['code'])) {
                        $rows = array_replace_recursive($rows ?? [], [$item['code'] => $item]);
                    }
                    return $rows;
                }));

                return response()->json(['success' => 1, 'products' => $devisAndRepartition], 200);

            }
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public
    function getJoindedLines($cart)
    {
        $joinLine = "";
        for ($i = 0; $i < count($cart); $i++) {
            $joinLine = $joinLine . "L;" . $cart[$i]['code'] . ";UN;" . $cart[$i]['quantite'] . ";;|";
        }

        return $joinLine;
    }

    /**
     * Obtain the lines of a given quote from the middleware service
     * @return JsonResponse
     */
    public function getQuoteLines(string $clientCode, string $quoteNumber)
    {
        try {
            $url = '/api/lireDevisLignes/' . env('BASE_MIDDLEWARE') . '/' . $clientCode . '/' . $quoteNumber;
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Post or Get documents from x3 inside a cart from the middleware service
     * @return JsonResponse
     */
    public
    function postAndGetDocument($params)
    {
        try {
            $url = $this->baseUri . '/api/UtiliseX3GenericWSSoap';

            $promise = Http::withToken("B3n5Hs4cBtEw349P4u3bPyK773bYYbQf")->async()->withOptions(["verify" => false])->withBody(json_encode($params), 'application/json')->post($url);

            $response = $promise->wait();

            return $response->getBody()->getContents();

        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    function array_filter_by_value($my_array, $index, $value): array
    {
        $new_array = [];
        if(is_array($my_array) && count($my_array)>0)
        {
            foreach(array_keys($my_array) as $key){
                $temp[$key] = $my_array[$key][$index];

                if ($temp[$key] == $value){
                    $new_array[$key] = $my_array[$key];
                }
            }
        }
        return $new_array;
    }
    /**
     * Obtain the full list of ungoing shipments from the middleware service
     * @return JsonResponse
     */
    public
    function getShipments(string $clientCode)
    {
        try {
            $url = '/api/lireCommandes/' . env('BASE_MIDDLEWARE') . '/' . $clientCode;
            $sageOrder = $this->successResponse($this->performRequest('GET', $url));

            $decodedSageOrder = json_decode($sageOrder->content(), true);

            if($decodedSageOrder['nbresultats'] > 0){
                $decodedSageOrder = $decodedSageOrder['documents']['document'];
            }


            $urlWeb = '/api/lireCommandesWeb/' . $clientCode;
            $webOrder = $this->successResponse($this->performRequest('GET', $urlWeb));

            $decodedWebOrder = json_decode($webOrder->content(), true);

            if($decodedWebOrder['nbresultats'] > 0){

                $decodedWebOrder = $decodedWebOrder['commandesWeb']['commandeWeb'];


                $webOrderPending = $this->array_filter_by_value($decodedWebOrder, 'status', 0);

                $returnedOrders = [];

                foreach ($webOrderPending as $order) {
                    $newOrder = [];

                    $date = new \DateTime($order['createdAt']);

                    $newOrder['numero'] = 'WEB' . $order['id'];
                    $newOrder['date'] =  $date->format('Y-m-d');
                    $newOrder['etat'] = 'En cours de validation';
                    $newOrder['totalht'] = '-';
                    $newOrder['totalttc'] = '-';
                    $newOrder['web'] = 1;
                    $newOrder['webId'] = $order['id'];

                    array_push($returnedOrders, $newOrder);
                }

                $mergedOrders = array_merge($decodedSageOrder, $returnedOrders);

                usort($mergedOrders, function($a, $b) {
                    return new \DateTime($b['date']) <=> new \DateTime($a['date']);
                });

                return $this->successResponse(json_encode($mergedOrders));
            }else{
                return $this->successResponse(json_encode($decodedSageOrder));
            }

        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Obtain the full list of ungoing shipments from the middleware service
     * @return JsonResponse
     */
    public
    function getShipmentWeb(string $clientCode, string $id)
    {
        try {

            $url = '/api/lireCommandesWeb/' . $clientCode;
            $webOrder = $this->successResponse($this->performRequest('GET', $url));

            $decodedWebOrder = json_decode($webOrder->content(), true);

            if($decodedWebOrder['nbresultats'] > 0){
                $webId = ltrim($id, 'WEB');

                $decodedWebOrder = $decodedWebOrder['commandesWeb']['commandeWeb'];
               $orderKey = array_search($webId, array_column($decodedWebOrder, 'id'));

                return response($decodedWebOrder[$orderKey], 200);
            }

        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    /**
     * Obtain the full list of products from the middleware service
     * @return JsonResponse
     */
    public
    function getAllProducts()
    {
        try {
            $url = '/api/lireArticles/' . env('BASE_MIDDLEWARE');
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain the full list of products by catalogue from the middleware service
     * @return JsonResponse
     */
    public
    function getProductsByCatalogue(
        string $CatNiv1,
        string $CatNiv2 = null,
        string $CatNiv3 = null,
        string $CatNiv4 = null,
        string $CatNiv5 = null,
        string $CatNiv6 = null,
        string $CatNiv7 = null
    )
    {
        try {
            $params = ['nomBaseSAGE' => env('BASE_MIDDLEWARE'), 'indiceCatalogue' => '1', 'CatNiv1' => urldecode($CatNiv1), "CatNiv2" => urldecode($CatNiv2), "CatNiv3" => urldecode($CatNiv3), "CatNiv4" => urldecode($CatNiv4), "CatNiv5" => urldecode($CatNiv5), "CatNiv6" => urldecode($CatNiv6), "CatNiv7" => urldecode($CatNiv7)];

            $url = $this->baseUri . '/api/lireArticlesParCatalogueMarketingV2';

            $promise = Http::withToken("B3n5Hs4cBtEw349P4u3bPyK773bYYbQf")->async()->withOptions(["verify" => false])->withBody(json_encode($params), 'application/json')->post($url);

            $response = $promise->wait();

            return $response->getBody()->getContents();
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain the full list of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function getSearchProducts($searchQuery)
    {
        try {
            $url = '/api/lireArticlesParRechContientMarketNiv1Filter/' . env('BASE_MIDDLEWARE') . '/' . $searchQuery;

            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain the full list of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function getSearchProductsByCode($searchQuery)
    {
        try {
            $url = '/api/item/' . env('BASE_MIDDLEWARE') . '/' . $searchQuery;

            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain one product from the middleware service
     * @return JsonResponse
     */
    public
    function getOneProduct($reference)
    {
        try {
            $url = '/api/lireArticle/' . env('BASE_MIDDLEWARE') . '/' . $reference;
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain the full list of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function getTopTenSearchProducts($searchQuery)
    {
        try {
            $url = '/api/lireArticlesParRechContientMarketNiv1Filter/' . env('BASE_MIDDLEWARE') . '/' . $searchQuery;
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain the catalogue of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function getCatalogue()
    {
        $catalogue = Cache::remember('catalogue', Carbon::now()->addMinutes(700), function () {
            $url = '/api/lireCatalogueMarketing/' . env('BASE_MIDDLEWARE') . '/' . '1/';
            $response = $this->performRequest('GET', $url);
            if (json_decode($response, true)['success'] === true) {
                return $response;
            }
        });

        return $catalogue;
    }

    /**
     * Obtain the catalogue of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function getSmileys($uuid)
    {

        try {
            $url = $this->baseS4A . 'smileys/total/' . $uuid;

            $promise = Http::withToken($this->getToken())->withOptions(["verify" => false])->get($url);

            return $promise->getBody()->getContents();
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtain the catalogue of products based on a search query from the middleware service
     * @return JsonResponse
     */
    public
    function postSmileys()
    {

        $url = $this->baseS4A . 'smileys';
        $smiley = [
            [
                "userCreatedBy" => ["uuid" => "fe9a8315-972e-11ec-8fb6-9220da0d1d4e"],
                "amount" => 300,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "fe9a8315-972e-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"]
            ],
            [
                "userCreatedBy" => ["uuid" => "fdc101f5-9724-11ec-8fb6-9220da0d1d4e"],
                "amount" => 400,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"]
            ],
            [
                "userCreatedBy" => ["uuid" => "fbb322d8-9719-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"]
            ],
            [
                "userCreatedBy" => ["uuid" => "fbb322d8-9719-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"]

            ],
            [
                "userCreatedBy" => ["uuid" => "fbb322d8-9719-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"
                ]
            ],
            [
                "userCreatedBy" => ["uuid" => "f8ee30ac-9719-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"
                ]
            ],
            [
                "userCreatedBy" => ["uuid" => "f2490544-9713-11ec-8fb6-9220da0d1d4e"],
                "amount" => 2000,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"
                ]
            ],
            [
                "userCreatedBy" => ["uuid" => "ee1f8695-9722-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"
                ]
            ],
            [
                "userCreatedBy" => ["uuid" => "ee1f8695-9722-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1200,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"
                ]
            ],
            [
                "userCreatedBy" => ["uuid" => "ede83d3f-9718-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"
                ]
            ],
            [
                "userCreatedBy" => ["uuid" => "ede83d3f-9718-11ec-8fb6-9220da0d1d4e"],
                "amount" => 499,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"
                ]
            ],
            [
                "userCreatedBy" => ["uuid" => "e9ef2401-972d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"
                ]
            ],
            [
                "userCreatedBy" => ["uuid" => "e87f2958-9717-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1000,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"
                ]
            ],
            [
                "userCreatedBy" => ["uuid" => "e81574e3-9714-11ec-8fb6-9220da0d1d4e"],
                "amount" => 400,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"
                ]

            ],
            [
                "userCreatedBy" => ["uuid" => "e77f588b-9713-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"
                ]
            ],
            [
                "userCreatedBy" => ["uuid" => "e77f588b-9713-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"
                ]
            ],
            [
                "userCreatedBy" => ["uuid" => "e77f588b-9713-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"
                ]
            ],
            [
                "userCreatedBy" => ["uuid" => "e77f588b-9713-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => [
                    "uuid" => "671b0abb-7733-4405-ad79-f030571bf45"
                ]
            ],
            [
                "userCreatedBy" => ["uuid" => "e4d76675-970f-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"
                ]
            ],
            [
                "userCreatedBy" => ["uuid" => "dda5e85e-9730-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "d7def857-970f-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "d38c435d-972c-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "d34da554-9731-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "d34da554-9731-11ec-8fb6-9220da0d1d4e"],
                "amount" => 799,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "cccbcab9-972c-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "c8a2e551-972d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "c8a2e551-972d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "c4394cad-972d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 400,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "c2a6ec0a-971b-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "c0c3f995-9718-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "c0600eab-9714-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "bc687287-9713-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "bc687287-9713-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "bbe766c5-9724-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "b80889fd-9723-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "b6141194-9714-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "b512b413-9732-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "aa242f8a-9728-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "a57d9c65-971b-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "98c33a16-9719-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "97e125db-970d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "959ff2ab-9714-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "959ff2ab-9714-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "959ff2ab-9714-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "959ff2ab-9714-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "938cc48f-971a-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "938cc48f-971a-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "938cc48f-971a-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "919beaed-971d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "8d23f1bc-9715-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "886f9975-971d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "886f9975-971d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "874e1301-9713-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "849ba996-972b-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1000,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "849ba996-972b-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "849ba996-972b-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1000,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "849ba996-972b-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "849ba996-972b-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1000,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "849ba996-972b-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "83040442-972d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "83040442-972d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "82902859-9716-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "8205eee6-9719-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1000,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "8205eee6-9719-11ec-8fb6-9220da0d1d4e"],
                "amount" => 7000,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "81203cf3-9716-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "7c5a7362-9720-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "76719b30-972d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "76719b30-972d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 499,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "73dafaf8-9718-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "6b9f7c7b-9728-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "60a22fe1-972d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1000,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "5ee360da-9727-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "5ee360da-9727-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "5cf4c759-9730-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "57d02282-9723-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "57d02282-9723-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "57d02282-9723-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "57ce1d0d-9716-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1000,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "57ce1d0d-9716-11ec-8fb6-9220da0d1d4e"],
                "amount" => 700,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "575b5632-9730-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "4de0338e-9733-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "4de0338e-9733-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "4de0338e-9733-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "4de0338e-9733-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1199,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "4de0338e-9733-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1199,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "4dbd2c38-9730-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "472d3864-971a-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "4564481b-9717-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "4564481b-9717-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "4564481b-9717-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "3ec09293-971d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1000,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "3db39ad0-9710-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "3db39ad0-9710-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "361ab900-9723-11ec-8fb6-9220da0d1d4e"],
                "amount" => 400,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "331ead5e-971a-11ec-8fb6-9220da0d1d4e"],
                "amount" => 3000,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "2faeede2-9711-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "2bfd3598-9715-11ec-8fb6-9220da0d1d4e"],
                "amount" => 700,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "2b7a4fbf-971c-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "2b35deb5-9715-11ec-8fb6-9220da0d1d4e"],
                "amount" => 700,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "24d52f22-972f-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "24c8ec9d-972d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1000,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "1bc46ada-9731-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "1b60ece6-9733-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "1a8668db-9720-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "15c1103a-9727-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "154cc65d-971a-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "1316621d-971e-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "1200db27-9714-11ec-8fb6-9220da0d1d4e"],
                "amount" => 200,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "0bddd0e3-9715-11ec-8fb6-9220da0d1d4e"],
                "amount" => 800,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "0bafd0e3-9719-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "0bafd0e3-9719-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1000,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "0b191410-971a-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1700,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "082c690d-9730-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "07d0ef62-9732-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "064b955a-9715-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "04a3308e-972d-11ec-8fb6-9220da0d1d4e"],
                "amount" => 700,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "0282a085-9719-11ec-8fb6-9220da0d1d4e"],
                "amount" => 1000,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ],
            [
                "userCreatedBy" => ["uuid" => "026bb5f5-9718-11ec-8fb6-9220da0d1d4e"],
                "amount" => 500,
                "act" => "credit",
                "seller" => ["uuid" => "671b0abb-7733-4405-ad79-f030571bf454"]
            ]
        ];
        for ($i = 0; $i < count($smiley); $i++) {
            $promise = Http::withToken($this->getToken())->withOptions(["verify" => false])->withBody(json_encode($smiley[$i]), 'application/json')->post($url);

        }
    }


    /**
     * Obtain the top ten products of given catalogue from the middleware service
     * @return JsonResponse
     */
    public
    function getTopTenProductsByCatalogue(string $cat)
    {
        $top10Catalogue = Cache::remember('top10Catalogue:' . $cat, Carbon::now()->addMinutes(700), function () use ($cat) {
            $url = '/api/lireArticlesTop10Catalogue/' . env('BASE_MIDDLEWARE') . '/' . strtoupper($cat);
            $response = $this->performRequest('GET', $url);
            if (json_decode($response, true)['success'] === true) {
                return $response;
            }
        });

        return $top10Catalogue;
    }

    /**
     * Check if email exists in x3 from the middleware service
     * @return JsonResponse
     */
    public
    function getEmailExists(string $email)
    {
        try {
            $url = '/api/lireExisteEmail/' . env('BASE_MIDDLEWARE') . '/' . $email;
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get client infos from the middleware service
     * @return JsonResponse
     */
    public
    function getClientInfos(string $clientCode)
    {
        try {
            $url = '/api/lireInfoClient/' . env('BASE_MIDDLEWARE') . '/' . $clientCode;
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get client infos using the v3 endpoint introduced for the distributor
     * workflow (§1). Centralized base value from config('x3.base').
     */
    public function getClientInfosV3(string $clientCode)
    {
        try {
            $base = config('x3.base');
            $url = '/api/v3/lireInfoClient/' . $base . '/' . $clientCode;
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get client contacts from the middleware service
     * @return JsonResponse
     */
    public
    function getClientContacts(string $clientCode)
    {
        try {
            $url = '/api/lireContactsDepuisClient/' . env('BASE_MIDDLEWARE') . '/' . $clientCode;
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * get partners from client from the middleware service
     * @return JsonResponse
     */
    public
    function postGetPartnerFromClient($params)
    {
        try {
            $url = '/api/lirePartenairesDepuisClient';
            return $this->successResponse($this->performRequest('POST', $url, $params));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * get client from partner from the middleware service
     * @return JsonResponse
     */
    public
    function postGetCLientFromPartner($params)
    {
        try {
            $url = '/api/lireClientsDepuisPartenaireV2';
            return $this->successResponse($this->performRequest('POST', $url, $params));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * post partner the middleware service
     * @return JsonResponse
     */
    public
    function postPartner($params)
    {
        try {
            $url = '/api/creerPartenaire';

            return $this->successResponse($this->performRequest('POST', $url, $params));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * post partnership the middleware service
     * @return JsonResponse
     */
    public
    function postPartnership($params)
    {
        try {
            $url = '/api/creerPartenariat';
            return $this->successResponse($this->performRequest('POST', $url, $params));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get contact function from the middleware service
     * @return JsonResponse
     */
    public
    function getContactFunction()
    {
        try {
            $url = '/api/lireContactsFonctions/' . env('BASE_MIDDLEWARE');
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Unlock client from the middleware service
     * @return JsonResponse
     */
    public
    function getUnlockClient(string $clientCode)
    {
        try {
            $url = '/api/debloqueX3Client/' . env('BASE_MIDDLEWARE') . '/' . $clientCode;
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Unlock client from the middleware service
     * @return JsonResponse
     */
    public
    function getNetworkLinks(string $sfid)
    {
        try {
            $url = '/api/clientsLinkedToLab/' . $sfid;
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * post partnership the middleware service
     * @return JsonResponse
     */
    public
    function getItemsStockLDA($ref)
    {
        try {
            $url = '/api/batchItemsStockLDA/' . $ref;
            return $this->successResponse($this->performRequest('GET', $url));
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Post order to adv from the middleware service
     * @return JsonResponse
     */
    public
    function postOrderLda(Request $request)
    {
        try {
            $params = $request->all();

            $url = $this->baseUri . '/api/createLDAOrders';

            $promise = Http::withToken("B3n5Hs4cBtEw349P4u3bPyK773bYYbQf")->async()->withOptions(["verify" => false])->withBody(json_encode($params), 'application/json')->put($url);

            $response = $promise->wait();

            return response()->json(['success' => 1, 'order' => $response->getBody()->getContents()], 200);
        } catch (Exception $e) {
            return $this->errorResponse('Quelque chose s\'est mal passé.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
