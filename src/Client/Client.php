<?php

namespace UniAlteri\Sellsy\Client;

use UniAlteri\Curl\RequestGenerator;
use UniAlteri\Sellsy\Client\Collection\CollectionGeneratorInterface;
use UniAlteri\Sellsy\Client\Collection\CollectionInterface;
use UniAlteri\Sellsy\Client\Exception\ErrorException;
use UniAlteri\Sellsy\Client\Exception\RequestFailureException;

/**
 * Class Client
 * @package UniAlteri\Sellsy\Client
 */
class Client implements ClientInterface
{
    /**
     * @var RequestGenerator $requestGenerator
     */
    protected $requestGenerator;

    /**
     * @var CollectionGeneratorInterface
     */
    protected $collectionGenerator;

    /**
     * @var string
     */
    protected $apiUrl;

    /**
     * @var string
     */
    protected $oauthAccessToken;

    /**
     * @var string
     */
    private $oauthAccessTokenSecret;

    /**
     * @var string
     */
    protected $oauthConsumerKey;

    /**
     * @var string
     */
    private $oauthConsumerSecret;

    /**
     * @var array
     */
    protected $lastRequest;

    /**
     * @var mixed|\stdClass
     */
    protected $lastAnswer;

    /**
     * @var \DateTime
     */
    protected $now;

    /**
     * Constructor
     * @param RequestGenerator $requestGenerator
     * @param CollectionGeneratorInterface $collectionGenerator
     * @param string $apiUrl
     * @param string $oauthAccessToken
     * @param string $oauthAccessTokenSecret
     * @param string $oauthConsumerKey
     * @param string $oauthConsumerSecret
     * @param \DateTime $now To allow developer to specify date to use to compute header. By default use now
     */
    public function __construct(
        RequestGenerator $requestGenerator,
        CollectionGeneratorInterface $collectionGenerator,
        $apiUrl='',
        $oauthAccessToken='',
        $oauthAccessTokenSecret='',
        $oauthConsumerKey='',
        $oauthConsumerSecret='',
        \DateTime $now = null
    ) {
        $this->requestGenerator = $requestGenerator;
        $this->collectionGenerator = $collectionGenerator;
        $this->apiUrl = $apiUrl;
        $this->oauthAccessToken = $oauthAccessToken;
        $this->oauthAccessTokenSecret = $oauthAccessTokenSecret;
        $this->oauthConsumerKey = $oauthConsumerKey;
        $this->oauthConsumerSecret = $oauthConsumerSecret;
        $this->now = $now;
    }

    /**
     * Update the api url
     * @param string $apiUrl
     * @return $this
     */
    public function setApiUrl($apiUrl)
    {
        $this->apiUrl = $apiUrl;

        return $this;
    }

    /**
     * Get the api url
     * @return string
     */
    public function getApiUrl()
    {
        return $this->apiUrl;
    }

    /**
     * Update the OAuth access token
     * @param string $oauthAccessToken
     * @return $this
     */
    public function setOAuthAccessToken($oauthAccessToken)
    {
        $this->oauthAccessToken = $oauthAccessToken;

        return $this;
    }

    /**
     * Get the OAuth access token
     * @return string
     */
    public function getOAuthAccessToken()
    {
        return $this->oauthAccessToken;
    }

    /**
     * Update the OAuth access secret token
     * @param string $oauthAccessTokenSecret
     * @return $this
     */
    public function setOAuthAccessTokenSecret($oauthAccessTokenSecret)
    {
        $this->oauthAccessTokenSecret = $oauthAccessTokenSecret;

        return $this;
    }

    /**
     * Get the OAuth access secret token
     * @return string
     */
    public function getOAuthAccessTokenSecret()
    {
        return $this->oauthAccessTokenSecret;
    }

    /**
     * Update the OAuth consumer key
     * @param string $oauthConsumerKey
     * @return $this
     */
    public function setOAuthConsumerKey($oauthConsumerKey)
    {
        $this->oauthConsumerKey = $oauthConsumerKey;

        return $this;
    }

    /**
     * Get the OAuth consumer key
     * @return string
     */
    public function getOAuthConsumerKey()
    {
        return $this->oauthConsumerKey;
    }

    /**
     * Update the OAuth consumer secret
     * @param string $oauthConsumerSecret
     * @return $this
     */
    public function setOAuthConsumerSecret($oauthConsumerSecret)
    {
        $this->oauthConsumerSecret = $oauthConsumerSecret;

        return $this;
    }

    /**
     * Get the OAuth consumer secret
     * @return string
     */
    public function getOAuthConsumerSecret()
    {
        return $this->oauthConsumerSecret;
    }

    /**
     * Transform an array to HTTP headers OAuth string
     * @param array $oauth
     * @return string
     */
    protected function encodeHeaders(&$oauth)
    {
        $values = array();
        foreach ($oauth as $key => &$value) {
            $values[] = $key.'="'.rawurlencode($value).'"';
        }

        return 'Authorization: OAuth '.implode(', ', $values);
    }

    /**
     * Internal method to generate HTTP headers to use for the API authentication with OAuth protocol
     */
    protected function computeHeaders()
    {
        if ($this->now instanceof \DateTime) {
            $now = clone $this->now;
        } else {
            $now = new \DateTime();
        }

        //Generate HTTP headers
        $encodedKey = rawurlencode($this->oauthConsumerSecret).'&'.rawurlencode($this->oauthAccessTokenSecret);
        $oauthParams = array(
            'oauth_consumer_key' => $this->oauthConsumerKey,
            'oauth_token' => $this->oauthAccessToken,
            'oauth_nonce' => md5($now->getTimestamp() + rand(0, 1000)),
            'oauth_timestamp' => $now->getTimestamp(),
            'oauth_signature_method' => 'PLAINTEXT',
            'oauth_version' => '1.0',
            'oauth_signature' => $encodedKey
        );

        //Generate header
        return array($this->encodeHeaders($oauthParams), 'Expect:');
    }

    /**
     * @return array
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
    }

    /**
     * @return mixed|\stdClass
     */
    public function getLastAnswer()
    {
        return $this->lastAnswer;
    }

    /**
     * Method to perform a request to the api
     * @param array $requestSettings
     * @return \stdClass
     * @throws RequestFailureException is the request can not be performed on the server
     * @throws ErrorException if the server returned an error for this request
     */
    public function requestApi($requestSettings)
    {
        //Arguments for the Sellsy API
        $this->lastRequest = array(
            'request' => 1,
            'io_mode' => 'json',
            'do_in' => json_encode($requestSettings)
        );

        //Generate client request
        $request = $this->requestGenerator->getRequest();

        //Configure to contact the api with POST request and return value
        $request->setMethod('POST')
            ->setUrl($this->apiUrl)
            ->setReturnValue(true)
            ->setOptionArray(//Add custom headers and post values
                array(
                    CURLOPT_HTTPHEADER => $this->computeHeaders(),
                    CURLOPT_POSTFIELDS => $this->lastRequest,
                    CURLOPT_SSL_VERIFYPEER => !preg_match("!^https!i",$this->apiUrl)
                )
            );

        //Execute the request
        try {
            $result = $request->execute();
        } catch (\Exception $e) {
            throw new RequestFailureException($e->getMessage(), $e->getCode(), $e);
        }

        //OAuth issue, throw an exception
        if (false !== strpos($result, 'oauth_problem')){
            throw new RequestFailureException($result);
        }

        $this->lastAnswer = json_decode($result);

        //Bad request, error returned by the api, throw an error
        if (!empty($this->lastAnswer->status) && 'error' == $this->lastAnswer->status) {
            throw new ErrorException($this->lastAnswer->error);
        }

        return $this->lastAnswer;
    }

    /**
     * @return \stdClass
     */
    public function getInfos()
    {
        $requestSettings = array(
            'method' => 'Infos.getInfos',
            'params' => array()
        );

        return $this->requestApi($requestSettings);
    }

    /**
     * Return collection methods of the api for Accountdatas
     * @return CollectionInterface
     */
    public function accountData()
    {
        return $this->collectionGenerator->getCollection($this, 'Accountdatas');
    }

    /**
     * Return collection methods of the api for AccountPrefs
     * @return CollectionInterface
     */
    public function accountPrefs()
    {
        return $this->collectionGenerator->getCollection($this, 'AccountPrefs');
    }

    /**
     * Return collection methods of the api for Purchase
     * @return CollectionInterface
     */
    public function purchase()
    {
        return $this->collectionGenerator->getCollection($this, 'Purchase');
    }

    /**
     * Return collection methods of the api for Agenda
     * @return CollectionInterface
     */
    public function agenda()
    {
        return $this->collectionGenerator->getCollection($this, 'Agenda');
    }

    /**
     * Return collection methods of the api for Annotations
     * @return CollectionInterface
     */
    public function annotations()
    {
        return $this->collectionGenerator->getCollection($this, 'Annotations');
    }

    /**
     * Return collection methods of the api for Catalogue
     * @return CollectionInterface
     */
    public function catalogue()
    {
        return $this->collectionGenerator->getCollection($this, 'Catalogue');
    }

    /**
     * Return collection methods of the api for CustomFields
     * @return CollectionInterface
     */
    public function customFields()
    {
        return $this->collectionGenerator->getCollection($this, 'CustomFields');
    }

    /**
     * Return collection methods of the api for Client
     * @return CollectionInterface
     */
    public function client()
    {
        return $this->collectionGenerator->getCollection($this, 'Client');
    }

    /**
     * Return collection methods of the api for Staffs
     * @return CollectionInterface
     */
    public function staffs()
    {
        return $this->collectionGenerator->getCollection($this, 'Staffs');
    }

    /**
     * Return collection methods of the api for Peoples
     * @return CollectionInterface
     */
    public function peoples()
    {
        return $this->collectionGenerator->getCollection($this, 'Peoples');
    }

    /**
     * Return collection methods of the api for Document
     * @return CollectionInterface
     */
    public function document()
    {
        return $this->collectionGenerator->getCollection($this, 'Document');
    }

    /**
     * Return collection methods of the api for Mails
     * @return CollectionInterface
     */
    public function mails()
    {
        return $this->collectionGenerator->getCollection($this, 'Mails');
    }

    /**
     * Return collection methods of the api for Event
     * @return CollectionInterface
     */
    public function event()
    {
        return $this->collectionGenerator->getCollection($this, 'Event');
    }

    /**
     * Return collection methods of the api for Expense
     * @return CollectionInterface
     */
    public function expense()
    {
        return $this->collectionGenerator->getCollection($this, 'Expense');
    }

    /**
     * Return collection methods of the api for Opportunities
     * @return CollectionInterface
     */
    public function opportunities()
    {
        return $this->collectionGenerator->getCollection($this, 'Opportunities');
    }

    /**
     * Return collection methods of the api for Prospects
     * @return CollectionInterface
     */
    public function prospects()
    {
        return $this->collectionGenerator->getCollection($this, 'Prospects');
    }

    /**
     * Return collection methods of the api for SmartTags
     * @return CollectionInterface
     */
    public function smartTags()
    {
        return $this->collectionGenerator->getCollection($this, 'SmartTags');
    }

    /**
     * Return collection methods of the api for Stat
     * @return CollectionInterface
     */
    public function stat()
    {
        return $this->collectionGenerator->getCollection($this, 'Stat');
    }

    /**
     * Return collection methods of the api for Stock
     * @return CollectionInterface
     */
    public function stock()
    {
        return $this->collectionGenerator->getCollection($this, 'Stock');
    }

    /**
     * Return collection methods of the api for Support
     * @return CollectionInterface
     */
    public function support()
    {
        return $this->collectionGenerator->getCollection($this, 'Support');
    }

    /**
     * Return collection methods of the api for Timetracking
     * @return CollectionInterface
     */
    public function timeTracking()
    {
        return $this->collectionGenerator->getCollection($this, 'Timetracking');
    }
}