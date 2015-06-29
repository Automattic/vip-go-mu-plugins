<?php
/**
 *
 * Makes HTTP Request to Sailthru API server
 * Response from server depends on the format being queried
 * if 'json' format is requested, client will recieve JSON object and 'php' is requested, client will recieve PHP array
 * XML format is also available but not has not been tested thoroughly
 *
 */
class Sailthru_Client {
    /**
     *
     * Sailthru API Key
     * @var string
     */
    protected $api_key;

    /**
     *
     * Sailthru Secret
     * @var string
     */
    protected $secret;

    /**
     *
     * Sailthru API URL, can be different for different users according to their settings
     * @var string
     */
    protected $api_uri = 'https://api.sailthru.com';

    /**
     *
     * cURL or non-cURL request
     * @var string
     */
    protected $http_request_type;

    /**
     *
     * User agent making request to Sailthru API server
     * Even, if you modify user-agent, please try to include 'PHP5' somewhere in the user-agent
     * @var String
     */
    protected $user_agent_string;

    /**
     * Get information regarding last response from server
     * @var type
     */
    private $lastResponseInfo = null;


    /**
     * File Upload Flag variable
     */
    private $fileUpload = false;

    private $httpHeaders = array("User-Agent: Sailthru API PHP5 Client");


    /**
     * Instantiate a new client; constructor optionally takes overrides for api_uri and whether
     * to share the version of PHP that is being used.
     *
     * @param string $api_key
     * @param string $secret
     * @param string $api_uri
     * @param boolean $show_version
     */
    public function  __construct($api_key, $secret, $api_uri = false) {
        $this->api_key = $api_key;
        $this->secret = $secret;
        if ($api_uri !== false) {
            $this->api_uri = $api_uri;
        }

        $this->http_request_type = function_exists('curl_init') ? 'httpRequestCurl' : 'httpRequestWithoutCurl';
    }

    public function setHttpHeaders(array $headers) {
        $this->httpHeaders = array_merge($this->httpHeaders, $headers);
        return true;
    }


    /**
     * Remotely send an email template to a single email address.
     *
     * If you pass the $schedule_time parameter, the send will be scheduled for a future time.
     *
     * Options:
     *   replyto: override Reply-To header
     *   test: send as test email (subject line will be marked, will not count towards stats)
     *
     * @param string $template_name
     * @param string $email
     * @param array $vars
     * @param array $options
     * @param string $schedule_time
     * @link http://docs.sailthru.com/api/send
     */
    public function send($template, $email, $vars = array(), $options = array(), $schedule_time = null) {
        $post = array();
        $post['template'] = $template;
        $post['email'] = $email;
        $post['vars'] = $vars;
        $post['options'] = $options;
        if ($schedule_time) {
            $post['schedule_time'] = $schedule_time;
        }
        $result = $this->apiPost('send', $post);
        return $result;
    }

    /**
     * Remotely send an email template to multiple email addresses.
     *
     * Use the evars parameter to set replacement vars for a particular email address.
     *
     * @param string $template_name
     * @param array $emails
     * @param array $vars
     * @param array $evars
     * @param array $options
     * @link http://docs.sailthru.com/api/send
     */
    public function multisend($template_name, $emails, $vars = array(), $evars = array(), $options = array()) {
        $post['template'] = $template_name;
        $post['email'] = is_array($emails) ? implode(',', $emails) : $emails;
        $post['vars'] = $vars;
        $post['evars'] = $evars;
        $post['options'] = $options;
        $result = $this->apiPost('send', $post);
        return $result;
    }


    /**
     * Get the status of a send.
     *
     * @param string $send_id
     * @link http://docs.sailthru.com/api/send
     */
    public function getSend($send_id) {
        return $this->apiGet('send', array('send_id' => $send_id));
    }


    /**
     * Cancel a send that was scheduled for a future time.
     *
     * @param string $send_id
     * @link http://docs.sailthru.com/api/send
     */
    public function cancelSend($send_id) {
        return $this->apiDelete('send', array('send_id' => $send_id));
    }


    /**
     * Return information about an email address, including replacement vars and lists.
     *
     * @param string $email
     * @param array $options
     * @link http://docs.sailthru.com/api/email
     */
    public function getEmail($email, array $options = array()) {
        return $this->apiGet('email', array_merge(array('email' => $email), $options));
    }


    /**
     * Set replacement vars and/or list subscriptions for an email address.
     *
     * $lists should be an assoc array mapping list name => 1 for subscribed, 0 for unsubscribed
     *
     * @param string $email
     * @param array $vars
     * @param array $lists
     * @param array $templates
     * @param integer $verified 1 or 0
     * @param string $optout
     * @param string $send
     * @param array $send_vars
     * @link http://docs.sailthru.com/api/email
     */
    public function setEmail($email, $vars = array(), $lists = array(), $templates = array(), $verified = 0, $optout = null, $send = null, $send_vars = array()) {
        $data = array('email' => $email);
        if ($vars) {
            $data['vars'] = $vars;
        }
        if ($lists) {
            $data['lists'] = $lists;
        }
        if ($templates) {
            $data['templates'] = $templates;
        }
        $data['verified'] = (int)$verified;
        if ($optout !== null)   {
            $data['optout'] = $optout;
        }
        if ($send !== null) {
            $data['send'] = $send;
        }
        if (!empty($send_vars)) {
            $data['send_vars'] = $send_vars;
        }

        return $this->apiPost('email', $data);
    }


    /**
     * Update / add email address
     *
     * @link http://docs.sailthru.com/api/email
     */
    public function setEmail2($email, array $options = array()) {
        $options['email'] = $email;
        return $this->apiPost('email', $options);
    }


    /**
     * Schedule a mass mail blast
     *
     * @param string $name the name to give to this new blast
     * @param string $list the mailing list name to send to
     * @param string $schedule_time when the blast should send. Dates in the past will be scheduled for immediate delivery. Any English textual datetime format known to PHP's strtotime function is acceptable, such as 2009-03-18 23:57:22 UTC, now (immediate delivery), +3 hours (3 hours from now), or February 14, 9:30 EST. Be sure to specify a timezone if you use an exact time.
     * @param string $from_name the name appearing in the "From" of the email
     * @param string $from_email The email address to use as the "from" – choose from any of your verified emails
     * @param string $subject the subject line of the email
     * @param string $content_html the HTML-format version of the email
     * @param string $content_text the text-format version of the email
     * @param array $options associative array
     *         blast_id
     *         copy_blast
     *         copy_template
     *         replyto
     *         report_email
     *         is_link_tracking
     *         is_google_analytics
     *         is_public
     *         suppress_list
     *         test_vars
     *         email_hour_range
     *         abtest
     *         test_percent
     *         data_feed_url
     * @link http://docs.sailthru.com/api/blast
     */
    public function scheduleBlast($name, $list, $schedule_time, $from_name,
        $from_email, $subject, $content_html, $content_text, $options = array()
    ) {
        $data = $options;
        $data['name'] = $name;
        $data['list'] = $list;
        $data['schedule_time'] = $schedule_time;
        $data['from_name'] = $from_name;
        $data['from_email'] = $from_email;
        $data['subject'] = $subject;
        $data['content_html'] = $content_html;
        $data['content_text'] = $content_text;

        return $this->apiPost('blast', $data);
    }


    /**
     * Schedule a mass mail from a template
     *
     * @param String $template
     * @param String $list
     * @param String $schedule_time
     * @param Array $options
     * @link http://docs.sailthru.com/api/blast
     **/
    public function scheduleBlastFromTemplate($template, $list, $schedule_time, $options = array()) {
        $data = $options;
        $data['copy_template'] = $template;
        $data['list'] = $list;
        $data['schedule_time'] = $schedule_time;
        return $this->apiPost('blast', $data);
    }


    /**
     * Schedule a mass mail blast from previous blast
     *
     * @param String|Integer $blast_id
     * @param String $schedule_time
     * @param array $options
     * @link http://docs.sailthru.com/api/blast
     **/
    public function scheduleBlastFromBlast($blast_id, $schedule_time, $options = array()) {
        $data = $options;
        $data['copy_blast'] = $blast_id;
        $data['schedule_time'] = $schedule_time;
        return $this->apiPost('blast', $data);
    }


    /**
     * updates existing blast
     *
     * @param string/integer $blast_id
     * @param string $name
     * @param string $list
     * @param string $schedule_time
     * @param string $from_name
     * @param string $from_email
     * @param string $subject
     * @param string $content_html
     * @param string $content_text
     * @param array $options associative array
     *         blast_id
     *         copy_blast
     *         copy_template
     *         replyto
     *         report_email
     *         is_link_tracking
     *         is_google_analytics
     *         is_public
     *         suppress_list
     *         test_vars
     *         email_hour_range
     *         abtest
     *         test_percent
     *         data_feed_url
     * @link http://docs.sailthru.com/api/blast
     */
    public function updateBlast($blast_id, $name = null, $list = null,
        $schedule_time = null, $from_name = null, $from_email = null,
        $subject = null, $content_html = null, $content_text = null,
        $options = array()
    ) {
        $data = $options;
        $data['blast_id'] = $blast_id;
        if (!is_null($name)) {
            $data['name'] = $name;
        }
        if (!is_null($list)) {
            $data['list'] = $list;
        }
        if (!is_null($schedule_time)) {
            $data['schedule_time'] = $schedule_time;
        }
        if (!is_null($from_name))  {
            $data['from_name'] = $from_name;
        }
        if (!is_null($from_email)) {
            $data['from_email'] = $from_email;
        }
        if (!is_null($subject)) {
            $data['subject'] = $subject;
        }
        if (!is_null($content_html)) {
            $data['content_html'] = $content_html;
        }
        if (!is_null($content_text)) {
            $data['content_text'] = $content_text;
        }

        return $this->apiPost('blast', $data);
    }


    /**
     * Get Blast information
     * @param string/integer $blast_id
     * @link http://docs.sailthru.com/api/blast
     */
    public function getBlast($blast_id) {
        return $this->apiGet('blast', array('blast_id' => $blast_id));
    }

    /**
     * Get info on multiple blasts
     * @param array $options associative array
     *       start_date (required)
     *       end-date (required)
     *       status
     * @link http://docs.sailthru.com/api/blast
     */
    public function getBlasts($options) {
        return $this->apiGet('blast', $options);
    }


    /**
     * Delete Blast
     * @param ineteger/string $blast_id
     * @link http://docs.sailthru.com/api/blast
     */
    public function deleteBlast($blast_id) {
        return $this->apiDelete('blast', array('blast_id' => $blast_id));
    }


    /**
     * Cancel a scheduled Blast
     * @param ineteger/string $blast_id
     * @link http://docs.sailthru.com/api/blast
     */
    public function cancelBlast($blast_id) {
        $data = array(
            'blast_id' => $blast_id,
            'schedule_time' => ''
        );
        return $this->apiPost('blast', $data);
    }


    /**
     * Fetch information about a template
     *
     * @param string $template_name
     * @link http://docs.sailthru.com/api/template
     */
    public function getTemplate($template_name, array $options = array()) {
        $options['template'] = $template_name;
        return $this->apiGet('template', $options);
    }


    /**
     * Fetch name of all existing templates
     * @link http://docs.sailthru.com/api/template
     */
    public function getTemplates() {
        return $this->apiGet('template');
    }


    public function getTemplateFromRevision($revision_id) {
        return $this->apiGet('template', array('revision' => (int)$revision_id));
    }


    /**
     * Save a template.
     *
     * @param string $template_name
     * @param array $template_fields
     * @link http://docs.sailthru.com/api/template
     */
    public function saveTemplate($template_name, array $template_fields = array()) {
        $data = $template_fields;
        $data['template'] = $template_name;
        return $this->apiPost('template', $data);
    }


    /**
     * Save a template from revision
     *
     * @param string $template_name
     * @param numeric $revision
     * @link http://docs.sailthru.com/api/template
     */
    public function saveTemplateFromRevision($template_name, $revision_id) {
        $revision_id = (int)$revision_id;
        return $this->saveTemplate($template_name, array('revision' => $revision_id));
    }


    /**
     * Delete a template.
     *
     * @param string $template_name
     * @param array $template_fields
     * @link http://docs.sailthru.com/api/template
     */
    public function deleteTemplate($template_name) {
        return $this->apiDelete('template', array('template' => $template_name));
    }


    /**
     * Fetch information about an include
     *
     * @param string $include_name
     */
    public function getInclude($include_name, array $options = array()) {
        $options['include'] = $include_name;
        return $this->apiGet('include', $options);
    }


    /**
     * Fetch name of all existing includes
     */
    public function getIncludes() {
        return $this->apiGet('include');
    }


    /**
     * Save an include
     *
     * @param string $include_name
     * @param array $include_fields
     */
    public function saveInclude($include_name, array $include_fields = array()) {
        $data = $include_fields;
        $data['include'] = $include_name;
        return $this->apiPost('include', $data);
    }


    /**
     * Get information about a list.
     *
     * @param string $list
     * @return array
     * @link http://docs.sailthru.com/api/list
     */
    public function getList($list) {
        return $this->apiGet('list', array('list' => $list));
    }


    /**
     * Get information about all lists
     *
     * @param string $list
     * @param string $emails
     * @return array
     * @link http://docs.sailthru.com/api/list
     */
    public function getLists() {
        return $this->apiGet('list', array());
    }


    /**
     * Create a list, or update a list.
     *
     * @param string $list
     * @param string $list
     * @param string $type
     * @param bool $primary
     * @param array $query
     * @return array
     * @link http://docs.sailthru.com/api/list
     * @link http://docs.sailthru.com/api/query
     */
    public function saveList($list, $type = null, $primary = null, $query = array()) {
        $data = array(
            'list'    => $list,
            'type'    => $type,
            'primary' => $primary ? 1 : 0,
            'query'   => $query,
        );
        return $this->apiPost('list', $data);
    }


    /**
     * Deletes a list.
     *
     * @param string $list
     * @return array
     * @link http://docs.sailthru.com/api/list
     */
    public function deleteList($list) {
        return $this->apiDelete('list', array('list' => $list));
    }


    /**
     *
     * Push a new piece of content to Sailthru, triggering any applicable alerts.
     *
     * @param String $title
     * @param String $url
     * @param String $date
     * @param Mixed $tags Null for empty values, or String or arrays
     * @link http://docs.sailthru.com/api/content
     */
    public function pushContent($title, $url, $date = null, $tags = null, $vars = array()) {
        $data = array();
        $data['title'] = $title;
        $data['url'] = $url;
        if (!is_null($tags)) {
            $data['tags'] = is_array($tags) ? implode(",", $tags) : $tags;
        }
        if (!is_null($date)) {
            $data['date'] = $date;
        }
        if (!empty($vars)) {
            $data['vars'] = $vars;
        }
        return $this->apiPost('content', $data);
    }


    /**
     *
     * Retrieve a user's alert settings.
     *
     * @link http://docs.sailthru.com/api/alert
     * @param String $email
     */
    public function getAlert($email) {
        $data = array(
            'email' => $email
        );
        return $this->apiGet('alert', $data);
    }


    /**
     *
     * Add a new alert to a user. You can add either a realtime or a summary alert (daily/weekly).
     * $when is only required when alert type is weekly or daily
     *
     * <code>
     * <?php
     * $options = array(
     *     'match' => array(
     *         'type' => array('shoes', 'shirts'),
     *         'min' => array('price' => 3000),
     *         'tags' => array('blue', 'red'),
     *     )
     * );
     * $response = $sailthruClient->saveAlert("praj@sailthru.com", 'realtime', 'default', null, $options);
     * ?>
     * </code>
     *
     * @link http://docs.sailthru.com/api/alert
     * @param String $email
     * @param String $type
     * @param String $template
     * @param String $when
     * @param array $options Associative array of additive nature
     *         match  Exact-match a custom variable  match[type]=shoes
     *         min    Minimum-value variables        min[price]=30000
     *         max    Maximum-value match            max[price]=50000
     *         tags   Tag-match                      tags[]=blue
     */
    public function saveAlert($email, $type, $template, $when = null, $options = array()) {
        $data = $options;
        $data['email'] = $email;
        $data['type'] = $type;
        $data['template'] = $template;
        if ($type == 'weekly' || $type == 'daily') {
            $data['when'] = $when;
        }
        return $this->apiPost('alert', $data);
    }


    /**
     * Remove an alert from a user's settings.
     * @link http://docs.sailthru.com/api/alert
     * @param <type> $email
     * @param <type> $alert_id
     */
    public function deleteAlert($email, $alert_id) {
        $data = array(
            'email' => $email,
            'alert_id' => $alert_id
        );
        return $this->apiDelete('alert', $data);
    }


    /**
     * Record that a user has made a purchase, or has added items to their purchase total.
     * @link http://docs.sailthru.com/api/purchase
     */
    public function purchase($email, array $items, $incomplete = null, $message_id = null, array $options = array()) {
        $data = $options;
        $data['email'] = $email;
        $data['items'] = $items;
        if (!is_null($incomplete)) {
            $data['incomplete'] = (int)$incomplete;
        }
        if (!is_null($message_id)) {
            $data['message_id'] = $message_id;
        }
        return $this->apiPost('purchase', $data);
    }


    /**
     * Make a purchase API call with incomplete flag
     * @link http://docs.sailthru.com/api/purchase
     */
    public function purchaseIncomplete($email, array $items, $message_id, array $options = array()) {
        return $this->purchase($email, $items, 1, $message_id, $options);
    }


    /**
     * Retrieve information about your subscriber counts on a particular list, on a particular day.
     * @link http://docs.sailthru.com/api/stats
     * @param String $list
     * @param String $date
     */
    public function stats_list($list = null, $date = null) {
        $data = array();
        if (!is_null($list)) {
            $data['list'] = $list;
        }

        if (!is_null($date)) {
            $data['date'] = $date;
        }
        $data['stat'] = 'list';
        return $this->stats($data);
    }


    /**
     * Retrieve information about a particular blast or aggregated information from all of blasts over a specified date range.
     * @param array $data
     */
    public function stats_blast($blast_id = null, $start_date = null, $end_date = null, array $data = array()) {
        $data['stat'] = 'blast';
        if (!is_null($blast_id)) {
            $data['blast_id'] = $blast_id;
        }
        if (!is_null($start_date)) {
            $data['start_date'] = $start_date;
        }
        if (!is_null($end_date)) {
            $data['end_date'] = $end_date;
        }
        return $this->stats($data);
    }

    /**
     * Retrieve information about a particular send or aggregated information from all of templates over a specified date range.
     * @param array $data
     */
    public function stats_send($template=null, $start_date = null, $end_date = null, array $data = array()) {
        $data['stat'] = 'send';

        if (!is_null($template)) {
            $data['template'] = $template;
        }
        if (!is_null($start_date)) {
            $data['start_date'] = $start_date;
        }
        if (!is_null($end_date)) {
            $data['end_date'] = $end_date;
        }

        return $this->stats($data);
    }


    /**
     * Make Stats API Request
     * @param array $data
     */
    public function stats(array $data) {
        return $this->apiGet('stats', $data);
    }


    /**
     *
     * Returns true if the incoming request is an authenticated verify post.
     * @link http://docs.sailthru.com/api/postbacks
     * @return boolean
     */
    public function receiveVerifyPost() {
        $params = $_POST;
        foreach (array('action', 'email', 'send_id', 'sig') as $k) {
            if (!isset($params[$k])) {
                return false;
            }
        }

        if ($params['action'] != 'verify') {
            return false;
        }
        $sig = $params['sig'];
        unset($params['sig']);
        if ($sig != Sailthru_Util::getSignatureHash($params, $this->secret)) {
            return false;
        }
        $send = $this->getSend($params['send_id']);
        if (!isset($send['email'])) {
            return false;
        }
        if ($send['email'] != $params['email']) {
            return false;
        }
        return true;
    }


    /**
     *
     * Optout postbacks
     * @return boolean
     * @link http://docs.sailthru.com/api/postbacks
     */
    public function receiveOptoutPost() {
        $params = $_POST;
        foreach (array('action', 'email', 'sig') as $k) {
            if (!isset($params[$k])) {
                return false;
            }
        }

        if ($params['action'] != 'optout') {
            return false;
        }
        $sig = $params['sig'];
        unset($params['sig']);
        if ($sig != Sailthru_Util::getSignatureHash($params, $this->secret)) {
            return false;
        }
        return true;
    }


    /**
     *
     * Hard bounce postbacks
     * @return boolean
     * @link http://docs.sailthru.com/api/postbacks
     */
    public function receiveHardBouncePost(){
        $params = $_POST;
        foreach (array('action', 'email', 'sig') as $k) {
            if (!isset($params[$k])) {
                return false;
            }
        }
        if ($params['action'] != 'hardbounce') {
            return false;
        }
        $sig = $params['sig'];
        unset($params['sig']);
        if ($sig != Sailthru_Util::getSignatureHash($params, $this->secret)) {
            return false;
        }
        if (isset($params['send_id'])) {
            $send_id = $params['send_id'];
            $send = $this->getSend($send_id);
            if (!isset($send['email'])) {
                return false;
            }
        }
        else if (isset($params['blast_id'])) {
            $blast_id = $params['blast_id'];
            $blast = $this->getBlast($blast_id);
            if (isset($blast['error'])) {
                return false;
            }
        }
        return true;
    }


    /**
     *
     * Get horizon data
     * @param string $email horizon user email
     * @param boolean $hid_only if true, server will only return Horizon Id of the user
     * @link http://docs.sailthru.com/api/horizon
     */
    public function getHorizon($email, $hid_only = false) {
        $data = array('email' => $email);
        if ($hid_only === true) {
            $data['hid_only'] = 1;
        }
        return $this->apiGet('horizon', $data);
    }


    /**
     *
     * Set horizon user data
     * @param string $email
     * @param Mixed $tags Null for empty values, or String or arrays
     */
    public function setHorizon($email, $tags = null) {
        $data = array('email' => $email);
        if (!is_null($tags)) {
            $data['tag'] = is_array($tags) ? implode(",", $tags) : $tags;
        }
        return $this->apiPost('horizon', $data);
    }


    /**
     * Get status of a job
     * @param String $job_id
     */
    public function getJobStatus($job_id) {
        return $this->apiGet('job', array('job_id' => $job_id));
    }


    /**
     * process job api call
     * @param String $job
     * @param array $options
     * @param String $report_email
     * @param String $postback_url
     * @param array $binary_data_param
     */
    protected function processJob($job, array $options = array(), $report_email = false, $postback_url = false, array $binary_data_param = array()) {
        $data = $options;
        $data['job'] = $job;
        if ($report_email) {
            $data['report_email'] = $report_email;
        }
        if ($postback_url) {
            $data['postback_url'] = $postback_url;
        }
        return $this->apiPost('job', $data, $binary_data_param);
    }


    /**
     * Process import job from given email string CSV
     * @param String $list
     * @param String $emails
     * @param String $report_email
     * @param String $postback_url
     */
    public function processImportJob($list, $emails, $report_email = false, $postback_url = false) {
        $data = array(
            'emails' => $emails,
            'list' => $list
        );
        return $this->processJob('import', $data, $report_email, $postback_url);
    }


    /**
     * Process import job from given file path of a CSV or email per line file
     *
     * @param String $list
     * @param String $emails
     * @param String $report_email
     * @param String $postback_url
     *
     */
    public function processImportJobFromFile($list, $file_path, $report_email = false, $postback_url = false) {
        $data = array(
            'file' => $file_path,
            'list' => $list
        );
        return $this->processJob('import', $data, $report_email, $postback_url, array('file'));
    }


    /**
     * Process a snapshot job
     *
     * @param array $query
     * @param String $report_email
     * @param String $postback_url
     */
    public function processSnapshotJob(array $query, $report_email = false, $postback_url = false) {
        $data = array('query' => $query);
        return $this->processJob('snaphot', $data, $report_email, $postback_url);
    }


    /**
     * Process a export list job
     * @param String $list
     * @param String $report_email
     * @param String $postback_url
     */
    public function processExportListJob($list, $report_email = false, $postback_url = false) {
        $data = array('list' => $list);
        return $this->processJob('export_list_data', $data, $report_email, $postback_url);
    }


    /**
     * Export blast data in CSV format
     * @param integer $blast_id
     * @param String $report_email
     * @param String $postback_url
     */
    public function processBlastQueryJob($blast_id, $report_email = false, $postback_url = false) {
        return $this->processJob('blast_query', array('blast_id' => $blast_id), $report_email, $postback_url);
    }


    /**
     * Perform a bulk update of any number of user profiles from given context: String CSV, file, URL or query
     * @param String $context
     * @param Array $update
     * @param String $report_email
     * @param String $postback_url
     */
    public function processUpdateJob($context, $value, array $update =  array(), $report_email = false, $postback_url = false, array $file_params = array()) {
        $data = array(
            $context => $value
        );
        if (count($update) > 0) {
            $data['update'] = $update;
        }
        return $this->processJob('update', $data, $report_email, $postback_url, $file_params);
    }


    /**
     * Perform a bulk update of any number of user profiles from given URL
     * @param String $url
     * @param Array $update
     * @param String $report_email
     * @param String $postback_url
     */
    public function processUpdateJobFromUrl($url, array $update = array(), $report_email = false, $postback_url = false) {
        return $this->processUpdateJob('url', $url, $update, $report_email, $postback_url);
    }


    /**
     * Perform a bulk update of any number of user profiles from given file
     * @param String $url
     * @param Array $update
     * @param String $report_email
     * @param String $postback_url
     */
    public function processUpdateJobFromFile($file, array $update = array(), $report_email = false, $postback_url = false) {
        return $this->processUpdateJob('file', $file, $update, $report_email, $postback_url, array('file'));
    }


    /**
     * Perform a bulk update of any number of user profiles from a query
     * @param Array $query
     * @param Array $update
     * @param String $report_email
     * @param String $postback_url
     */
    public function processUpdateJobFromQuery($query, array $update = array(), $report_email = false, $postback_url = false) {
        return $this->processUpdateJob('query', $query, $update, $report_email, $postback_url);
    }


    /**
     * Perform a bulk update of any number of user profiles from emails CSV
     * @param String $emails
     * @param Array $update
     * @param String $report_email
     * @param String $postback_url
     */
    public function processUpdateJobFromEmails($emails, array $update = array(), $report_email = false, $postback_url = false) {
        return $this->processUpdateJob('emails', $emails, $update, $report_email, $postback_url);
    }


    /**
     * Save existing user
     * @param String $id
     * @param Array $options
     */
    public function saveUser($id, array $options = array()) {
        $data = $options;
        $data['id'] = $id;
        return $this->apiPost('user', $data);
    }


    /**
     * Get user by Sailthru ID
     * @param String $id
     * @param Array $fields
     */
    public function getUserBySid($id, array $fields = array()) {
        return $this->apiGet('user', array('id' => $id));
    }

    /**
     * Get user by specified key
     * @param String $id
     * @param String $key
     * @param Array $fields
     */
    public function getUserByKey($id, $key, array $fields = array()) {
        $data  = array(
            'id' => $id,
            'key' => $key,
            'fields' => $fields
        );
        return $this->apiGet('user', $data);
    }


    /**
     *
     * Set Horizon cookie
     *
     * @param string $email horizon user email
     * @param string $domain
     * @param integer $duration
     * @param boolean $secure
     * @return boolean
     */
    public function setHorizonCookie($email, $domain = null, $duration = null, $secure = false) {
        $data = $this->getHorizon($email, true);
        if (!isset($data['hid'])) {
            return false;
        }
        if (!$domain) {
            $domain_parts = explode('.', $_SERVER['HTTP_HOST']);
            $domain = $domain_parts[sizeof($domain_parts)-2] . '.' . $domain_parts[sizeof($domain_parts)-1];
        }
        if ($duration === null) {
            $expire = time() + 31556926;
        } else if ($duration) {
            $expire = time() + $duration;
        } else {
            $expire = 0;
        }
        return setcookie('sailthru_hid', $data['hid'], $expire, '/', $domain, $secure);
    }

    /**
     * Get an HTML preview of a template.
     * @param type $template
     * @param type $email
     * @return type
     * @link http://docs.sailthru.com/api/preview
     */
    public function previewTemplateWithHTML($template, $email) {
        $data = array();
        $data['template'] = $template;
        $data['email'] = $email;

        $result = $this->apiPost('preview', $data);
        return $result;
    }

    /**
     * Get an HTML preview of a blast.
     * @param type $blast_id
     * @param type $email
     * @return type
     * @link http://docs.sailthru.com/api/preview
     */
    public function previewBlastWithHTML($blast_id, $email) {
        $data = array();
        $data['blast_id'] = $blast_id;
        $data['email'] = $email;

        $result = $this->apiPost('preview', $data);
        return $result;
    }

    /**
     * Get an HTML preview of a recurring blast.
     * @param type $blast_repeat_id
     * @param type $email
     * @return type
     * @link http://docs.sailthru.com/api/preview
     */
    public function previewRecurringBlastWithHTML($blast_repeat_id, $email) {
        $data = array();
        $data['blast_repeat_id'] = $blast_repeat_id;
        $data['email'] = $email;

        $result = $this->apiPost('preview', $data);
    }

    /**
     * Get an HTML preview of content_html.
     * @param type $content_html
     * @param type $email
     * @return type
     * @link http://docs.sailthru.com/api/preview
     */
    public function previewContentWithHTML($content_html, $email) {
        $data = array();
        $data['content_html'] = $content_html;
        $data['email'] = $email;

        $result = $this->apiPost('preview', $data);
        return $result;
    }

    /**
     * Get an email preview of a template.
     * @param type $template
     * @param type $send_email
     * @return type
     * @link http://docs.sailthru.com/api/preview
     */
    public function previewTemplateWithEmail($template, $send_email) {
        $data = array();
        $data['template'] = $template;
        $data['send_email'] = $send_email;

        $result = $this->apiPost('preview', $data);
        return $result;
    }

    /**
     * Get an email preview of a blast.
     * @param type $blast_id
     * @param type $send_email
     * @return type
     * @link http://docs.sailthru.com/api/preview
     */
    public function previewBlastWithEmail($blast_id, $send_email) {
        $data = array();
        $data['blast_id'] = $blast_id;
        $data['send_email'] = $send_email;

        $result = $this->apiPost('preview', $data);
        return $result;
    }

    /**
     * Get an email preview of a recurring blast.
     * @param type $blast_repeat_id
     * @param type $send_email
     * @return type
     * @link http://docs.sailthru.com/api/preview
     */
    public function previewRecurringBlastWithEmail($blast_repeat_id, $send_email) {
        $data = array();
        $data['blast_repeat_id'] = $blast_repeat_id;
        $data['send_email'] = $send_email;

        $result = $this->apiPost('preview', $data);
        return $result;
    }

    /**
     * Get an email preview of content_html.
     * @param type $content_html
     * @param type $send_email
     * @return type
     * @link http://docs.sailthru.com/api/preview
     */
    public function previewContentWithEmail($content_html, $send_email) {
        $data = array();
        $data['content_html'] = $content_html;
        $data['send_email'] = $send_email;

        $result = $this->apiPost('preview', $data);
        return $result;
    }

    /**
     * Get information on a trigger
     * @param type $template
     * @param type $trigger_id
     * @return type
     * @link http://docs.sailthru.com/api/trigger
     */
    public function getTrigger($template, $trigger_id) {
        $data = array();
        $data['template'] = $template;
        $data['trigger_id'] = $trigger_id;

        $result = $this->apiGet('trigger', $data);
        return $result;
    }

    /**
     * Create a trigger
     * @param type $template
     * @param type $time
     * @param type $time_unit
     * @param type $event
     * @param type $zephyr
     * @return type
     * @link http://docs.sailthru.com/api/trigger
     */
    public function postTrigger($template, $time, $time_unit, $event, $zephyr) {
        $data = array();
        $data['template'] = $template;
        $data['time'] = $time;
        $data['time_unit'] = $time_unit;
        $data['event'] = $event;
        $data['zephyr'] = $zephyr;

        $result = $this->apiPost('trigger', $data);
        return $result;
    }

    /**
     * Perform an HTTP request using the curl extension
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return string
     */
    function httpRequestCurl($url, array $data, $method = 'POST') {
        
        $ch = curl_init();
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($this->fileUpload === true) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                $this->fileUpload = false;
            }
            else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
            }
        } else {
            $url .= '?' . http_build_query($data, '', '&');
            if ($method != 'GET') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->httpHeaders);
        $data = curl_exec($ch);
        $this->lastResponseInfo = curl_getinfo($ch);
        curl_close($ch);
        if (!$data) {
            throw new Sailthru_Client_Exception("Bad response received from $url");
        }
        return $data;
        
    }


    /**
     * Adapted from: http://netevil.org/blog/2006/nov/http-post-from-php-without-curl
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return string
     */
    function httpRequestWithoutCurl($url, $data, $method = 'POST') {
        if ($this->fileUpload === true) {
            $this->fileUpload = false;
            throw new Sailthru_Client_Exception('cURL extension is required for the request with file upload');
        }

        $params = array('http' => array('method' => $method));
        if ($method == 'POST') {
            $params['http']['content'] = is_array($data) ? http_build_query($data, '', '&') : $data;
        } else {
            $url .= '?' . http_build_query($data, '', '&');
        }
        $params['http']['header'] = "User-Agent: {$this->user_agent_string}\nContent-Type: application/x-www-form-urlencoded";
        $ctx = stream_context_create($params);
        $fp = @fopen($url, 'rb', false, $ctx);
        if (!$fp) {
            throw new Sailthru_Client_Exception("Unable to open stream: $url");
        }
        $response = @stream_get_contents($fp);
        if ($response === false) {
            throw new Sailthru_Client_Exception("No response received from stream: $url");
        }
        return $response;
    }


    /**
     * Perform an HTTP request, checking for curl extension support
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return string
     */
    protected function httpRequest($url, $data, $method = 'POST') {
        $response = $this->{$this->http_request_type}($url, $data, $method);
        $json = json_decode($response, true);
        if ($json === NULL) {
            throw new Sailthru_Client_Exception("Response: {$response} is not a valid JSON");
        }
        return $json;
    }


    /**
     * Perform an API POST (or other) request, using the shared-secret auth hash.
     * if binary_data_param is set, its appends '@' so that cURL can make binary POST request
     *
     * @param array $data
     * @return array
     */
    public  function apiPost($action, $data, array $binary_data_param = array()) {
        $binary_data = array();
        if (!empty ($binary_data_param)) {
            foreach ($binary_data_param as $param) {
                if (isset($data[$param]) && file_exists($data[$param])) {
                    $binary_data[$param] = "@{$data[$param]}";
                    unset($data[$param]);
                    $this->fileUpload = true;
                }
            }
        }
        $payload = $this->prepareJsonPayload($data, $binary_data);
        return $this->httpRequest("$this->api_uri/$action", $payload, 'POST');
    }


    /**
     * Perform an API GET request, using the shared-secret auth hash.
     *
     * @param string $action
     * @param array $data
     * @return array
     */
    public function apiGet($action, $data = array(), $method = 'GET') {
        return $this->httpRequest("{$this->api_uri}/{$action}", $this->prepareJsonPayload($data), $method);
    }


    /**
     * Perform an API DELETE request, using the shared-secret auth hash.
     *
     * @param string $action
     * @param array $data
     * @return array
     */
    public function apiDelete($action, $data) {
        return $this->apiGet($action, $data, 'DELETE');
    }


    /**
     * get information from last server response when used with cURL
     * returns associative array as per http://us.php.net/curl_getinfo
     * @return array or null
     */
    public function getLastResponseInfo() {
        return $this->lastResponseInfo;
    }


    /**
     * Prepare JSON payload
     */
    protected function prepareJsonPayload(array $data, array $binary_data = array()) {
        $payload =  array(
            'api_key' => $this->api_key,
            'format' => 'json', //<3 XML
            'json' => json_encode($data)
        );
        $payload['sig'] = Sailthru_Util::getSignatureHash($payload, $this->secret);
        if (!empty($binary_data)) {
            $payload = array_merge($payload, $binary_data);
        }
        return $payload;
    }
}
