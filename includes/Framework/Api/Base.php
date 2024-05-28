<?php
/**
 * WooCommerce Plugin Framework
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * @since     3.0.0
 * @author    WooCommerce / SkyVerge
 * @copyright Copyright (c) 2013-2019, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 */

namespace WooCommerce\Square\Framework\Api;

use WooCommerce;
use WooCommerce\Square\Framework\Square_Helper;

defined( 'ABSPATH' ) or exit;

/**
 * Base API Class
 *
 * This class provides a standardized framework for constructing an API wrapper
 * to external services. It is designed to be extremely flexible.
 */
abstract class Base {

	/** @var string request method, defaults to POST */
	protected $request_method = 'POST';

	/** @var string URI used for the request */
	protected $request_uri;

	/** @var array request headers */
	protected $request_headers = array();

	/** @var string request user-agent */
	protected $request_user_agent;

	/** @var string request HTTP version, defaults to 1.0 */
	protected $request_http_version = '1.0';

	/** @var string request duration */
	protected $request_duration;

	/** @var API_Request|object request */
	protected $request;

	/** @var string response code */
	protected $response_code;

	/** @var string response message */
	protected $response_message;

	/** @var array response headers */
	protected $response_headers;

	/** @var string raw response body */
	protected $raw_response_body;

	/** @var string response handler class name */
	protected $response_handler;

	/** @var API_Response|object response */
	protected $response;

	/**
	 * Perform the request and return the parsed response
	 *
	 * @since 3.0.0
	 *
	 * @param API_Request|object $request class instance which implements API_Request
	 * @return API_Response|object class instance which implements API_Response
	 * @throws API_Exception may be thrown in implementations
	 */
	protected function perform_request( $request ) {

		// ensure API is in its default state
		$this->reset_response();

		// save the request object
		$this->request = $request;

		$start_time = microtime( true );

		// if this API requires TLS v1.2, force it
		if ( $this->require_tls_1_2() ) {
			add_action( 'http_api_curl', array( $this, 'set_tls_1_2_request' ), 10, 3 );
		}

		// perform the request
		$response = $this->do_remote_request( $this->get_request_uri(), $this->get_request_args() );

		// calculate request duration
		$this->request_duration = round( microtime( true ) - $start_time, 5 );

		try {

			// parse & validate response
			$response = $this->handle_response( $response );

		} catch ( \Exception $e ) {

			// alert other actors that a request has been made
			$this->broadcast_request();

			throw $e;
		}

		return $response;
	}

	/**
	 * Simple wrapper for wp_remote_request() so child classes can override this
	 * and provide their own transport mechanism if needed, e.g. a custom
	 * cURL implementation
	 *
	 * @since 3.0.0
	 *
	 * @param string $request_uri
	 * @param string $request_args
	 * @return array|\WP_Error
	 */
	protected function do_remote_request( $request_uri, $request_args ) {

		return wp_safe_remote_request( $request_uri, $request_args );
	}

	/**
	 * Handle and parse the response
	 *
	 * @since 3.0.0
	 * @param array|\WP_Error $response response data
	 * @throws \Exception network issues, timeouts, API errors, etc
	 * @return API_Request|object request class instance that implements API_Request
	 */
	protected function handle_response( $response ) {

		// check for WP HTTP API specific errors (network timeout, etc)
		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message(), (int) $response->get_error_code() );
		}

		// set response data
		$this->response_code     = wp_remote_retrieve_response_code( $response );
		$this->response_message  = wp_remote_retrieve_response_message( $response );
		$this->raw_response_body = wp_remote_retrieve_body( $response );

		$response_headers = wp_remote_retrieve_headers( $response );

		// WP 4.6+ returns an object
		if ( is_object( $response_headers ) ) {
			$response_headers = $response_headers->getAll();
		}

		$this->response_headers = $response_headers;

		// parse the response body and tie it to the request
		$this->response = $this->get_parsed_response( $this->raw_response_body );

		// fire do_action() so other actors can act on request/response data,
		// primarily used for logging
		$this->broadcast_request();

		return $this->response;
	}

	/**
	 * Return the parsed response object for the request
	 *
	 * @since 3.0.0
	 * @param string $raw_response_body
	 * @return object|API_Request response class instance which implements API_Request
	 */
	protected function get_parsed_response( $raw_response_body ) {

		$handler_class = $this->get_response_handler();

		return new $handler_class( $raw_response_body );
	}

	/**
	 * Alert other actors that a request has been performed. This is primarily used
	 * for request logging.
	 *
	 * @since 3.0.0
	 */
	protected function broadcast_request() {

		$request_data = array(
			'method'     => $this->get_request_method(),
			'uri'        => $this->get_request_uri(),
			'user-agent' => $this->get_request_user_agent(),
			'headers'    => $this->get_sanitized_request_headers(),
			'body'       => $this->get_sanitized_request_body(),
			'duration'   => $this->get_request_duration() . 's', // seconds
		);

		$response_data = array(
			'code'    => $this->get_response_code(),
			'message' => $this->get_response_message(),
			'headers' => $this->get_response_headers(),
			'body'    => $this->get_sanitized_response_body() ? $this->get_sanitized_response_body() : $this->get_raw_response_body(),
		);

		/**
		 * API Base Request Performed Action.
		 *
		 * Fired when an API request is performed via this base class. Plugins can
		 * hook into this to log request/response data.
		 *
		 * @since 3.0.0
		 * @param array $request_data {
		 *     @type string $method request method, e.g. POST
		 *     @type string $uri request URI
		 *     @type string $user-agent
		 *     @type string $headers request headers
		 *     @type string $body request body
		 *     @type string $duration in seconds
		 * }
		 * @param array $response data {
		 *     @type string $code response HTTP code
		 *     @type string $message response message
		 *     @type string $headers response HTTP headers
		 *     @type string $body response body
		 * }
		 * @param Base $this instance
		 */
		do_action( 'wc_' . $this->get_api_id() . '_api_request_performed', $request_data, $response_data, $this );
	}

	/**
	 * Reset the API response members to their
	 *
	 * @since 3.0.0
	 */
	protected function reset_response() {

		$this->response_code     = null;
		$this->response_message  = null;
		$this->response_headers  = null;
		$this->raw_response_body = null;
		$this->response          = null;
		$this->request_duration  = null;
	}

	/**
	 * Get the request URI
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_request_uri() {

		$uri = $this->request_uri . $this->get_request_path();

		// append any query params to the URL when necessary
		$query = $this->get_request_query();
		if ( $query ) {

			$url_parts = parse_url( $uri );

			// if the URL already has some query params, add to them
			if ( ! empty( $url_parts['query'] ) ) {
				$query = '&' . $query;
			} else {
				$query = '?' . $query;
			}

			$uri = untrailingslashit( $uri ) . $query;
		}

		/**
		 * Request URI Filter.
		 *
		 * Allow actors to filter the request URI. Note that child classes can override
		 * this method, which means this filter may be invoked prior to the overridden
		 * method.
		 *
		 * @since 3.0.0
		 *
		 * @param string $uri current request URI
		 * @param Base class instance
		 */
		return apply_filters( 'wc_' . $this->get_api_id() . '_api_request_uri', $uri, $this );
	}

	/**
	 * Gets the request path.
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_request_path() {

		return ( $this->get_request() ) ? $this->get_request()->get_path() : '';
	}

	/**
	 * Gets the request URL query.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	protected function get_request_query() {

		$query   = '';
		$request = $this->get_request();

		if ( $request && in_array( strtoupper( $this->get_request_method() ), array( 'GET', 'HEAD' ), true ) ) {

			$params = $request->get_params();

			if ( ! empty( $params ) ) {
				$query = http_build_query( $params, '', '&' );
			}
		}

		return $query;
	}

	/**
	 * Get the request arguments in the format required by wp_remote_request()
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	protected function get_request_args() {

		$args = array(
			'method'      => $this->get_request_method(),
			'timeout'     => MINUTE_IN_SECONDS,
			'redirection' => 0,
			'httpversion' => $this->get_request_http_version(),
			'sslverify'   => true,
			'blocking'    => true,
			'user-agent'  => $this->get_request_user_agent(),
			'headers'     => $this->get_request_headers(),
			'body'        => $this->get_request_body(),
			'cookies'     => array(),
		);

		/**
		 * Request arguments.
		 *
		 * Allow other actors to filter the request arguments. Note that
		 * child classes can override this method, which means this filter may
		 * not be invoked, or may be invoked prior to the overridden method
		 *
		 * @since 3.0.0
		 * @param array $args request arguments
		 * @param Base class instance
		 */
		return apply_filters( 'wc_' . $this->get_api_id() . '_http_request_args', $args, $this );
	}

	/**
	 * Get the request method, POST by default
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_request_method() {
		// if the request object specifies the method to use, use that, otherwise use the API default
		return $this->get_request() && $this->get_request()->get_method() ? $this->get_request()->get_method() : $this->request_method;
	}

	/**
	 * Gets the request body.
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_request_body() {

		// GET & HEAD requests don't support a body
		if ( in_array( strtoupper( $this->get_request_method() ), array( 'GET', 'HEAD' ), true ) ) {
			return '';
		}

		return ( $this->get_request() && $this->get_request()->to_string() ) ? $this->get_request()->to_string() : '';
	}

	/**
	 * Gets the sanitized request body, for logging.
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_sanitized_request_body() {

		// GET & HEAD requests don't support a body
		if ( in_array( strtoupper( $this->get_request_method() ), array( 'GET', 'HEAD' ), true ) ) {
			return '';
		}

		return ( $this->get_request() && $this->get_request()->to_string_safe() ) ? $this->get_request()->to_string_safe() : '';
	}

	/**
	 * Get the request HTTP version, 1.1 by default
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_request_http_version() {

		return $this->request_http_version;
	}

	/**
	 * Get the request headers
	 *
	 * @since 3.0.0
	 * @return array
	 */
	protected function get_request_headers() {
		return $this->request_headers;
	}

	/**
	 * Get sanitized request headers suitable for logging, stripped of any
	 * confidential information
	 *
	 * The `Authorization` header is sanitized automatically.
	 *
	 * Child classes that implement any custom authorization headers should
	 * override this method to perform sanitization.
	 *
	 * @since 3.0.0
	 * @return array
	 */
	protected function get_sanitized_request_headers() {

		$headers = $this->get_request_headers();

		if ( ! empty( $headers['Authorization'] ) ) {
			$headers['Authorization'] = str_repeat( '*', strlen( $headers['Authorization'] ) );
		}

		return $headers;
	}

	/**
	 * Get the request user agent, defaults to:
	 *
	 * Dasherized-Plugin-Name/Plugin-Version (WooCommerce/WC-Version; WordPress/WP-Version)
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_request_user_agent() {

		return sprintf( '%s/%s (WooCommerce/%s; WordPress/%s)', str_replace( ' ', '-', $this->get_plugin()->get_plugin_name() ), $this->get_plugin()->get_version(), WC_VERSION, $GLOBALS['wp_version'] );
	}

	/**
	 * Get the request duration in seconds, rounded to the 5th decimal place
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_request_duration() {
		return $this->request_duration;
	}

	/**
	 * Get the response handler class name
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_response_handler() {
		return $this->response_handler;
	}

	/**
	 * Get the response code
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_response_code() {
		return $this->response_code;
	}

	/**
	 * Get the response message
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_response_message() {
		return $this->response_message;
	}

	/**
	 * Get the response headers
	 *
	 * @since 3.0.0
	 * @return array
	 */
	protected function get_response_headers() {
		return $this->response_headers;
	}

	/**
	 * Get the raw response body, prior to any parsing or sanitization
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_raw_response_body() {
		return $this->raw_response_body;
	}

	/**
	 * Get the sanitized response body, provided by the response class
	 * to_string_safe() method
	 *
	 * @since 3.0.0
	 * @return string|null
	 */
	protected function get_sanitized_response_body() {
		return is_callable( array( $this->get_response(), 'to_string_safe' ) ) ? $this->get_response()->to_string_safe() : null;
	}

	/**
	 * Returns the most recent request object.
	 *
	 * @since 3.0.0
	 *
	 * @return API_Request|object the most recent request object
	 */
	public function get_request() {

		return $this->request;
	}

	/**
	 * Returns the most recent response object.
	 *
	 * @since 3.0.0
	 *
	 * @return API_Response|object the most recent response object
	 */
	public function get_response() {

		return $this->response;
	}

	/**
	 * Get the ID for the API, used primarily to namespace the action name
	 * for broadcasting requests
	 *
	 * @since 3.0.0
	 * @return string
	 */
	protected function get_api_id() {

		return 'square';
	}

	/**
	 * Return a new request object
	 *
	 * Child classes must implement this to return an object that implements
	 * API_Request which should be used in the child class API methods
	 * to build the request. The returned API_Request should be passed
	 * to self::perform_request() by your concrete API methods
	 *
	 * @since 3.0.0
	 *
	 * @param array $args optional request arguments
	 * @return API_Request|object
	 */
	abstract protected function get_new_request( $args = array() );

	/**
	 * Return the plugin class instance associated with this API
	 *
	 * Child classes must implement this to return their plugin class instance
	 *
	 * This is used for defining the plugin ID used in filter names, as well
	 * as the plugin name used for the default user agent.
	 *
	 * @since 3.0.0
	 *
	 * @return WooCommerce\Square\Framework\Plugin
	 */
	abstract protected function get_plugin();

	/**
	 * Set the Content-Type request header
	 *
	 * @since x.x.x
	 * @param string $content_type The request content type.
	 */
	protected function set_request_content_type_header( $content_type ) {
		$this->request_headers['content-type'] = $content_type;
	}


	/**
	 * Set the Accept request header
	 *
	 * @since x.x.x
	 * @param string $type The request accept type.
	 */
	protected function set_request_accept_header( $type ) {
		$this->request_headers['accept'] = $type;
	}

	/**
	 * Set the response handler class name. This class will be instantiated
	 * to parse the response for the request.
	 *
	 * @since 3.0.0
	 *
	 * @param string $handler handle class name
	 */
	protected function set_response_handler( $handler ) {
		$this->response_handler = $handler;
	}

	/**
	 * Maybe force TLS v1.2 requests.
	 *
	 * @since 3.0.0
	 *
	 * @param resource $handle the cURL handle returned by curl_init() (passed by reference)
	 * @param array $r the HTTP request arguments
	 * @param $url string the request URL
	 */
	public function set_tls_1_2_request( $handle, $r, $url ) {

		if ( ! Square_Helper::str_starts_with( $url, 'https://' ) ) {
			return;
		}

		curl_setopt( $handle, CURLOPT_SSLVERSION, 6 );
	}

	/**
	 * Determine if TLS v1.2 is required for API requests.
	 *
	 * Subclasses should override this to return true if TLS v1.2 is required.
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function require_tls_1_2() {
		return false;
	}

	/**
	 * Determines if TLS 1.2 is available.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function is_tls_1_2_available() {

		// assume availability to avoid notices for unknown SSL types
		$is_available = true;

		// check the cURL version if installed
		if ( is_callable( 'curl_version' ) ) {

			$versions = curl_version();

			// cURL 7.34.0 is considered the minimum version that supports TLS 1.2
			if ( version_compare( $versions['version'], '7.34.0', '<' ) ) {
				$is_available = false;
			}
		}

		/**
		 * Filters whether TLS 1.2 is available.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $is_available whether TLS 1.2 is available
		 * @param Base $api API class instance
		 */
		return apply_filters( 'wc_square_api_is_tls_1_2_available', $is_available, $this );
	}
}
