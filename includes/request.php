<?php
include_once( __DIR__ . DIRECTORY_SEPARATOR . 'config.inc.php' );

function call_api( $request, $override_options = [] ) {
	$options = array_merge( [
		'api_scheme' => 'https',
		'api_port' => 443,
		'api_base_path' => 'api/v1',
		'api_ssl_verify' => false,
	],
	$override_options );

	$api_scheme = $options[ 'api_scheme' ];
	$api_hostname = $options[ 'api_hostname' ];
	$api_port = $options[ 'api_port' ];
	$api_base_path = $options[ 'api_base_path' ];
	$api_ssl_verify = $options[ 'api_ssl_verify' ];
	$api_key = $options[ 'api_key' ];


	$headers = [
		'Accept: application/json',
		'X-API-Key: ' . $api_key
	];

	$api_base_url = $api_scheme . '://' . $api_hostname . ':'. $api_port . '/' . $api_base_path;

	$base_path_location = strpos( $request[ 'url' ], $api_base_path );

	$endpoint_url = $api_base_url . $request[ 'url' ];

	// sometimes we might recive a url that already starts with the base path, so we shouldn't repeat the base path
	// the url might or might not start with a leading backslash 
	if ( $base_path_location === 0 || $base_path_location === 1 ) {
		$endpoint_url = $api_base_url . substr( $request[ 'url' ], $base_path_location + strlen( $api_base_path ) );
	}

	$curl_handle = curl_init();

	curl_setopt( $curl_handle, CURLOPT_RETURNTRANSFER, 1 );
	
	if ( $api_scheme === 'https' ) {
		curl_setopt( $curl_handle, CURLOPT_SSL_VERIFYPEER, $api_ssl_verify );
	}

	if ( array_key_exists( 'content', $request ) ) {
		$headers[] = 'Content-Type: application/json';
		curl_setopt( $curl_handle, CURLOPT_POST, 1 );
		curl_setopt( $curl_handle, CURLOPT_POSTFIELDS, $request[ 'content' ] );
	}

    curl_setopt( $curl_handle, CURLOPT_HTTPHEADER, $headers );


	switch ( strtoupper( $request[ 'method' ] ) ) {
		case 'POST':
			curl_setopt( $curl_handle, CURLOPT_POST, 1);
			break;
		case 'GET':
			curl_setopt( $curl_handle, CURLOPT_POST, 0);
			break;
		case 'DELETE':
		case 'PATCH':
		case 'PUT':
			curl_setopt( $curl_handle, CURLOPT_CUSTOMREQUEST, $request[ 'method' ] );
		break;
	}

	curl_setopt( $curl_handle, CURLOPT_URL, $endpoint_url );

    $result = curl_exec( $curl_handle );
    $http_code = curl_getinfo( $curl_handle, CURLINFO_HTTP_CODE );
    $json_result = json_decode( $result, 1 ); // 1 - assoc array
    $curl_error = curl_error( $curl_handle );
	
	curl_close( $curl_handle );

    if ( isset( $json_result[ 'error' ] ) ) {
            throw new Exception( "API Error $http_code: ". $json_result[ 'error' ] . ' ' . $endpoint_url );
	} elseif ( $http_code < 200 || $http_code >= 300 ) {
        if ($http_code == 401) {
            throw new Exception( 'Authentication failed. Have you configured your authmethod correct?' );
        }

        throw new Exception( "Curl Error: $http_code " . $curl_error . ' ' . $api_base_url . $request[ 'url' ] );
    }

	return $json_result;
}


function call_api_audited( $request ) {
	global $apiip, $apiport, $apipass, $apiproto, $apisslverify;

	$config = [
		'api_scheme' => $apiproto,
		'api_hostname' => $apiip,
		'api_port' => $apiport,
		'api_ssl_verify' => $apisslverify,
		'api_key' => $apipass
	];

	$log_entry = [
		'method' => $request[ 'method' ],
		'url' => $request[ 'url' ],
		'content' => array_key_exists( 'content', $request ) ? $request[ 'content' ] : null,
		'issued_by_user' => $_SERVER[ 'PHP_AUTH_USER' ]
    ];

	if ( strtoupper( $request[ 'method' ] ) !== 'GET' ) {
	    $domain_regex = '@zones\/([^/]+)(?=.)\/?@i';
	    preg_match($domain_regex, $log_entry[ 'url' ], $matches );
	    $domain_name = $matches[ 1 ];
		$zone_id = $domain_name . '.';

	    $zone = call_api( [
			'method' => 'get',
			'url' => '/servers/localhost/zones/' . $zone_id
		], $config );

	    $account = $zone[ 'account' ];
	    $account_parts = explode( ':', $account );
	    $provider = $account_parts[ 0 ];
	    $user_id = $account_parts[ 1 ];
	    
	    //TODO: remove it:
	    //@a8c_slack( '#delphin-dev', $domain_name . ' owned by ' . $provider . ' ' . $user_id . ' changed with ' . $account, $botname = 'dotblog-dns' );

	    //audit_user_log( 'dotblog-dnsedit', json_encode( $log_entry ), $user_id );
	}

	return call_api( $request, $config );
}
