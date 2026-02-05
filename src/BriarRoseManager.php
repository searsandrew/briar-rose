<?php

namespace Searsandrew\BriarRose;

use Searsandrew\BriarRose\Clients\RestletClient;

class BriarRoseManager
{
    public function __construct(
        protected string $account,
        protected string $consumerKey,
        protected string $consumerSecret,
        protected string $tokenId,
        protected string $tokenSecret,
        protected string $restletBaseUrl,
        protected string $restBaseUrl,
        protected int $timeout = 30,
        protected int $connectTimeout = 10,
        protected bool $logRequests = false,
    ) {}

    public function restlet(): RestletClient
    {
        return new RestletClient(
            account: $this->account,
            consumerKey: $this->consumerKey,
            consumerSecret: $this->consumerSecret,
            tokenId: $this->tokenId,
            tokenSecret: $this->tokenSecret,
            restletBaseUrl: $this->restletBaseUrl,
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
            logRequests: $this->logRequests,
        );
    }

    /**
     * Placeholder for the future “normal REST API” client.
     * @todo after RESTlet is stable.
     */
    public function rest(): void
    {
        /**
         *  Planned features:
         *  - REST Record
         *  - SuiteQL
         *  - Generic request() with OAuth signing
         */
    }
}