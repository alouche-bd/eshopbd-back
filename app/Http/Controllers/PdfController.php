<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade as PDF;
use Exception;
use GuzzleHttp\Client;

class PdfController extends Controller
{
    const BASE_BDD = 'BIOTECHDENTAL_TEST';
    const TOKEN_SECURE = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI4OWMwMDZmNCIsIm5hbWUiOiJTZWN1cmUgZ2FsYXh5IHJlcXVlc3QiLCJpYXQiOjE1MTYyMzkwMjJ9.TJcJx7ihshsoNHX6vfHFBJ1y5VFymMeS1_5efjUyDJ0";

    private $host;
    private $client;

    public function __construct()
    {
        $this->host = 'https://api-sage-public.groupe-upperside.com/API/';
        $this->client = new Client();
    }

    public static function jsonDataToArray(object $response)
    {
        $data = $response->getBody();

        return json_decode($data, true);
    }

    public function getFacturesLignes(string $numero, string $clientCode)
    {
        $url = $this->host . 'lireFacturesLignes/' . self::BASE_BDD . '/' . $clientCode . '/' . $numero . '/1h2of9j20xgkw8o8s8gokck0cs8wgsoos4w8oskcoko8o8cw5a';
        $promise = $this->client->requestAsync('GET', $url);
        $response = $promise->wait();

        $content = SELF::jsonDataToArray($response);

        if ($content['success'] === false || $content['nbresultats'] === 0) {
            return null;
        } else {
            return $content['factures']['facture'][0];
        }
    }

    public function getFactureInPdfSecure(string $numero, string $clientCode)
    {
        $token = request()->bearerToken();
        try {
            if ($token === SELf::TOKEN_SECURE) {
                $result = $this->getFacturesLignes($numero, $clientCode);
                if ($result !== null) {
                    $countLignes = count($result['lignes']['ligne']);
                    $i = 0;
                    $remiseTot = 0;
                    while ($i < $countLignes) {
                        $remiseTot = !is_string($result['lignes']['ligne'][$i]['remise1']) ? 0 : 1;
                        $i++;
                    }
                    $options = ['defaultFont' => 'helvetica', 'isRemoteEnabled' => true, 'enable_php' => true];

                    $data = [
                        'result' => $result,
                        'codeClient' => $clientCode,
                        'remiseTot' => $remiseTot,
                    ];

                    $pdf = PDF::loadView('pdf.factureDetails', compact('data'));
                    $pdf->setOptions($options);
                    $pdf->setPaper('A4', 'portrait');

                    $output = base64_encode($pdf->output());

                    return response()->json(['success' => 1, 'bill' => $output]);
                }
            } else {
                return response()->json(['success' => 0, 'message' => 'Invalid token'], 403);
            }
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    public function getFactureInPdf(string $numero, string $clientCode)
    {
        try {
            $result = $this->getFacturesLignes($numero, $clientCode);
            if ($result !== null) {
                $countLignes = count($result['lignes']['ligne']);
                $i = 0;
                $remiseTot = 0;
                while ($i < $countLignes) {
                    $remiseTot = !is_string($result['lignes']['ligne'][$i]['remise1']) ? 0 : 1;
                    $i++;
                }
                $options = ['defaultFont' => 'helvetica', 'isRemoteEnabled' => true, 'enable_php' => true];

                $data = [
                    'result' => $result,
                    'codeClient' => $clientCode,
                    'remiseTot' => $remiseTot,
                ];

                $pdf = PDF::loadView('pdf.factureDetails', compact('data'));
                $pdf->setOptions($options);
                $pdf->setPaper('A4', 'portrait');

                $output = base64_encode($pdf->output());

                return response()->json(['success' => 1, 'bill' => $output]);
            }
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 500);
        }
    }


    public function getCreditsLignes(string $numero, string $clientCode)
    {
        $url = $this->host . 'lireCreditsDocumentsLignes/' . self::BASE_BDD . '/' . $clientCode . '/' . $numero . '/1h2of9j20xgkw8o8s8gokck0cs8wgsoos4w8oskcoko8o8cw5a';
        $promise = $this->client->requestAsync('GET', $url);
        $response = $promise->wait();

        $content = SELF::jsonDataToArray($response);

        return $content['documents']['document'][0];
    }

    public function getCreditInPdf(string $numero, string $clientCode)
    {
        try {

            $docCredits = $this->getCreditsLignes($numero, $clientCode);

            $options = ['defaultFont' => 'helvetica', 'isRemoteEnabled' => true, 'enable_php' => true];

            $data = [
                'numero' => $numero,
                'docCredits' => $docCredits,
                'codeClient' => $clientCode,
            ];

            $pdf = PDF::loadView('pdf.creditDetails', compact('data'));
            $pdf->setOptions($options);
            $pdf->setPaper('A4', 'portrait');

            $output = base64_encode($pdf->output());

            return response()->json(['success' => 1, 'credit' => $output]);
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 500);
        }
    }
}
