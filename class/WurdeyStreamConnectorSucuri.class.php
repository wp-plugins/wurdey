<?php
if (class_exists('WP_Stream_Connector')) {
    class WurdeyStreamConnectorSucuri extends WP_Stream_Connector
    {   

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public static $name = 'wurdey_sucuri';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public static $actions = array(
		'wurdey_sucuri_scan',
	);

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public static function get_label() {
		return __( 'Wurdey Sucuri', 'default' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'wurdey_sucuri_scan'    => __( 'Scan', 'default' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'wurdey_sucuri' => __( 'Wurdey Sucuri', 'default' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 * @param  array $links      Previous links registered
	 * @param  int   $record     Stream record
	 * @return array             Action links
	 */
	public static function action_links( $links, $record ) {
		if ( isset($record->object_id )) {
			
		}
		return $links;
	}

        public static function callback_wurdey_sucuri_scan($data, $scan_status) {
            $message = "";            
            if ($scan_status == "success") {
                $message = __("Sucuri scan success", "wurdey-child");
                $scan_status = "success";
            } else {
                $message = __("Sucuri scan failed", "wurdey-child");
                $scan_status = "failed";
            }
            
            $scan_result = unserialize(base64_decode($data));
            $status = $webtrust = "";            
            if (is_array($scan_result)) {
                $status = isset($scan_result['status']) ? $scan_result['status'] : "";
                $webtrust = isset($scan_result['webtrust']) ? $scan_result['webtrust'] : "";
            }
            
            if (WurdeyClientReport::is_version_2()) {
                self::log(
                    $message,
                    compact('scan_status', 'status', 'webtrust'),
                    0,
                    'wurdey_sucuri',
                    'wurdey_sucuri_scan'
                );                            
            } else {
                self::log(
                    $message,
                    compact('scan_status', 'status', 'webtrust'),
                    0,
                    array( 'wurdey_sucuri' => 'wurdey_sucuri_scan' )
                );            
            }
        }
    }
}

