<?php

namespace Buffer\Facebook;

use Facebook\Facebook as FacebookClient;

class Facebook
{
    protected $client = null;

    public function __construct()
    {
        $this->client = new FacebookClient([
            'app_id' => getenv('FACEBOOK_APP_ID'),
            'app_secret' => getenv('FACEBOOK_APP_SECRET'),
            'default_graph_version' => 'v2.9',
        ]);
    }

    /*
     * Set the default access token for the client.
     */
    public function setAccessToken($accessToken)
    {
        if (empty($accessToken)) {
            return false;
        }
        try {
            $this->client->setDefaultAccessToken($accessToken);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /*
     * Set facebook client to the given library.
     */
    public function setFacebookLibrary($facebookLibrary)
    {
        return $this->client = $facebookLibrary;
    }

    /*
     * Get page insights data for the given page, metrics and range.
     */
    public function getPageInsightsMetricsData($pageId, $insightsMetrics, $since, $until)
    {
        $params = [
            "metric" => $insightsMetrics,
            "until" => strtotime("now"),
        ];
        if (!is_null($since) && !is_null($until)) {
            $params["since"] = $since;
            $params["until"] = $until;
        }
        $response = $this->sendRequest("GET", "/{$pageId}/insights", $params);

        if (!empty($response)) {
            $decodedBody = $response->getDecodedBody();
            if (!empty($decodedBody) && is_array($decodedBody)) {
                // make the response human readable and return it
                return $this->extractPageInsightsMetricsFromResponse($decodedBody);
            }
        }

        return [];
    }

    /*
     * Get page post's graph metrics data (only comments, likes and reactions)
     */
    public function getPagePostGraphMetricsData($pageId, $postId, $metrics)
    {
        $result = [];

        $objectId = "{$pageId}_{$postId}";
        $fieldsString = join(".summary(true),", $metrics);
        $fieldsString .= ".summary(true)";

        $response = $this->sendRequest("GET", "/{$objectId}", [
            "fields" => $fieldsString,
        ]);

        if (!empty($response)) {
            $result = $this->processGraphResponse($response, $metrics);
        }

        return $result;
    }

    /*
     * Get page batch posts' graph metrics data (only comments, likes and reactions)
     */
    public function getPageBatchPostsGraphMetricsData($postIds, $metrics)
    {
        $result = [];
        $batchRequests = [];
        $fieldsString = join(".summary(true),", $metrics);
        $fieldsString .= ".summary(true)";
        foreach ($postIds as $postId) {
            $request = $this->createRequest("GET", "/{$postId}", [
                "fields" => $fieldsString,
            ]);
            $batchRequests[$postId] = $request;
        }
        $responses = $this->sendBatchRequest($batchRequests);
        if (!empty($responses)) {
            foreach ($responses as $key => $response) {
                $result[$key] = $this->processGraphResponse($response, $metrics);
            }
        }

        return $result;
    }

    /*
     * Get page post's insights data for the given post and metrics.
     */
    public function getPagePostInsightsMetricData($pageId, $postId, $insightsMetrics)
    {
        $result = [];

        $objectId = "{$pageId}_{$postId}";
        $insightsMetricsString = join(",", $insightsMetrics);
        $response = $this->sendRequest("GET", "/{$objectId}/insights", [
            "metric" => $insightsMetricsString,
            "period" => "lifetime",
            "until" => strtotime("now"),
        ]);

        if (!empty($response)) {
            $result = $this->processInsightsResponse($response);
        }

        return $result;
    }

    /*
     * Get page batch posts insights data for the given posts and metrics.
     */
    public function getPageBatchPostsInsightsMetricData($postIds, $insightsMetrics)
    {
        $result = [];
        $batchRequests = [];
        $insightsMetricsString = join(",", $insightsMetrics);
        foreach ($postIds as $postId) {
            $request = $this->createRequest("GET", "/{$postId}/insights", [
                "metric" => $insightsMetricsString,
                "period" => "lifetime",
                "until" => strtotime("now"),
            ]);
            $batchRequests[$postId] = $request;
        }

        $responses = $this->sendBatchRequest($batchRequests);
        if (!empty($responses)) {
            foreach ($responses as $key => $response) {
                $result[$key] = $this->processInsightsResponse($response);
            }
            return $result;
        }

        return [];
    }

    /*
     * Get page posts sent within since and until date times.
     * Returns an associative array of id => created_time elements where
     * id is the post id.
     */
    public function getPagePosts($pageId, $since, $until, $limit = 100)
    {
        $posts = [];

        $since = $since ? $since : strtotime("yesterday");
        $until = $until ? $until : strtotime("now");


        $posts = [];
        $response = $this->client->get("/{$pageId}/posts?since={$since}&until={$until}&limit={$limit}");
        $graphEdge = $response->getGraphEdge();

        while ($graphEdge) {
            foreach ($graphEdge as $post) {
                $posts[$post->getField("id")] = $post->getField("created_time")->format(DATE_ISO8601);
            }

            $graphEdge = $this->client->next($graphEdge);
        }

        return $posts;
    }

    /*
     * Create a single request
     */
    private function createRequest($method, $url, $params = [])
    {
        return $this->client->request($method, $url, $params);
    }

    /*
     * Given page insights daily data extract the metrics to a human readable format.
     * [Metric1 => [[Day1 => Value], [Day2 => Value]], Metric2 => [[Day1 => Value], [Day2 => Value]], ...]
     */
    private function extractPageInsightsMetricsFromResponse($response)
    {
        $result = [];
        $data = $response["data"];

        if (empty($data)) {
            return $result;
        }

        foreach ($data as $key => $metrics) {
            $metricName = $metrics["name"];
            // care only daily and lifetime metrics
            if (in_array($metrics["period"], ["day", "lifetime"])) {
                $result[$metricName] = [];
                $metricValuesByDay = $metrics["values"];
                foreach ($metricValuesByDay as $key => $value) {
                    if (isset($value["end_time"]) && isset($value["value"])) {
                        $result[$metricName][$value["end_time"]] =  $value["value"];
                    }
                }
            }
        }
        return $result;
    }

    /*
     * Make Graph API /insights response to a human readable associative array.
     */
    private function processInsightsResponse($response)
    {
        $result = [];
        $decodedBody = $response->getDecodedBody();
        if (!empty($decodedBody["data"]) && is_array($decodedBody["data"])) {
            $data = $decodedBody["data"];
            foreach ($data as $key => $metric) {
                $metricName = $metric["name"];
                // since the period for posts is lifetime there will be only one value
                $metricValue = $metric["values"][0]["value"];
                $result[$metricName] = $metricValue;
            }
        }
        return $result;
    }

    /*
     * Make Graph API response to a human readable associative array.
     */
    private function processGraphResponse($response, $metrics)
    {
        $result = [];
        $decodedBody = $response->getDecodedBody();
        if (!empty($decodedBody)) {
            foreach ($metrics as $metric) {
                // shares count requires a special care
                if ($metric === "shares") {
                    $result[$metric] = isset($decodedBody[$metric]["count"]) ? $decodedBody[$metric]["count"] : 0;
                } elseif (isset($decodedBody[$metric])) {
                    $result[$metric] = $decodedBody[$metric]["summary"]["total_count"];
                }
            }
        }
        return $result;
    }

    /*
     * Make a request to FB API.
     */
    private function sendRequest($method, $endpoint, $params = [])
    {
        return $this->client->sendRequest($method, $endpoint, $params);
    }

    /*
     * Make a batch request to FB API.
     */
    private function sendBatchRequest($requests)
    {
        return $this->client->sendBatchRequest($requests);
    }
}
