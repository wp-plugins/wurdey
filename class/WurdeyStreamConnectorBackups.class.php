<?php
if (class_exists('WP_Stream_Connector')) {
    class WurdeyStreamConnectorBackups extends WP_Stream_Connector
    {   

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public static $name = 'wurdey_backups';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public static $actions = array(
            'wurdey_backup',
	);

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public static function get_label() {
            return __( 'Wurdey Backups', 'default' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
            return array(
                'wurdey_backup'    => __( 'Backup', 'default' ),
            );
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
            return array(
                'wurdey_backups' => __( 'Wurdey Backups', 'wurdey-child' ),
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
            if (isset($record->object_id)) {
            }
            return $links;
	}

        public static function callback_wurdey_backup($destination, $message, $size, $status, $type) {
            if (WurdeyClientReport::is_version_2()) {
                self::log(
                    $message,
                    compact('destination', 'status', 'type', 'size'),
                    0,
                    'wurdey_backups',
                    'wurdey_backup'
                );                          
            } else {
                self::log(
                    $message,
                    compact('destination', 'status', 'type', 'size'),
                    0,
                    array( 'wurdey_backups' => 'wurdey_backup' )
                );
            }            
        }
    }
}

