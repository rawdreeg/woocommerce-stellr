<?php


if ( ! class_exists( 'WC_Stellar_Integration_Product_Import' ) ) :

	class WC_Stellar_Integration_Product_Import {

		/**
		 * Get product id by sku.
		 *
		 * @param $sku
		 * @return int
		 */
		public static function wc_stellr_get_product_id( $sku ) {

			return wc_get_product_id_by_sku( $sku );
		}

		/**
		 * Create a new product.
		 */
		public static function wc_stellr_upsert_product( $product_data ) {

			$product_id = wc_get_product_id_by_sku( $product_data['ref'] );

			if ( $product_id == 0 ) {
				$wc_product = new WC_Product_Simple();
			} else {
				$wc_product = wc_get_product( $product_id );
			}

			$image_id = self::wc_stellr_fetch_image( $product_data['id'] );

			$pdesc   = $product_data['description'];
			$pterm   = isset( $product_data['terms']['web'] ) ? $product_data['terms']['web'] : '';
			$predems = isset( $product_data['redemption']['web'] ) ? $product_data['redemption']['web'] : '';
			$ppub    = substr( $product_data['modified'], 0, -14 );
			$amounts = $product_data['amount'];
			foreach ( $amounts as $key => $value ) {
				if ( ! empty( $amounts['value'] ) ) {
					$pricing = $amounts['value'];
				}
				if ( ! empty( $amounts['max'] ) ) {
					$pricing = $amounts['max'];
				}
				$currency = $amounts['currency'];
			}

			$wc_product->set_name( $product_data['name'] );
			$wc_product->set_sku( $product_data['ref'] );
			$wc_product->set_description( $pdesc . '<br><br><strong>Redemption instructions :</strong><br>' . $predems . '<br><br><strong>Terms :</strong><br>' . $pterm );
			$wc_product->set_status( 'publish' );
			$wc_product->set_catalog_visibility( 'visible' );
			$wc_product->set_price( $pricing );
			$wc_product->set_regular_price( $pricing );
			$wc_product->set_sold_individually( true );
			$wc_product->set_downloadable( false );
			$wc_product->set_virtual( true );

			if ( $image_id ) {
				$wc_product->set_image_id( $image_id );
			}

			$wc_product->save();

			if ( $wc_product ) {
                // Add category to product.
                $id = $wc_product->get_id();
                wp_set_object_terms($wc_product->get_id(), 'Digital Vouchers', 'product_cat');
				return $wc_product;
			}

			return false;
		}

		/**
		 * Fetch image.
		 */
		public static function wc_stellr_fetch_image( $product_id ) {
			$settings = get_option( 'woocommerce_wc-stellr-integration_settings' );
			$response = WC_Stellr_Integration_API::stellr_api_request(
				$settings['base_url'] . "product/$product_id/image/646x1007.png",
				$settings['api_key'],
				'GET',
				null,
				true
			);

			if ( $response ) {
				$image_data = $response['body'];
				$file_name  = md5( $product_id ) . '.png';
				$upload     = wp_upload_bits( $file_name, null, $image_data );

				if ( $upload['error'] ) {
					return false;
				} else {
					if ( $attachment_id = self::wp_get_attachment_by_post_name( $product_id ) ) {
						$updated = update_attached_file( $attachment_id, $upload['file'] );
						if ( ! $updated ) {
							return false;
						}
						return $attachment_id;
					} else {
						$wp_filetype   = wp_check_filetype( $upload['file'], null );
						$attachment    = array(
							'post_mime_type' => $wp_filetype['type'],
							'post_title'     => $product_id,
							'post_content'   => '',
							'post_status'    => 'inherit',
						);
						$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );
					}

					if ( ! is_wp_error( $attachment_id ) ) {
						require_once ABSPATH . 'wp-admin/includes/image.php';
						$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
					}
					wp_update_attachment_metadata( $attachment_id, $attachment_data );
					return $attachment_id;
				}
			}
			return false;
		}

		/**
		 * Get attachment by name.
		 */
		public static function wp_get_attachment_by_post_name( $post_name ) {
			$args = array(
				'posts_per_page' => 1,
				'post_type'      => 'attachment',
				'name'           => trim( $post_name ),
			);

			$get_attachment = new WP_Query( $args );

			if ( ! $get_attachment || ! isset( $get_attachment->posts, $get_attachment->posts[0] ) ) {
				return false;
			}

			return $get_attachment->posts[0]->ID;
		}

	}
endif;
