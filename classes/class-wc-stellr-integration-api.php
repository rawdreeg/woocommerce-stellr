<?php

if ( ! class_exists( 'WC_Stellr_Integration_API' ) ) :
	class WC_Stellr_Integration_API {


		public static function stellr_api_request( $url, $api_key, $method, $data = null, $is_file = false ) {
			$args     = array(
				'timeout'     => 15,
				'method'  => $method,
				'sslverify' => false,
				'headers' => array(
					'X-API-Key'    => $api_key,
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
			);

            if ( $data ) {
                $args['body'] = json_encode( $data );
            }

			$response = wp_remote_request( $url, $args );
			if ( is_wp_error( $response ) ) {
				return false;
			}

			$response_body = json_decode( $response['body'], true );
			if ( isset( $response_body['error'] ) ) {
				return false;
			}

			if ($is_file) {
				return $response;
			}

			return $response_body;
		}
	}

endif;
