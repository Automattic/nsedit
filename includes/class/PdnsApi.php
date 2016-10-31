<?php

include_once('ApiHandler.php');
include_once( __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'request.php' );

class PdnsAPI {
    public function listzones($q = FALSE) {
        if ($q) {
            $ret = Array();
            $seen = Array();

			$api_result = call_api_audited( [
				'method' => 'GET',
				'url' => "/servers/localhost/search-data?q=*".$q."*&max=25"
			] );

            foreach ($api_result as $result) {
                if (isset($seen[$result['zone_id']])) {
                    continue;
                }
                $zone = $this->loadzone($result['zone_id']);
                unset($zone['rrsets']);
                array_push($ret, $zone);
                $seen[$result['zone_id']] = 1;
            }

            return $ret;
        }
		
		return call_api_audited( [
			'method' => 'GET',
			'url' => '/servers/localhost/zones'
		] );
    }

    public function loadzone($zoneid) {
        return call_api_audited( [
			'method' => 'GET',
			'url' => "/servers/localhost/zones/$zoneid"
		] );
    }

    public function exportzone($zoneid) {
		return call_api_audited( [
			'method' => 'GET',
			'url' => "/servers/localhost/zones/$zoneid/export"
		] );  
    }

    public function savezone($zone) {
        // We have to split up RRSets and Zoneinfo.
        // First, update the zone

        $zonedata = $zone;
        unset( $zonedata['id'] );
        unset( $zonedata['url'] ) ;
        unset( $zonedata['rrsets'] ) ;
		unset( $zonedata[ 'account' ] ); // we don't allow settings an account field because that we use to determine ownership    
		/* we don't create zones here
	    if (!isset($zone['serial']) or gettype($zone['serial']) != 'integer') {
            $api->method = 'POST';
            $api->url = '/servers/localhost/zones';
            $api->content = json_encode($zonedata);
            $api->call();

            return $api->json;
        }*/


		call_api_audited( [
			'method' => 'PUT',
			'url' => $zone[ 'url' ],
			'content' => json_encode( $zonedata )
		] );

        // Then, update the rrsets
        if ( count( $zone['rrsets'] ) > 0 ) {
			call_api_audited( [
				'method' => 'PATCH',
				'url' => $zone[ 'url' ],
				'content' => json_encode( [ 'rrsets' => $zone['rrsets'] ] )
			] );
        }

        return $this->loadzone( $zone['id'] );
    }

    public function deletezone($zoneid) {
	/* we don't support deleting zones
		return call_api_audited( [
			'method' => 'DELETE',
			'url' =>  "/servers/localhost/zones/$zoneid"
		] );
*/
    }

    public function getzonekeys($zoneid) {
        $ret = array();

		$api_result = call_api_audited( [
			'method' => 'GET',
			'url' =>  "/servers/localhost/zones/$zoneid/cryptokeys"
		] );

        foreach ( $api_result as $key) {
            if (!isset($key['active']))
                continue;

            $key['dstxt'] = $zoneid . ' IN DNSKEY '.$key['dnskey']."\n\n";

            if (isset($key['ds'])) {
                foreach ($key['ds'] as $ds) {
                    $key['dstxt'] .= $zoneid . ' IN DS '.$ds."\n";
                }
                unset($key['ds']);
            }
            array_push($ret, $key);
        }

        return $ret;
    }

}

?>
