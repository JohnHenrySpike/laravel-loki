<?php

namespace App\Helpers\System;

use App\Models\System\DBLog;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;

class Loki
{
    private Client $http_client;

    public string $direction; // BACKWARD or FORWARD
    public int $limit;
    public string $query;
    public int $start;
    public int $end;
    public int $step;
    public int $range;
    public array $request;
    private string $response;


    function __construct(){
        $this->http_client = new Client([
            'base_uri' => config('gis.LOKI_URL')
        ]);
        $this->direction = 'BACKWARD';
        $this->limit = 1488;
        $this->step = 60;
        $this->end = time();
        $this->start = time()-(3600);
        $this->range = $this->end - $this->start;
    }


    /**
     * Send query string to loki
     * start = 163 862 1470243000000
     * end =   163 870 7870243000000
     *
     * start=163 853 5169427000000
     * end=  163 870 7969427000000
     * example 'topk(10, sum (count_over_time({request_type="tile"}[$__range])) by (tile_pub) )'
     * @param string $query
     * @return array
     * @throws Exception
     */
    public function get(string $query): array
    {
        $this->setQuery($query);
        $this->sendRequest();
        $response = json_decode($this->response, true);
        if (isset($response["data"]["result"]) && !empty($response["data"]["result"])) {
            return $response["data"]["result"];
        }
        return [];
    }

    /**
     * @return void
     */
    private function sendRequest(): void
    {
        try {
            $this->createRequest([
                "direction" => $this->direction,
                "limit" => $this->limit,
                "query" => $this->query,
                "start" => $this->start,
                "end" => $this->end,
                "step" => $this->step
            ]);
            $this->response = $this->http_client->get('/loki/api/v1/query', [
                "query" => $this->request
            ])->getBody()->getContents();
            return;
        } catch (BadResponseException $e){
            DBLog::error('loki', $e->getMessage());
            return;
        } catch (GuzzleException $e) {
            DBLog::error('loki', $e->getMessage());
            return;
        }
    }

    /**
     * @param string $query
     * @return Loki
     * @throws Exception
     */
    public function setQuery(string $query): Loki
    {
        $this->query = $this->lokiQuery($query);
        return $this;
    }

    /**
     * @param int|string $start
     * @return Loki
     */
    public function setStart($start): Loki
    {
        if (is_string($start)){
            $this->start = strtotime($start);
        }else {
            $this->start = $start;
        }
        $this->updateRange();
        return $this;
    }

    /**
     * @param int | string $end
     * @return Loki
     */
    public function setEnd($end): Loki
    {
        if (is_string($end)){
            $this->end = strtotime($end);
        }else {
            $this->end = $end;
        }
        $this->updateRange();
        return $this;
    }

    /**
     * @param int $step
     * @return Loki
     */
    public function setStep(int $step): Loki
    {
        $this->step = $step;
        return $this;
    }

    public function createRequest(array $data): Loki
    {
        $this->request = $data;
        return $this;
    }

    /**
     * @param string $query
     * @return array|string|string[]
     * @throws Exception
     */
    private function lokiQuery(string $query)
    {
        $pQuery = false;
        if (strpos($query, '$__range')){
            $pQuery = str_replace('$__range', $this->range.'s', $query);
        }
        return $pQuery;
    }

    /**
     * @return void
     */
    private function updateRange(): void
    {
        $this->range = $this->end - $this->start;
    }
}
