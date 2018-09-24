<?php


namespace Devtropolis\NovaCodeShipStatusCard;


use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CodeShipStatusController
{

    protected $client;
    protected $api_auth_url;
    protected $organisation_id;
    protected $project_uuid;


    public function __construct()
    {
        $this->client = new Client();
        $this->api_url  = 'https://api.codeship.com/v2/';

        $this->organisation_id = env('CODESHIP_ORGANISATION','');
        $this->project_uuid = env('CODESHIP_PROJECT','');

    }

    public function show()
    {

        $latestBuildStatus = $this->getLatestBuildStatus($this->getProjectBuilds());


        Log::info('The Build Status from codeship is: ' . $latestBuildStatus['status']);


        return response()->json([
            'status' =>  $this->translateStatus($latestBuildStatus['status']),
            'message' => 'Branch: ' . $latestBuildStatus['branch'],
        ]);
    }


        protected function translateStatus($status)
        {

            $failArrayStates = ['error','stopped','infrastructure_failure'];
            $passArrayStatus = ['success'];


            if(in_array($status,$failArrayStates))
            {
                return 'fail';
            }
            elseif(in_array($status,$passArrayStatus))
            {
                return 'pass';
            }

            return 'other';

        }

    protected function getLatestBuildStatus(Response $responseBody)
    {
        $arr = $responseBody->getBody()->getContents();
        $el = json_decode($arr,true);
        $latest = $el['builds'][0];
        return $latest;
    }

    protected function getProjectBuilds()
    {
        $accessToken = $this->getAccessToken();
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept'        => 'application/json',
        ];
        $response = $this->getResource($this->buildListUrl(),['headers' => $headers]);
        return $response;
    }


    protected function buildListUrl()
    {
        return $this->api_url . 'organizations/'.   $this->organisation_id  . '/projects/' . $this->project_uuid . '/builds';
    }


    protected function getAccessToken()
    {
        $token = Cache::remember('codeship_access_token', 59, function () {

            $response = $this->client->post( $this->api_url .'/auth',['auth' => [
                env('CODESHIP_USERNAME',''),
                env('CODESHIP_PASSWORD','')
            ]]);
            $responseBody= json_decode($response->getBody(), true);
            return $responseBody['access_token'];
        });
        return $token;
    }

    protected function getResource($request_url,$options)
    {
        return $this->client->get($request_url,$options);
    }



}
