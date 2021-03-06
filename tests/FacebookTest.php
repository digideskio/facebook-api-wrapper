<?php

require_once(__DIR__ . "/mocks/GraphNode.php");

use Buffer\Facebook\Facebook;
use Mockery as m;

use \Facebook\FacebookRequest;
use \Facebook\GraphNodes\GraphEdge;

class FacebookTest extends PHPUnit_Framework_TestCase
{
    const FB_POST_ID = '11111_22222';
    const FB_PAGE_ID = '2222222';

    private $facebook = null;

    public function setUp()
    {
        parent::setUp();
        $this->facebook = new Facebook();
    }

    public function tearDown()
    {
        m::close();
    }

    public function testSetAccessToken()
    {
        $mockedFacebookLibrary = m::mock('\Facebook\Facebook');
        $mockedFacebookLibrary->shouldReceive('setDefaultAccessToken')->once()->getMock();
        $this->facebook->setFacebookLibrary($mockedFacebookLibrary);

        $this->assertTrue($this->facebook->setAccessToken('test_token'));
    }

    public function testSetAccessTokenWithNullToken()
    {
        $mockedFacebookLibrary = m::mock('\Facebook\Facebook');
        $mockedFacebookLibrary->shouldNotReceive('setDefaultAccessToken')->getMock();
        $this->facebook->setFacebookLibrary($mockedFacebookLibrary);

        $this->assertFalse($this->facebook->setAccessToken(null));
    }

    public function testGetPageInsightsMetricsData()
    {
        $decodedInsightsResponseData = [
            'data' => [
                0 => [
                    'name' => 'page_views_total',
                    'period' => 'day',
                    'values' => [
                        0 => [
                            'value' => 123,
                            'end_time' => '2017-04-27T07:00:00+0000',
                        ],
                        1 => [
                            'value' => 222,
                            'end_time' => '2017-04-28T07:00:00+0000',
                        ],
                        2 => [
                            'value' => 111,
                            'end_time' => '2017-04-29T07:00:00+0000',
                        ],
                    ],
                ],
                1 => [
                    'name' => 'page_fans',
                    'period' => 'lifetime',
                    'values' => [
                        0 => [
                            'value' => 444,
                            'end_time' => '2017-04-27T07:00:00+0000',
                        ],
                        1 => [
                            'value' => 555,
                            'end_time' => '2017-04-28T07:00:00+0000',
                        ],
                        2 => [
                            'value' => 666,
                            'end_time' => '2017-04-29T07:00:00+0000',
                        ],
                    ],
                ],
                2 => [
                    'name' => 'page_fan_adds',
                    'period' => 'week',
                    'values' => [
                        0 => [
                            'value' => 444,
                            'end_time' => '2017-04-27T07:00:00+0000',
                        ],
                        1 => [
                            'value' => 555,
                            'end_time' => '2017-05-4T07:00:00+0000',
                        ],
                    ],
                ],
            ]
        ];

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedInsightsResponseData)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $insightsData = $facebook->getPageInsightsMetricsData(
            self::FB_PAGE_ID,
            [
                'page_views_total',
                'page_fans',
            ],
            strtotime("yesterday"),
            strtotime("now")
        );
        $this->assertEquals($insightsData["page_views_total"]["2017-04-27T07:00:00+0000"], 123);
        $this->assertEquals($insightsData["page_views_total"]["2017-04-28T07:00:00+0000"], 222);
        $this->assertEquals($insightsData["page_views_total"]["2017-04-29T07:00:00+0000"], 111);

        $this->assertEquals($insightsData["page_fans"]["2017-04-27T07:00:00+0000"], 444);
        $this->assertEquals($insightsData["page_fans"]["2017-04-28T07:00:00+0000"], 555);
        $this->assertEquals($insightsData["page_fans"]["2017-04-29T07:00:00+0000"], 666);

        // week period metrics should not be in the response
        $this->assertArrayNotHasKey("page_fan_adds", $insightsData);

    }

    public function testGetPageInsightsMetricsDataShouldReturnEmpty()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn([])
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $insightsData = $facebook->getPageInsightsMetricsData(
            self::FB_PAGE_ID,
            [
                'page_posts_impressions_unique',
                'page_posts_impressions',
            ],
            strtotime("yesterday"),
            strtotime("now")
        );
        $this->assertEquals($insightsData, []);
    }

    public function testGetPageInsightsMetricsDataShouldReturnEmptyWhenDataIsNull()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn(['data' => null])
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $insightsData = $facebook->getPageInsightsMetricsData(
            self::FB_PAGE_ID,
            [
                'page_posts_impressions_unique',
                'page_posts_impressions',
            ],
            strtotime("yesterday"),
            strtotime("now")
        );
        $this->assertEquals($insightsData, []);
    }

    public function testGetPageInsightsMetricsDataShouldUseRightSinceAndUntil()
    {
        $decodedInsightsResponseData = [
            'data' => [
                0 => [
                    'name' => 'page_posts_impressions_unique',
                    'period' => 'day',
                    'values' => [
                        0 => [
                            'value' => 123,
                            'end_time' => '2017-04-27T07:00:00+0000',
                        ],
                        1 => [
                            'value' => 222,
                            'end_time' => '2017-04-28T07:00:00+0000',
                        ],
                        2 => [
                            'value' => 111,
                            'end_time' => '2017-04-29T07:00:00+0000',
                        ],
                    ],
                ],
            ]
        ];
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedInsightsResponseData)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $since  = "1493826552";
        $until = "1496418552";
        $params = [
            "metric" => ['page_posts_impressions_unique'],
            "until" => $until,
            "since" => $since,
        ];
        $expectedGetParams = ["GET", "/2222222/insights", $params];

        $facebookMock->shouldReceive('sendRequest')->withArgs($expectedGetParams)->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $facebook->getPageInsightsMetricsData(
            self::FB_PAGE_ID,
            ['page_posts_impressions_unique'],
            $since,
            $until
        );
    }

    public function testGetPagePostGraphMetricsData()
    {
        $decodedGraphResponseData = [
            'reactions' => [
                'data' => [],
                'summary' => ['total_count' => 123],
            ],
            'comments' => [
                'data' => [],
                'summary' => ['total_count' => 12],
            ],
            'likes' => [
                'data' => [],
                'summary' => ['total_count' => 30],
            ],
            'shares' => [
                'count' => 10,
            ],
        ];

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedGraphResponseData)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $graphData = $facebook->getPagePostGraphMetricsData(
            self::FB_PAGE_ID,
            self::FB_POST_ID,
            ['comments', 'reactions', 'likes', 'shares']
        );

        $this->assertEquals($graphData['comments'], 12);
        $this->assertEquals($graphData['reactions'], 123);
        $this->assertEquals($graphData['likes'], 30);
        $this->assertEquals($graphData['shares'], 10);
    }

    // Test the case when shares count is not in the response.
    // In this case we default shares count to 0.
    public function testGetPagePostGraphMetricsDataShouldHaveSharesCount()
    {
        $decodedGraphResponseData = [
            'reactions' => [
                'data' => [],
                'summary' => ['total_count' => 123],
            ],
            'comments' => [
                'data' => [],
                'summary' => ['total_count' => 12],
            ],
        ];

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedGraphResponseData)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $graphData = $facebook->getPagePostGraphMetricsData(
            self::FB_PAGE_ID,
            self::FB_POST_ID,
            ['comments', 'reactions', 'likes', 'shares']
        );

        $this->assertEquals($graphData['comments'], 12);
        $this->assertEquals($graphData['reactions'], 123);
        $this->assertEquals($graphData['shares'], 0);
    }

    public function testGetPagePostGraphMetricsShouldReturnEmptyIfNoResponse()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn([])
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $graphData = $facebook->getPagePostGraphMetricsData(
            self::FB_PAGE_ID,
            self::FB_POST_ID,
            ['comments', 'reactions']
        );
        $this->assertEquals($graphData, []);
    }

    public function testGetPageBatchPostsGraphMetricsData()
    {
        $facebook = new Facebook();

        $decodedGraphResponseData1 = [
            'reactions' => [
                'summary' => ['total_count' => 123],
            ],
            'comments' => [
                'summary' => ['total_count' => 12],
            ],
            'shares' => [
                'count' => 3
            ]
        ];

        $decodedGraphResponseData2 = [
            'reactions' => [
                'data' => [],
                'summary' => ['total_count' => 111],
            ],
            'comments' => [
                'data' => [],
                'summary' => ['total_count' => 2222],
            ],
            'shares' => [
                'count' => 6
            ]
        ];

        $responseMock1 = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedGraphResponseData1)
            ->getMock()
            ;
        $responseMock2 = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedGraphResponseData2)
            ->getMock();

        $getIteratorMock = new ArrayIterator([
            "11111_22222" => $responseMock1,
            "33333_444444" => $responseMock2
        ]);

        $responseBatchMock = m::mock('\Facebook\FacebookBatchResponse')
            ->shouldReceive('getIterator')
            ->once()
            ->andReturn($getIteratorMock)
            ->getMock();

        $requestMock1 = m::mock('\Facebook\FacebookRequest');
        $requestMock2 = m::mock('\Facebook\FacebookRequest');

        $postIds = ['11111_22222', '33333_444444'];
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebook->setFacebookLibrary($facebookMock);


        $facebookMock->shouldReceive('request')->twice()->andReturn($requestMock1, $requestMock2);

        $facebookMock->shouldReceive('sendBatchRequest')->once()->andReturn($responseBatchMock);

        $graphData = $facebook->getPageBatchPostsGraphMetricsData($postIds, ['comments', 'reactions', 'shares']);

        $this->assertEquals($graphData['11111_22222']['comments'], 12);
        $this->assertEquals($graphData['11111_22222']['reactions'], 123);
        $this->assertEquals($graphData['11111_22222']['shares'], 3);

        $this->assertEquals($graphData['33333_444444']['comments'], 2222);
        $this->assertEquals($graphData['33333_444444']['reactions'], 111);
        $this->assertEquals($graphData['33333_444444']['shares'], 6);
    }

    public function testGetPageBatchPostsInsightsMetricsData()
    {
        $facebook = new Facebook();

        $decodedResponseData1 = [
            'data' => [
                [
                    'name' => 'post_impressions',
                    'period' => 'lifetime',
                    'values' => [['value' => 5]]
                ],
                [
                    'name' => 'post_fan_reach',
                    'period' => 'lifetime',
                    'values' => [['value' => 12]]
                ],
                [
                    'name' => 'post_consumptions',
                    'period' => 'lifetime',
                    'values' => [['value' => 4]]
                ],
            ]
        ];

        $decodedResponseData2 = [
            'data' => [
                [
                    'name' => 'post_impressions',
                    'period' => 'lifetime',
                    'values' => [['value' => 14]]
                ],
                [
                    'name' => 'post_fan_reach',
                    'period' => 'lifetime',
                    'values' => [['value' => 28]]
                ],
                [
                    'name' => 'post_consumptions',
                    'period' => 'lifetime',
                    'values' => [['value' => 42]]
                ],
            ]
        ];

        $responseMock1 = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedResponseData1)
            ->getMock()
            ;
        $responseMock2 = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedResponseData2)
            ->getMock();

        $getIteratorMock = new ArrayIterator([
            "11111_22222" => $responseMock1,
            "33333_444444" => $responseMock2
        ]);

        $responseBatchMock = m::mock('\Facebook\FacebookBatchResponse')
            ->shouldReceive('getIterator')
            ->once()
            ->andReturn($getIteratorMock)
            ->getMock();

        $requestMock1 = m::mock('\Facebook\FacebookRequest');
        $requestMock2 = m::mock('\Facebook\FacebookRequest');

        $postIds = ['11111_22222', '33333_444444'];
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebook->setFacebookLibrary($facebookMock);


        $facebookMock->shouldReceive('request')->twice()->andReturn($requestMock1, $requestMock2);

        $facebookMock->shouldReceive('sendBatchRequest')->once()->andReturn($responseBatchMock);

        $graphData = $facebook->getPageBatchPostsInsightsMetricData($postIds, ['post_impressions', 'post_fan_reach', 'post_consumptions']);

        $this->assertEquals($graphData['11111_22222']['post_impressions'], 5);
        $this->assertEquals($graphData['11111_22222']['post_fan_reach'], 12);
        $this->assertEquals($graphData['11111_22222']['post_consumptions'], 4);

        $this->assertEquals($graphData['33333_444444']['post_impressions'], 14);
        $this->assertEquals($graphData['33333_444444']['post_fan_reach'], 28);
        $this->assertEquals($graphData['33333_444444']['post_consumptions'], 42);
    }

    public function testGetPagePostInsightsMetricDataReturnsEmptyOnNullResponse()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturnNull()
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $data = $facebook->getPagePostInsightsMetricData(
            self::FB_PAGE_ID,
            self::FB_POST_ID,
            ['post_impressions', 'post_fan_reach', 'post_consumptions']
        );

        $this->assertEquals($data, []);
    }

    public function testGetPagePostInsightsMetricData()
    {
        $decodedResponseData = [
            'data' => [
                [
                    'name' => 'post_impressions',
                    'period' => 'lifetime',
                    'values' => [['value' => 5]]
                ],
                [
                    'name' => 'post_fan_reach',
                    'period' => 'lifetime',
                    'values' => [['value' => 12]]
                ],
                [
                    'name' => 'post_consumptions',
                    'period' => 'lifetime',
                    'values' => [['value' => 4]]
                ],
            ]
        ];

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn($decodedResponseData)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('sendRequest')->once()->andReturn($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $insightsData = $facebook->getPagePostInsightsMetricData(
            self::FB_PAGE_ID,
            self::FB_POST_ID,
            ['post_impressions', 'post_fan_reach', 'post_consumptions']
        );


        $this->assertEquals($insightsData['post_impressions'], 5);
        $this->assertEquals($insightsData['post_fan_reach'], 12);
        $this->assertEquals($insightsData['post_consumptions'], 4);
    }

    public function testGetPagePostsShouldReturnEmptyOnEmptyBody()
    {
        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getGraphEdge')
            ->once()
            ->andReturn(null)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('get')->once()->andThrow($responseMock);
        $facebook->setFacebookLibrary($facebookMock);

        $posts = $facebook->getPagePosts(
            self::FB_PAGE_ID,
            strtotime("yesterday"),
            strtotime("now"),
            10
        );

        $this->assertEquals($posts, []);
    }

    public function testGetPagePostsShouldReturnCorrectData()
    {
        $graphEdge = new GraphEdge(new FacebookRequest(), [
            new GraphNode([
                'created_time' => DateTime::createFromFormat(DATE_ISO8601, '2017-04-20T17:50:27+0000'),
                'id' => '511222705738444_744511765742869'
            ]),
            new GraphNode([
                'created_time' => DateTime::createFromFormat(DATE_ISO8601, '2017-04-19T18:23:52+0000'),
                'id' => '511222705738444_744029602457752'
            ]),
            new GraphNode([
                'created_time' => DateTime::createFromFormat(DATE_ISO8601, '2017-04-19T18:20:58+0000'),
                'id' => '511222705738444_744027942457918'
            ])
        ]);

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getGraphEdge')
            ->once()
            ->andReturn($graphEdge)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('get')->once()->andReturn($responseMock);
        $facebookMock->shouldReceive('next')->once()->withArgs([$graphEdge])->andReturn(null);
        $facebook->setFacebookLibrary($facebookMock);

        $posts = $facebook->getPagePosts(
            self::FB_PAGE_ID,
            strtotime("yesterday"),
            strtotime("now"),
            10
        );

        $this->assertEquals(count($posts), 3);
        $this->assertEquals($posts["511222705738444_744511765742869"], "2017-04-20T17:50:27+0000");
        $this->assertEquals($posts["511222705738444_744029602457752"], "2017-04-19T18:23:52+0000");
        $this->assertEquals($posts["511222705738444_744027942457918"], "2017-04-19T18:20:58+0000");
    }

    public function testGetPagePostsShouldUseRightSinceAndUntilArgs()
    {
        $graphEdge = new GraphEdge(
            new FacebookRequest(),
            [
                new GraphNode([
                    'created_time' => DateTime::createFromFormat(DATE_ISO8601, '2017-04-20T17:50:27+0000'),
                    'id' => '511222705738444_744511765742869'
                ]),
            ]
        );

        $facebook = new Facebook();
        $responseMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getGraphEdge')
            ->once()
            ->andReturn($graphEdge)
            ->getMock();
        $facebookMock = m::mock('\Facebook\Facebook');

        $since  = "1493826552";
        $until = "1496418552";
        $params = [
            "limit" => 100,
            "until" => $until,
            "since" => $since,
        ];
        $expectedGetParams = ["/2222222/posts?since={$since}&until={$until}&limit=100"];
        $facebookMock->shouldReceive('get')->withArgs($expectedGetParams)->once()->andReturn($responseMock);
        $facebookMock->shouldReceive('next')->withArgs([$graphEdge])->once()->andReturn(null);
        $facebook->setFacebookLibrary($facebookMock);
        $facebook->getPagePosts(self::FB_PAGE_ID, $since, $until, 100);
    }

    public function testShouldKeepMakingCallsWhenThereArePaginatedResults()
    {
        $graphEdge = new GraphEdge(
            new FacebookRequest(),
            [
                new GraphNode([
                    'created_time' => DateTime::createFromFormat(DATE_ISO8601, '2017-04-20T16:21:23+0000'),
                    'id' => '511222705738444_744511765742869'
                ])
            ]
        );
        $notPagedResponse = new GraphEdge(
            new FacebookRequest(),
            [
                new GraphNode([
                    'created_time' => DateTime::createFromFormat(DATE_ISO8601, '2017-04-22T18:21:23+0000'),
                    'id' => '511222705738444_744511022112069'
                ])
            ]
        );
        $facebook = new Facebook();
        $responsePagedMock = m::mock('\Facebook\FacebookResponse')
            ->shouldReceive('getGraphEdge')
            ->once()
            ->andReturn($graphEdge)
            ->getMock();

        $since = "1493826552";
        $until = "1496418552";

        $facebookMock = m::mock('\Facebook\Facebook');
        $facebookMock->shouldReceive('get')->with("/2222222/posts?since={$since}&until={$until}&limit=100")->once()->andReturn($responsePagedMock);
        $facebookMock->shouldReceive('next')->withArgs([$graphEdge])->once()->andReturn($notPagedResponse);
        $facebookMock->shouldReceive('next')->withArgs([$notPagedResponse])->once()->andReturn(null);

        $facebook->setFacebookLibrary($facebookMock);
        $this->assertCount(2, $facebook->getPagePosts(self::FB_PAGE_ID, $since, $until, 100));
    }
}
