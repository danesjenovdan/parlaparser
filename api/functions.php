<?php

class parserApi{

    protected $elementId;
    protected $client;
    protected $response;

    public $responseBody;

    const STATUS_200 = 200;
    const STATUS_201 = 201;
    const STATUS_204 = 204;
    const STATUS_205 = 205;

    public function __construct($client)
    {

        $this->client = $client;
    }

    public function getResponseJsonDecoded(){

        $this->responseBody = json_decode($this->response->getBody(), true);
        return $this->responseBody;
    }
    public function getElementId(){
        return $this->responseBody["id"];
    }

    public function apiGetList($what){
        $response = $this->client->get($what);
        if($response->getStatusCode() != self::STATUS_200){
            // var_dumpp(json_decode($response->getBody(), true));
            return $response->getStatusCode();
        }

        $this->response = $response;
        return $response->getStatusCode();
    }

    public function apiGetSingle($what){
        $response = $this->client->get($what);
        if($response->getStatusCode() != self::STATUS_200){
            // var_dumpp(json_decode($response->getBody(), true));
            return $response->getStatusCode();
        }

        $this->response = $response;
        return $response->getStatusCode();
    }

    public function apiCreate($what, $data){
        $response = $this->client->post($what, $data);
        if($response->getStatusCode() != self::STATUS_201){
            // var_dumpp(json_decode($response->getBody(), true));
            return $response->getStatusCode();
        }

        $this->response = $response;
        return $response->getStatusCode();
    }

    public function apiEdit($what, $data){
        $response = $this->client->patch($what, $data);
        if($response->getStatusCode() != self::STATUS_200){
            // var_dumpp(json_decode($response->getBody(), true));
            return $response->getStatusCode();
        }

        $this->response = $response;
        return $response->getStatusCode();
    }

    public function apiDelete($what){
        $response = $this->client->delete($what);
        if($response->getStatusCode() != self::STATUS_200){
            // var_dumpp(json_decode($response->getBody(), true));
            return $response->getStatusCode();
        }

        $this->response = $response;
        return $response->getStatusCode();
    }
}


