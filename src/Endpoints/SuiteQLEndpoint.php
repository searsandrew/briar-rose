<?php

namespace Searsandrew\BriarRose\Endpoints;

use Illuminate\Http\Client\Response;
use Searsandrew\BriarRose\Clients\RestClient;

class SuiteQLEndpoint
{
    public function __construct(
        protected RestClient $client
    ) {}

    /**
     * Run a SuiteQL query.
     *
     * NetSuite endpoint:
     * POST /services/rest/query/v1/suiteql
     * body: { "q": "SELECT ..." }
     *
     * Optional: pass query params like ['limit' => 1000, 'offset' => 0] if you use them.
     */
    public function query(string $sql, array $queryParams = [], array $headers = []): Response
    {
        return $this->client->post(
            path: '/services/rest/query/v1/suiteql',
            json: ['q' => $sql],
            query: $queryParams,
            headers: $headers
        );
    }
}