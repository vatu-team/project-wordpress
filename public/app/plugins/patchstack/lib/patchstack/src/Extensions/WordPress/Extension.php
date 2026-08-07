<?php

namespace Patchstack\Extensions\WordPress;

use Patchstack\Extensions\ExtensionInterface;

class Extension implements ExtensionInterface
{
    /**
     * WordPress specific options that we need to remember.
     *
     * @var array
     */
    public $options = [
        'patchstack_basic_firewall_roles' => ['administrator', 'editor', 'author'],
        'patchstack_whitelist' => ''
    ];

    /**
     * The request parameter values exploded into pairs.
     *
     * @var array
     */
    private $requestParams = [
        'method' => 'method',
        'rulesFile' => 'rules->file',
        'rulesRawPost' => 'rules->raw->post',
        'rulesUri' => 'rules->uri',
        'rulesHeadersAll' => 'rules->headers->all',
        'rulesHeadersKeys' => 'rules->headers->keys',
        'rulesHeadersValues' => 'rules->headers->values',
        'rulesHeadersCombinations' => 'rules->headers->combinations',
        'rulesBodyAll' => 'rules->body->all',
        'rulesBodyKeys' => 'rules->body->keys',
        'rulesBodyValues' => 'rules->body->values',
        'rulesBodyCombinations' => 'rules->body->combinations',
        'rulesParamsAll' => 'rules->params->all',
        'rulesParamsKeys' => 'rules->params->keys',
        'rulesParamsValues' => 'rules->params->values',
        'rulesParamsCombinations' => 'rules->params->combinations'
    ];

    /**
     * The core of the Patchstack plugin.
     *
     * @var P_Core
     */
    private $core;

    /**
     * Creates a new extension instance.
     *
     * @var array $options
     */
    public function __construct($options, $core)
    {
        $this->options = array_merge($this->options, $options);
        $this->core = $core;
    }

    /**
     * Log the HTTP request.
     *
     * @param  int    $ruleId
     * @param  array  $request
     * @param  string $logType
     * @return void
     */
    public function logRequest($ruleId, $request, $logType = 'BLOCK')
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }

        // Transform raw payload.
        if (is_array($request) && array_key_exists('raw', $request)) {
            $request['raw'] = isset($request['raw']) && is_array($request['raw']) ? $request['raw'][0] : $request['raw'];

            // Remove raw payload if not present.
            if ((is_array($request['raw']) && count($request['raw']) == 0) || empty($request['raw'])) {
                unset($request['raw']);
            }
        }

        // Remove files payload if not present.
        if (isset($request['files']) && is_array($request['files']) && count($request['files']) == 0) {
            unset($request['files']);
        }

        // Remove post payload if not present.
        if (isset($request['post']) && is_array($request['post']) && count($request['post']) == 0) {
            unset($request['post']);
        }

        // Insert into the logs.
        $wpdb->insert(
            $wpdb->prefix . 'patchstack_firewall_log',
            [
                'ip'          => $this->getIpAddress(),
                'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
                'user_agent'  => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'method'      => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '',
                'fid'         => '55' . $ruleId,
                'flag'        => '',
                'post_data'   => json_encode($request),
                'block_type'  => $logType
            ]
        );

        // Increment all time hits counter.
        if ($logType == 'BLOCK') {
            $hits = (int) get_option('patchstack_hits_all_time', 0);
            update_option('patchstack_hits_all_time', $hits + 1);

            $counters = get_option('patchstack_hits_last_30', []);
            $counters = $this->merge_counters([date('Y-m-d') => 1], $counters);
            update_option('patchstack_hits_last_30', $counters);
        }
    }

    /**
     * Determine if the current visitor can bypass the firewall.
     * If $isMuCall is true, we MUST avoid any function calls that checks the current authorization of the user,
     * this includes current_user_can. Otherwise, a fatal error is thrown.
     *
     * @param bool $isMuCall
     * @return bool
     */
    public function canBypass($isMuCall)
    {
        if ($isMuCall || !is_user_logged_in()) {
            return false;
        }

        // Get the whitelisted roles.
        $roles = $this->options['patchstack_basic_firewall_roles'];
        if (!is_array($roles)) {
            return false;
        }

        // Special scenario for super admins on a multisite environment.
        if (in_array('administrator', $roles) && is_multisite() && is_super_admin()) {
            return true;
        }

        // Get the roles of the user.
        $user = wp_get_current_user();
        if (!isset($user->roles) || count((array) $user->roles) == 0) {
            return false;
        }

        // Is the user in the whitelist roles list?
        $role_count = array_intersect($user->roles, $roles);
        return count($role_count) != 0;
    }

    /**
     * Determine if the visitor is blocked from the website.
     *
     * @param  int $minutes
     * @param  int $blockTime
     * @param  int $attempts
     * @return bool
     */
    public function isBlocked($minutes, $blockTime, $attempts)
    {
        // Calculate block time.
        if (empty($minutes) || empty($blockTime)) {
            $time = 30 + 60;
        } else {
            $time = $minutes + $blockTime;
        }

        // Determine if the user should be blocked.
        global $wpdb;
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COUNT(*) as blockedCount
                FROM " . $wpdb->prefix . "patchstack_firewall_log
                WHERE block_type = 'BLOCK'
                AND apply_ban = 1
                AND ip = '%s'
                AND log_date >= ('" . current_time('mysql') . "' - INTERVAL %d MINUTE)",
                [$this->getIpAddress(), $time]
            ),
            OBJECT
        );

        if (!isset($results, $results[0], $results[0]->blockedCount)) {
            return false;
        }

        return $results[0]->blockedCount > $attempts;
    }

    /**
     * The response to return when a request has been blocked.
     *
     * @param  int $fid
     * @return void
     */
    public function forceExit($fid)
    {
        status_header(403);
        send_nosniff_header();
        nocache_headers();

		// Supported by a number of popular caching plugins.
		if (!defined( 'DONOTCACHEPAGE')) {
			define('DONOTCACHEPAGE', true);
		}

        // Because WP Fastest Cache just has to be special...
        if (function_exists('wpfc_exclude_current_page')) {
            @wpfc_exclude_current_page();
        }

        include_once dirname(__FILE__) . '/../../../../../includes/views/access-denied.php';

        exit;
    }

    /**
     * Get the IP address of the request.
     *
     * @return string
     */
    public function getIpAddress()
    {
        return $this->core->get_ip();
    }

    /**
     * Get the hostname of the environment.
     * This is only used for open redirect vulnerabilities.
     * 
     * @return string
     */
    public function getHostName()
    {
        return parse_url(home_url(), PHP_URL_HOST);
    }

    /**
     * Check the custom whitelist rules defined in the backend of WordPress
     * and attempt to match it with the request.
     *
     * @return boolean
     */
    private function isWhitelistedCustom()
    {
        $whitelist = str_replace( '<?php exit; ?>', '', $this->options['patchstack_whitelist'] );
        if (empty($whitelist)) {
            return false;
        }

        // Loop through all lines.
        $lines = explode("\n", $whitelist);
        if (count($lines) === 0) {
            return false;
        }

        // Grab the IP address.
        $ip = $this->getIpAddress();

        // Loop through the whitelist entries.
        foreach ($lines as $line) {
            $t = explode(':', $line);

            if (count($t) == 2) {
                $val = strtolower(trim($t[1]));
                switch (strtolower($t[0])) {
                    case 'ip': // IP address match.
                        if ($ip == $val) {
                            return true;
                        }
                        break;
                    case 'payload': // Payload match.
                        if (count($_POST) > 0 && strpos(strtolower(print_r($_POST, true)), $val) !== false) {
                            return true;
                        }

                        if (count($_GET) > 0 && strpos(strtolower(print_r($_GET, true)), $val) !== false) {
                            return true;
                        }
                        break;
                    case 'url': // URL match.
                        if (isset($_SERVER['REQUEST_URI']) && strpos(strtolower($_SERVER['REQUEST_URI']), $val) !== false) {
                            return true;
                        }
                        break;
                }
            }
        }

        return false;
    }

    /**
     * Determine if the request is whitelisted.
     *
     * @param array $whitelistRules
     * @param array $request
     * @return boolean
     */
    public function isWhitelisted($whitelistRules, $request)
    {
        // First check if the user has custom whitelist rules configured.
        if ($this->isWhitelistedCustom()) {
            return true;
        }

        // Determine if there are any whitelist rules to process.
        if (!is_array($whitelistRules) || count($whitelistRules) == 0) {
            return false;
        }

        // Grab visitor's IP address and request data.
        $clientIp = $this->getIpAddress();
        $requests  = $request;

        foreach ($whitelistRules as $whitelist) {
            $whitelistRule = json_decode($whitelist['rule']);

            // Skip malformed rules; reading properties off null warns on PHP 8.
            if (!is_object($whitelistRule)) {
                continue;
            }

            // If an IP address match is given, determine if it matches.
            $ip = isset($whitelistRule->rules, $whitelistRule->rules->ip_address) ? $whitelistRule->rules->ip_address : null;
            if (!is_null($ip)) {
                if (strpos($ip, '*') !== false) {
                    $isWhitelistedIp = $this->check_wildcard_rule($clientIp, $ip);
                } elseif (strpos($ip, '-') !== false) {
                    $isWhitelistedIp = $this->check_range_rule($clientIp, $ip);
                } elseif (strpos($ip, '/') !== false) {
                    $isWhitelistedIp = $this->check_subnet_mask_rule($clientIp, $ip);
                } elseif ($clientIp == $ip) {
                    $isWhitelistedIp = true;
                } else {
                    $isWhitelistedIp = false;
                }
            } else {
                $isWhitelistedIp = true;
            }

            foreach ($requests as $key => $request) {
                // Treat the raw POST data string as the body contents of all values combined.
                if ($key == 'rulesRawPost') {
                    $key = 'rulesBodyAll';
                }

                if (isset($this->requestParams[$key]) && ($whitelistRule->method == $requests['method'] || $whitelistRule->method == 'ALL')) {
                    $exp = explode('->', $this->requestParams[$key]);

                    // Determine if a rule exists for this request.
                    $rule = $whitelistRule;
                    foreach ($exp as $var) {
                        if (!isset($rule->$var)) {
                            $rule = null;
                            continue;
                        }
                        $rule = $rule->$var;
                    }

                    if (!is_null($rule) && substr($key, 0, 4) == 'rule' && $this->isLegacyRuleMatch($rule, $request) && $isWhitelistedIp) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Determine if the request matches the given firewall or whitelist rule.
     *
     * @param  string       $rule
     * @param  string|array $request
     * @return bool
     */
    private function isLegacyRuleMatch($rule, $request)
    {
        $is_matched = false;
        if (is_array($request)) {
            foreach ($request as $value) {
                $is_matched = $this->isLegacyRuleMatch($rule, $value);
                if ($is_matched) {
                    return $is_matched;
                }
            }
        } else {
            return preg_match($rule, urldecode($request));
        }

        return $is_matched;
    }

    /**
     * Determine if the current request is a file upload request.
     *
     * @return boolean
     */
    public function isFileUploadRequest()
    {
        return isset($_FILES) && count($_FILES) > 0;
    }

	/**
	 * CIDR notation IP block check.
	 *
	 * @param string $ip The IP address of the user.
	 * @param string $range The range to check.
	 * @return boolean Whether or not the IP is in the range.
	 */
	public function check_subnet_mask_rule( $ip, $range )
    {
		list($range, $netmask) = explode( '/', $range, 2 );

		// A malformed netmask would throw a TypeError on the arithmetic below on
		// PHP 8; treat anything outside 0-32 as a non-match.
		if ( ! is_numeric( $netmask ) || $netmask < 0 || $netmask > 32 ) {
			return false;
		}
		$netmask = (int) $netmask;

		$range_decimal         = ip2long( $range );
		$ip_decimal            = ip2long( $ip );
		$wildcard_decimal      = pow( 2, ( 32 - $netmask ) ) - 1;
		$netmask_decimal       = ~ $wildcard_decimal;
		return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
	}

	/**
	 * Wildcard IP block check.
	 *
	 * @param string $ip The IP address of the user.
	 * @param string $rule The wildcard range to check against.
	 * @return boolean Whether or not the IP is in the wilcard range.
	 */
	public function check_wildcard_rule( $ip, $rule )
    {
		$match = explode( '*', $rule );
		$match = $match[0];
		return ( substr( $ip, 0, strlen( $match ) ) == $match );
	}

	/**
	 * IP range block check.
	 *
	 * @param string|array $ip The IP address of the user.
	 * @param string       $rule The range to check against.
	 * @return boolean Whether or not the IP is in the range.
	 */
	public function check_range_rule( $ip, $rule )
    {
		// Check if client has multiple IPs
		if ( is_array( $ip ) ) {
			$ip = $ip[0];
		}

		$first_ip  = explode( '-', $rule );
		$second_ip = explode( '-', $rule );

		$start_ip   = ip2long( $first_ip[0] );
		$end_ip     = ip2long( $second_ip[1] );
		$request_ip = ip2long( $ip );

		return ( $request_ip >= $start_ip && $request_ip <= $end_ip );
	}

    /**
     * Given an array of the current counters, merge it with the past counters.
     * 
     * @param array $newCounters
     * @param array $oldCounters
     * @return array
     */
    public function merge_counters($newCounters, $oldCounters)
    {
        // The new counters to return.
        $countersNow = [];
        $oldCounters = is_array($oldCounters) ? $oldCounters : [];
    
        // Set the range of dates we need.
        $start = new \DateTime();
        $start->modify('-30 days');
        $end = new \DateTime();
        $end->modify('+1 day');
        $interval = new \DateInterval('P1D');
        $range = new \DatePeriod($start, $interval, $end);
    
        // Set the range from -6 days to +1 day from now.
        foreach ($range as $date) {
            $formattedDate = $date->format('Y-m-d');
            if (isset($oldCounters[$formattedDate])) {
                $countersNow[$formattedDate] = $oldCounters[$formattedDate];
            } else {
                $countersNow[$formattedDate] = 0;
            }
        }
    
        // Update the counters with the ones passed from the firewall logger.
        foreach ($newCounters as $date => $hits) {
            if (isset($countersNow[$date])) {
                $countersNow[$date] += $hits;
            }
        }
    
        return $countersNow;
    }
}
