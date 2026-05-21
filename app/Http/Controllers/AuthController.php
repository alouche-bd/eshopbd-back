<?php

namespace App\Http\Controllers;

use App\Mail\AksRegistrationMailer;
use App\Models\User;
use App\Services\AuthService;
use App\Services\Distributor\ClientSyncService;
use App\Services\GeneratePasswordService;
use App\Traits\ApiResponser;
use App\Traits\ConsumeExternalService;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\JWTAuth;

class AuthController extends Controller
{
    use ConsumeExternalService;
    use ApiResponser;


    const TTL_TOKEN = 700000000;
    protected $jwt;
    private $generatePasswordService;
    private $authService;

    /**
     * The base uri to consume middleware service
     * @var string
     */
    public $baseUri;


    /**
     * Authorization secret to pass to middleware api
     * @var string
     */
    public $secret;

    /**
     * Authorization secret to pass to middleware api
     * @var string
     */
    public $ssoSecret;

    /**
     * Authorization secret to pass to middleware api
     * @var string
     */
    public $baseSSO;

    /**
     * Authorization secret to pass to middleware api
     * @var string
     */
    public $UUIDSSO;

    public Client $client;

    /**
     * The base uri to consume middleware service
     * @var string
     */
    public $middlewarebaseUri;

    /**
     * Authorization secret to pass to middleware api
     * @var string
     */
    public $middlewareSecret;

    /**
     * @return void
     */
    public function __construct(JWTAuth $jwt)
    {
        $this->baseUri = config('services.middleware.base_uri');
        $this->secret = config('services.middleware.secret');
        $this->baseSSO = config('services.sso.base_uri');
        $this->ssoSecret = config('services.sso.broker_secret');
        $this->UUIDSSO = config('services.sso.broker_uuid');
        $this->jwt = $jwt;
        $this->authService = new AuthService();
        $this->generatePasswordService = new GeneratePasswordService();
        $this->client = new Client();
        $this->middlewarebaseUri = config('services.middleware.base_uri');
        $this->middlewareSecret = config('services.middleware.secret');
    }


    /**
     *
     * @return mixed
     */
    public function register($email)
    {
        $url = '/api/lireExisteEmailStrictLaGalaxy/' . env('BASE_MIDDLEWARE') . '/' . $email;

        try {
            $response = $this->performRequest('GET', $url);

            $contentResponse = json_decode($response, true);

            if ($contentResponse['nbresultats'] > 0) {
                $sageUser = $contentResponse['datas']['data'][0];
                $user = new User();
                $user->email = $email;
                $user->codeclientGC = $sageUser['codeclientGC'];
                $user->user_type = $sageUser['qualite'];
                $user->codeclientCPTA = $sageUser['codeclientCPTA'];
                $user->raisonsociale = $sageUser['raisonsociale'];
                $user->suid = $sageUser['suuid'];
                $user->sfuid = $sageUser['idsf'];
                $plainPassword = "1234";
                $user->password = app('hash')->make($plainPassword);

                $user->save();
                $user = $this->syncSage($user);
                $token = auth()->tokenById($user->id);

                return response()->json([
                    'success' => 1,
                    'message' => 'User Registration success!',
                    'token' => $token,
                    'user' => $user
                ]);
            } else {
                return response()->json(['success' => 0, 'message' => 'User not found in sage!'], 404);
            }
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 409);
        }
    }

    /**
     *
     * @return mixed
     */
    public function registerAdmin(request $request)
    {
        try {
            $user = new User();
            $user->email = $request->input("email");
            $user->codeclientGC = $request->input("codeclientGC");
            $user->codeclientCPTA = $request->input("codeclientCPTA");
            $user->raisonsociale = $request->input("raisonsociale");
            $user->suid = $request->input("suid");
            $user->sfuid = $request->input("sfuid");
            $plainPassword = $request->input("password");
            $user->user_type = "LDA";
            $user->password = app('hash')->make($plainPassword);

            $user->save();
            $user = $this->syncSage($user);
            $token = auth()->tokenById($user->id);

            return response()->json([
                'success' => 1,
                'message' => 'User Registration success!',
                'token' => $token,
                'user' => $user
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 409);
        }
    }

    /**
     *
     * @return mixed
     */
    public function registerFake(request $request)
    {

        try {
            $email = $request->input("email");
            $suid = $request->input("suid");
            $user = new User();
            $user->email = $email;
            $user->codeclientGC = 'SF094762';
            $user->codeclientCPTA = 'SF094762';
            $user->raisonsociale = 'demo';
            $user->suid = $suid;
            $user->sfuid = '0011r00002WyqvvAAB';
            $plainPassword = "1234";
            $user->password = app('hash')->make($plainPassword);

            $user->save();
            $token = auth()->tokenById($user->id);

            return response()->json([
                'success' => 1,
                'message' => 'User Registration success!',
                'token' => $token,
                'user' => $user
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 409);
        }
    }

    public function askRegistration(Request $request)
    {
        try {
            $infos = $request->all();
            $country = $request->input("shippingCountry");
            if ($country === "France") {
                foreach (['j.labru@biotech-dental.com', 'j.velot@biotech-dental.com'] as $recipient) {
                    Mail::to($recipient)
                        ->send(new AksRegistrationMailer($infos));
                }
            } else {
                Mail::to('r.basiul@biotech-dental.com')
                    ->send(new AksRegistrationMailer($infos));
            }
            return response()->json(["message" => "Message sent successfully."]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()]);
        }
    }


    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function checkEmail(Request $request)
    {
        $validator = Validator::make($request->only('email'), [
            'email' => 'required|email',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Please fix these errors', 'errors' => $validator->errors()], 500);
        }
        try {
            /** @var User $user */
            $user = User::where('email', $request->get('email'))->first();
            if (!$user) {
                return response()->json([
                    'success' => 0,
                ]);
            } else {

                $token = auth()->setTTL(self::TTL_TOKEN)->tokenById($user->id);
                $user = $this->syncSage($user);
                return response()->json([
                    'success' => 1,
                    'token' => $token,
                    'user' => $user

                ]);
            }
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 500);
        }
    }


    function userDetails()
    {
        $user = Auth::user();
        return response()->json([
            'user' => $user
        ]);
    }

    /**
     * @return JsonResponse
     */
    function logout()
    {
        $token = auth()->tokenById(auth()->user()->id);
        $this->jwt->setToken($token)->invalidate();
        auth()->logout();
        return response()->json([
            'success' => 1,
            'message' => 'Signed out'
        ]);
    }

    public function attach($brokerToken)
    {
        $url_attach = $this->baseSSO . 'attach/api';
	
        try {
            $response = $this->client->requestAsync('POST', $url_attach, [
                'json' => ['r' => $this->authService->generateRParameters($this->UUIDSSO, $this->ssoSecret, $brokerToken)]
            ]);

            $response = $response->wait();
            $contentResponse = json_decode($response->getBody(), true);
            return $contentResponse['result']['app-authorization'];
        } catch (Exception $e) {
            $msgError = "Erreur lors de la phase d'attach au serveur SSO";

            $msgError .= ': ' . $e->getMessage();

            return response()->json(['success' => 0, 'message' => $msgError], 500);
        }
    }

    public function validateSSO($ssoToken, $brokerToken)
    {
        $url_validate = $this->baseSSO . 'validate';

        try {

            $ssoAppToken = $this->attach($brokerToken);

            $response = $this->client->requestAsync('GET', $url_validate, [
                'headers' => [
                    'app-authorization' => sprintf('Bearer %s-%s-%s',
                        $this->UUIDSSO,
                        $brokerToken,
                        $ssoAppToken
                    ),
                    'authorization' => $ssoToken
                ]
            ]);

            $response = $response->wait();


            $contentResponse = json_decode($response->getBody(), true);


            return $contentResponse['result']['authorization'];

        } catch (Exception $e) {
            $msgError = "Erreur lors de la phase d'attach au serveur SSO";

            $msgError .= ': ' . $e->getMessage();

            return response()->json(['success' => 0, 'message' => $msgError], 500);
        }
    }

    public function getLoginSso($ssoToken)
    {
        $url_login = $this->baseSSO . 'login/credentials';

        $brokerToken = $this->authService->generateBrokerToken();

        try {
            $response = $this->client->requestAsync('POST', $url_login, [
                'headers' => [
                    'app-authorization' => sprintf('Bearer %s-%s-%s',
                        $this->UUIDSSO,
                        $brokerToken,
                        $this->validateSSO($ssoToken, $brokerToken)
                    ),
                    'authorization' => $ssoToken
                ]
            ]);

            $response = $response->wait();
            $contentResponse = json_decode($response->getBody(), true);

            return $contentResponse['login'];

        } catch (Exception $e) {
            $msgError = "Erreur lors de la phase d'attach au serveur SSO";

            $msgError .= ': ' . $e->getMessage();

            return response()->json(['success' => 0, 'message' => $msgError], 500);
        }
    }


    /**
     *
     * @return mixed
     */
    public function login($email)
    {
        try {
            /** @var User $user */
            $user = User::where('email', $email)->first();
            if (!$user) {
                return false;
            } else {
                $token = auth()->setTTL(self::TTL_TOKEN)->tokenById($user->id);
                $user = $this->syncSage($user);
                return response()->json([
                    'success' => 1,
                    'token' => $token,
                    'user' => $user

                ]);
            }
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Refresh the user's Sage profile (billing country, currency, representative,
     * addresses, distributor flag) on every authenticated entry point. Never
     * throws — login must succeed even when middleware is offline.
     */
    private function syncSage(User $user): User
    {
        try {
            /** @var ClientSyncService $sync */
            $sync = app(ClientSyncService::class);
            return $sync->syncFromSage($user);
        } catch (Exception $e) {
            return $user;
        }
    }

    /**
     * @return JsonResponse
     * @throws ValidationException
     */
    public function loginAdmin(Request $request)
    {
        $credentials = $this->validate($request, [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            try {
                /** @var User $user */
                $user = User::where('email', $request->get('email'))->first();
                if (!$user) {
                    return false;
                } else {
                    $token = auth()->setTTL(self::TTL_TOKEN)->tokenById($user->id);
                    $user = $this->syncSage($user);
                    return response()->json([
                        'success' => 1,
                        'token' => $token,
                        'user' => $user

                    ]);
                }
            } catch (Exception $e) {
                return response()->json(['success' => 0, 'message' => $e->getMessage()], 500);
            }
        } else {
            return response()->json(['success' => 0]);
        }
    }

    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function loginWithSso(Request $request)
    {
        $token = $request->get('token');
	
        try {
            $email = $this->getLoginSso(urldecode($token));

            $isUser = $this->login($email);
            if (!$isUser) {
                return $this->register($email);
            } else {
                return $isUser;
            }
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    function cryptPassword($password)
    {
        openssl_public_encrypt($password, $cryptPassword, "-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC4y8gYZMJM1Z30WhODD+/j64Tw
lseqHxw8QjX87UNBDFvYWGT8LYmEUFbjGIbxOKi/R1wS3nVfyfoOmfdp3Rzd7WbV
Gcr7PiJT/QJN8hrUY41amEAJiprhxlAUqqYcLZTTiINU+Re2f36Yshyc/dQRsCmh
HKHc9LAa+CRIEBX1jQIDAQAB
-----END PUBLIC KEY-----");
        return base64_encode($cryptPassword);
    }

    /**
     * @return JsonResponse
     */
    function reverseLoginSSO(Request $request)
    {
        //param required in the API call but don't know what for
        $url_reverse_login = $this->baseSSO . 'login/api?redirect_url=http://localhost:8000';
        $brokerToken = $this->authService->generateBrokerToken();
        $data_login = ["login" => $request->get('email'), "password" => $this->cryptPassword($request->get('password'))];

        $curl_handle_login = curl_init();
        curl_setopt($curl_handle_login, CURLOPT_URL, $url_reverse_login);
        curl_setopt($curl_handle_login, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_handle_login, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_handle_login, CURLOPT_POSTFIELDS, json_encode($data_login));
        curl_setopt(
            $curl_handle_login,
            CURLOPT_HTTPHEADER,
            [
                'Content-type: application/json',
                'app-authorization: ' . sprintf('Bearer %s-%s-%s',
                    $this->UUIDSSO,
                    $brokerToken,
                    $this->attach($brokerToken)
                ),
            ]
        );

        $query_login = curl_exec($curl_handle_login);
        $error_login = curl_error($curl_handle_login);
        curl_close($curl_handle_login);

        $array_login = json_decode(trim($query_login), TRUE);

        $authorization_from_app1 = $array_login['result']['token'];

        try {
            $email = $this->getLoginSso($authorization_from_app1);

            //login or register user on eshop
            $isUser = $this->login($email);
            if (!$isUser) {
                return $this->register($email);
            } else {
                return $isUser;
            }
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 500);
        }
    }


    /**
     * @return JsonResponse
     */
    function checkLogin()
    {
        if (Auth::user()) {
            return response()->json(['success' => 1]);
        }
        return response()->json(['success' => 0]);
    }


    /**
     *
     * @return JsonResponse
     */
    public function refresh()
    {
        $user = Auth::user();
        return $this->respondWithToken(auth()->setTTL(self::TTL_TOKEN)->refresh(), $user);
    }

    /**
     * @param string $token
     *
     * @return JsonResponse
     */
    protected function respondWithToken($token, $user)
    {
        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }
}
