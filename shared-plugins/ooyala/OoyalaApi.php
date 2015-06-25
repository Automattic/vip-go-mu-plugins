<?php
/* Copyright 2011 © Ooyala, Inc.  All rights reserved.
 *
 * Ooyala, Inc. (“Ooyala”) hereby grants permission, free of charge, to any
 * person or entity obtaining a copy of the software code provided in source
 * code format via this webpage and direct links contained within this webpage
 * and any associated documentation (collectively, the "Software"), to use,
 * copy, modify, merge, and/or publish the Software and, subject to
 * pass-through of all terms and conditions hereof, permission to transfer,
 * distribute and sublicense the Software; all of the foregoing subject to the
 * following terms and conditions:
 *
 * 1.   The above copyright notice and this permission notice shall be included
 *      in all copies or portions of the Software.
 *
 * 2.   For purposes of clarity, the Software does not include any APIs, but
 *      instead consists of code that may be used in conjunction with APIs that
 *      may be provided by Ooyala pursuant to a separate written agreement
 *      subject to fees.
 *
 * 3.   Ooyala may in its sole discretion maintain and/or update the Software.
 *      However, the Software is provided without any promise or obligation of
 *      support, maintenance or update.
 *
 * 4.   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 *      OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 *      MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, TITLE, AND
 *      NONINFRINGEMENT.  IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 *      LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 *      OF CONTRACT, TORT OR OTHERWISE, RELATING TO, ARISING FROM, IN
 *      CONNECTION WITH, OR INCIDENTAL TO THE SOFTWARE OR THE USE OR OTHER
 *      DEALINGS IN THE SOFTWARE.
 *
 * 5.   TO THE MAXIMUM EXTENT PERMITTED BY APPLICABLE LAW, (i) IN NO EVENT
 *      SHALL OOYALA BE LIABLE FOR ANY CONSEQUENTIAL, INCIDENTAL, INDIRECT,
 *      SPECIAL, PUNITIVE, OR OTHER DAMAGES WHATSOEVER (INCLUDING, WITHOUT
 *      LIMITATION, DAMAGES FOR LOSS OF BUSINESS PROFITS, BUSINESS
 *      INTERRUPTION, LOSS OF BUSINESS INFORMATION, OR OTHER PECUNIARY LOSS)
 *      RELATING TO, ARISING FROM, IN CONNECTION WITH, OR INCIDENTAL TO THE
 *      SOFTWARE OR THE USE OF OR INABILITY TO USE THE SOFTWARE, EVEN IF OOYALA
 *      HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES, AND (ii) OOYALA’S
 *      TOTAL AGGREGATE LIABILITY RELATING TO, ARISING FROM, IN CONNECTION
 *      WITH, OR INCIDENTAL TO THE SOFTWARE SHALL BE LIMITED TO THE ACTUAL
 *      DIRECT DAMAGES INCURRED UP TO MAXIMUM AMOUNT OF FIFTY DOLLARS ($50).
 */

define('OOYALA_API_DEFAULT_CACHE_BASE_URL', 'https://cdn.api.ooyala.com');
define('OOYALA_API_DEFAULT_BASE_URL', 'https://api.ooyala.com');
define('OOYALA_API_DEFAULT_EXPIRATION_WINDOW', 15);
define('OOYALA_API_ROUND_UP_TIME', 300);

/**
 * This class allows to communicate with Ooyala's API v2.
 */
class OoyalaApi
{
    /**
     * Holds the supported HTTP methods
     *
     * @var array
     */
    private static $supportedMethods = array('GET', 'POST', 'DELETE', 'PUT',
        'PATCH');

    /**
     * Holds the secret key, that can be found in the developers tab from
     * Backlot (http://ooyala.com/backlot/web).
     *
     * @var string
     */
    public $secretKey;

    /**
     * Holds the api key, that can be found in the developers tab from Backlot
     * (http://ooyala.com/backlot/web).
     *
     * @var string
     */
    public $apiKey;

    /**
     * Holds the base URL where requests are going to be made to. Defaults to
     * https://api.ooyala.com.
     *
     * @var string
     */
    public $baseUrl;

    /**
     * Holds the cache base URL where requests are going to be made to.
     * Defaults to https://cdn.api.ooyala.com.
     *
     * @var string
     */
    public $cacheBaseUrl;

    /**
     * Holds the expiration window. This value is added to the current time. It
     * should be in seconds, and represent the time that the request is valid.
     *
     * @var int
     */
    public $expirationWindow;

    /**
     * Holds the HTTP request object. This interacts with cURL, in order to make
     * requests to the API.
     *
     * @var OoyalaHttpRequest
     */
    public $httpRequest;

    /**
     * Constructor. Takes the secret and api keys.
     *
     * Examples:
     * $ooyalaApi = new OoyalaApi('7ab06', '329b5');
     * $ooyalaApi = new OoyalaApi('7ab06', '329b5', array(
     *     'baseUrl' => 'https://api.ooyala.com',
     *     'expirationWindow' => 20));
     * 
     * @param string $apiKey    Backlot's API key.
     * @param string $secretKey Backlot's secret key.
     * @param array  $options   Extra options to override the baseUrl and
     *                          expirationWindow. These should be specified
     *                          as the values from the keys in this array.
     */
    function __construct($apiKey, $secretKey, $options = array())
    {
        $this->apiKey    = $apiKey;
        $this->secretKey = $secretKey;
        $this->baseUrl   = array_key_exists('baseUrl', $options) ?
            $options['baseUrl'] : OOYALA_API_DEFAULT_BASE_URL;
        $this->cacheBaseUrl = array_key_exists('cacheBaseUrl', $options) ?
            $options['cacheBaseUrl'] : OOYALA_API_DEFAULT_CACHE_BASE_URL;
        $this->expirationWindow = isset($options['expirationWindow']) ?
            $options['expirationWindow'] : OOYALA_API_DEFAULT_EXPIRATION_WINDOW;
        $this->httpRequest = new OoyalaHttpRequest(array(
            'shouldFollowLocation' => true,
            'contentType' => 'application/json',
            'curlOptions' => array(CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false)));
    }

    /**
     * Generates a GET request to the Ooyala API.
     * @param string $requestPath The path of the resource from the request.
     * @param array  $queryParams The associative array with GET parameters.
     *                            Defaults to array().
     * @return string the response body.
     * @throws OoyalaRequestErrorException if an error occurs.
     */
    public function get($requestPath, $queryParams = array())
    {
        return $this->sendRequest('GET', $requestPath, $queryParams);
    }

    /**
     * Generates a POST request to the Ooyala API.
     * @param string $requestPath The path of the resource from the request.
     * @param array  $requestBody The POST data to send. Defaults to array().
     * @param array  $queryParams The associative array with GET parameters.
     *                            Defaults to array().
     * @return string the response body.
     * @throws OoyalaRequestErrorException if an error occurs.
     */
    public function post($requestPath, $requestBody = array(),
        $queryParams = array()
    ) {
        if(empty($requestBody)) {
            $requestBody = "";
        } else {
            $requestBody = json_encode($requestBody);
        }
        return $this->sendRequest('POST', $requestPath, $queryParams,
            $requestBody);
    }

    /**
     * Generates a PUT request to the Ooyala API.
     * @param string $requestPath The path of the resource from the request.
     * @param array  $requestBody The POST data to send. Defaults to array().
     * @param array  $queryParams The associative array with GET parameters.
     *                            Defaults to array().
     * @return string the response body.
     * @throws OoyalaRequestErrorException if an error occurs.
     */
    public function put($requestPath, $requestBody = array(),
        $queryParams = array()
    ) {
        if(empty($requestBody)) {
            $requestBody = "";
        } else {
            $requestBody = json_encode($requestBody);
        }
        return $this->sendRequest('PUT', $requestPath, $queryParams,
            $requestBody);
    }

    /**
     * Generates a PATCH request to the Ooyala API.
     * @param string $requestPath The path of the resource from the request.
     * @param array  $requestBody The POST data to send. Defaults to array().
     * @param array  $queryParams The associative array with GET parameters.
     *                            Defaults to array().
     * @return string the response body.
     * @throws OoyalaRequestErrorException if an error occurs.
     */
    public function patch($requestPath, $requestBody = array(),
        $queryParams = array()
    ) {
        if(empty($requestBody)) {
            $requestBody = "";
        } else {
            $requestBody = json_encode($requestBody);
        }
        return $this->sendRequest('PATCH', $requestPath, $queryParams,
            $requestBody);
    }

    /**
     * Generates a DELETE request to the Ooyala API.
     * @param string $requestPath The path of the resource from the request.
     * @param array  $queryParams The associative array with GET parameters.
     *                            Defaults to array().
     * @param string $requestBody The POST data to send. Defaults to "".
     * @return string the response body.
     * @throws OoyalaRequestErrorException if an error occurs.
     */
    public function delete($requestPath, $queryParams = array()) {
        return $this->sendRequest('DELETE', $requestPath, $queryParams);
    }

    /**
     * Generates the signature for a request. If the method is GET, then it does
     * not need to add the body of the request to the signature. On the other
     * hand, if it's either a POST, PUT or PATCH, the request body should be a
     * JSON serialized object into a String. The resulting signature should be
     * added as a GET parameter to the request.
     *
     * @param string $httpMethod  Either GET, DELETE POST, PUT or PATCH.
     * @param string $requestPath The path of the resource from the request.
     * @param array  $queryParams An associative array that contains GET params.
     * @param string $requestBody The contents of the request body. Used when
     *                            doing a POST, PATCH or PUT requests. Defaults
     *                            to "".
     *
     * @return string The signature that shiuld be added as a query parameter to
     *                the URI of the request.
     */
    public function generateSignature($httpMethod, $requestPath, $queryParams,
        $requestBody = ""
    ) {
        $stringToSign  = $this->secretKey . strtoupper($httpMethod);
        $stringToSign .= $requestPath;
        ksort($queryParams);
        foreach($queryParams as $key => $value) {
            $stringToSign .= $key . "=" . $value;
        }
        $stringToSign .= $requestBody;

        $signature = base64_encode(hash('sha256', $stringToSign, true));
        $signature = urlencode(substr($signature, 0, 43));
        return rtrim($signature, '=');
    }

    /**
     * Builds the URL for a request, appends the query parameters.
     *
     * @param string $httpMethod  Either GET, POST, PUT, DELETE or PATCH.
     * @param string $requestPath The absolute path for the URL to build.
     * @param array  $queryParams An associative array with the parameters to
     *                            add to the URL. Defaults to array().
     *
     * @return string The built URL.
     */
    public function buildURL($httpMethod, $requestPath, $queryParams = array())
    {
        $params = array();
        $url    = $httpMethod == 'GET' ? $this->cacheBaseUrl : $this->baseUrl;
        $url   .= $requestPath . '?';
        foreach($queryParams as $key => $value) {
            $params[] = "$key=$value";
        }
        return $url . implode('&', $params);
    }

    /**
     * Sends a request to a given path using the passed HTTP Method.
     *
     * @param string $httpMethod  Either GET, POST, PUT, DELETE or PATCH.
     * @param string $requestPath The relative path of the request.
     * @param array  $queryParams An associative array with the parameters to
     *                            add to the URL. Defaults to array().
     * @param string $requestBody The body of the request, used when doing a
     *                            POST, PUT or PATCH request. Defaults to "".
     *
     * @return array The JSON parsed response if it was success.
     * @throws OoyalaMethodNotSupportedException if the HTTP method is not
     *                                           supported.
     * @throws OoyalaRequestErrorException if there was an error sending the
     *                                     request.
     */
    public function sendRequest($httpMethod, $requestPath,
        $queryParams = array(), $requestBody = ''
    ) {
        if(substr($requestPath, 0, 4) != '/v2/')
            $requestPath = '/v2/' . $requestPath;
        $httpMethod = strtoupper($httpMethod);
        if(!in_array($httpMethod, self::$supportedMethods)) {
            throw new OoyalaMethodNotSupportedException('Method not supported '
                . $httpMethod);
        }
        $params = $this->sanitizeAndAddNeededParams($queryParams);
        $params['signature'] = $this->generateSignature($httpMethod,
            $requestPath, array_merge($params, $queryParams), $requestBody);
        $url = $this->buildURL($httpMethod, $requestPath, $params);

        $response = $this->httpRequest->execute($httpMethod, $url,
            array('payload' => $requestBody));
        return json_decode($response['body']);
    }

    private function sanitizeAndAddNeededParams($params)
    {
        foreach($params as $key => $value) {
            $params[$key] = urlencode($value);
        }
        if(!array_key_exists('expires', $params)) {
            $expiration  = time() + $this->expirationWindow;
            $params['expires'] = $expiration + OOYALA_API_ROUND_UP_TIME -
                ($expiration%OOYALA_API_ROUND_UP_TIME);
        }
        if(!array_key_exists('api_key', $params)) {
            $params['api_key'] = $this->apiKey;
        }
        return $params;
    }
}

/**
 * This class mimics an Object Oriented HTTP Client. Underneath it uses PHP's
 * cURL.
 */
class OoyalaHttpRequest
{
    /**
     * Holds the main options.
     */
    private static $optionKeys = array('contentType', 'curlOptions',
        'shouldFollowLocation');

    /**
     * Holds the default content type for all the requests.
     * @var string
     */
    public $contentType;

    /**
     * Holds an associative array with curl options.
     * @see http://mx.php.net/manual/en/function.curl-setopt.php for the
     *      available options.
     * @var array
     */
    public $curlOptions;

    /**
     * Set if requests should follow location.
     * @var boolean
     */
    public $shouldFollowLocation;

    /**
     * Constructor.
     * @param array  $options An associative array that contains default options
     *                        for the requests. Defaults to array().
     *                        'contentType' => Set the content type for all
     *                                         requests.
     *                        'curlOptions' => cURL options that will be added
     *                                         to all requests. Defaults to
     *                                         array().
     *                        'shouldFollowLocation' => If it should follow 300
     *                                                  responses. Defaults to
     *                                                  false.
     */
    function __construct($options = array())
    {
        $this->curlOptions = array();
        $this->shouldFollowLocation = false;
        $this->applyOptions($options);
    }

    /**
     * Makes the request.
     * @param string $method  the HTTP method to perform.
     * @param string $url     the URL to request.
     * @param array  $options extra options to override the default ones.
     * @return array Associative array with the status and body keys.
     * @throws OoyalaRequestErrorException if the request failed to execute.
     */
    public function execute($method, $url, $options = array())
    {
        $options = $this->extractOptions($options);
        $ch      = curl_init($url);
        $method  = strtoupper($method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,
            $options['shouldFollowLocation']);
        if(array_key_exists('contentType', $options)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER,
                array('Content-Type: ' . $options['contentType']));
        }
        if(array_key_exists('payload', $options) && strlen($options['payload'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['payload']);
        }
        curl_setopt_array($ch, $options['curlOptions']);

        $response = curl_exec($ch);
        if($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new OoyalaRequestErrorException("cURL Error $error");
        }

        $headers  = curl_getinfo($ch);
        $httpCode = $headers["http_code"];
        curl_close($ch);

        if($httpCode < 400) {
            return array('status' => $httpCode,
                'body' => $response);
        }

        $error = "HTTP Error ($httpCode), Response: $response.";
        throw new OoyalaRequestErrorException($error);
    }

    private function applyOptions($options)
    {
        foreach(self::$optionKeys as $key) {
            if(array_key_exists($key, $options))
                $this->$key = $options[$key];
        }
    }

    private function extractOptions($options)
    {
        $result = array();
        foreach(self::$optionKeys as $key) {
            if(array_key_exists($key, $options)) {
                $result[$key] = $options[$key];
                unset($options[$key]);
            } else if(isset($this->$key)) {
                $result[$key] = $this->$key;
            }
        }
        return array_merge($result, $options);
    }
}

class OoyalaMethodNotSupportedException extends Exception {}
class OoyalaRequestErrorException extends Exception {}
