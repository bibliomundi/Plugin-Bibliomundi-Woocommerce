<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Catalog_BiblioMundi' ) ) :

class WC_Catalog_BiblioMundi {

	public function __construct() {
		add_action( 'wp_ajax_bibliomundi_import_catalog', array( $this, 'ajax_import' ) );
		add_action( 'wp_ajax_bibliomundi_import_status', array( $this, 'ajax_status' ) );
	}

	public function ajax_status() {
		wp_send_json(json_decode(file_get_contents(dirname(__FILE__) . '/../log/import.lock')));
	}

	public function ajax_import() {
		$return = array( 
			'error' => true, 
			'msg'   => __( 'Invalid operation', 'woocommerce-bibliomundi' ),
		);

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			$return['msg'] = __( "You don't have permission to manage woocommerce.", 'woocommerce-bibliomundi' );
		} else {
			$security = isset( $_POST['security'] ) && $_POST['security'] ? $_POST['security'] : NULL;
			$scope    = isset( $_POST['scope'] ) && $_POST['scope'] ? $_POST['scope'] : NULL;

			if ( $security && wp_verify_nonce( $security, 'bbm_nonce' ) ) {
				if ( 'complete' == $scope ) {
					$action = __( 'import', 'woocommerce-bibliomundi' );
				} else {
					$action = __( 'update', 'woocommerce-bibliomundi' );
				}
				$response = self::import( $scope );
				if ( ! is_wp_error( $response ) || ! $response ) {
					$return = array(
						'error' => false,
						'msg'   => sprintf( esc_html__( 'Success to %s catalog.', 'woocommerce-bibliomundi' ), $action ),
					);
				} else{
					if ( is_wp_error( $response ) ) {
						$msg = $response->get_error_message();
					} else {
						esc_html__( 'Error to %s catalog.', 'woocommerce-bibliomundi' );
					}
					$return['msg'] = sprintf( $msg, $action );
				}
			}			
		}

		wp_send_json( $return );	
	}

	public static function write_lock($lockfile, $content) {
		$lock = fopen($lockfile, 'a');
		ftruncate($lock, 0);
		fwrite($lock, json_encode($content).PHP_EOL);
		fclose($lock);
	}

	public static function import( $scope ) {
		$result = array('status' => 'progress');					
		$lockfile = dirname(__FILE__) . '/../log/import.lock';
		self::write_lock($lockfile, $result);

		$catalog = bbm_api()->get_catalog( $scope );
		if ( ! is_wp_error( $catalog ) && $catalog instanceof SimpleXMLElement ) {			

			$result['total'] = count($catalog);
			$result['current'] = 0;

			$post = new WC_Post_BiblioMundi();
			foreach ( $catalog as $product ) {
				$post->set_element( $product )->insert();
				$result['current']++;
				self::write_lock($lockfile, $result);
			}

			$result['status'] = 'complete';
			self::write_lock($lockfile, $result);

			return true;
		}
		$result['status'] = 'complete';
		self::write_lock($lockfile, $result);

		return $catalog;
	}

}

return new WC_Catalog_BiblioMundi;

endif;