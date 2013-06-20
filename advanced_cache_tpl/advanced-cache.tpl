<?php
if ( is_admin() ) { return; }

class SiteManagerAdvancedCache {
	private $device_regexes = array(
### DEVICE REGEX ###
	);
	private $sites = array(
### SITES ARRAY ###
	);

	private $site_mode = ### SITE MODE ###;

	function __construct() {
		global $table_prefix;

		if ( $_SERVER['REQUEST_METHOD'] != 'GET' ) { return; }
		foreach ( array_keys( $_COOKIE ) as $key ) {
			if ( strpos( $key, 'wordpress_logged_in_' ) === 0 || strpos( $key, 'comment_author_' ) === 0 ) {
				return;
			}
		}

		if ( $_COOKIE['site-view'] == 'PC' || ! $group = $this->get_device_group() ) {
			$group = '';
		}

		switch ( $this->site_mode ) {
		case 'domain' :
			$add_prefix = isset( $this->sites[$_SERVER['SERVER_NAME']] ) && $this->sites[$_SERVER['SERVER_NAME']] != 1 ? $this->sites[$_SERVER['SERVER_NAME']] . '_' : '';
			$site_id = isset( $this->sites[$_SERVER['SERVER_NAME']] ) ? $this->sites[$_SERVER['SERVER_NAME']] : '';
			$table = $table_prefix . $add_prefix;
			break;
			break;
		case 'directory' :
			$key = array_pop( explode( '/', trim( $_SERVER['REQUEST_URI'], '/' ), 2 ) );
			$add_prefix = isset( $this->sites[$key] ) && $this->sites[$key] != 1 ? $this->sites[$key] . '_' : '';
			$site_id = isset( $this->sites[$key] ) ? $this->sites[$key] : '';
			$table = $table_prefix . $add_prefix;
			break;
		default :
			$table = $table_prefix;
			$site_id = '';
		}
### REGEX INCLUDE ###
		$protocol = isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
		$device_url = array(
			$group,
			$protocol,
			$_SERVER['SERVER_NAME'],
			$_SERVER['REQUEST_URI']
		);
		$device_url = implode( '|', $device_url );
		$hash = md5( $device_url );

		$dbh = mysql_connect( 
			DB_HOST,
			DB_USER,
			DB_PASSWORD,
			true
		);

		if ( $dbh ) {
			if ( function_exists( 'mysql_set_charset' ) ) {
				mysql_set_charset( DB_CHARSET, $dbh );
			} else {
				$sql = 'set names ' . DB_CHARSET;
				mysql_query( $sql, $dbh );
			}
			mysql_select_db( DB_NAME, $dbh );
			$now = date( 'Y-m-d H:i:s' );
			$sql = "
SELECT	*
FROM	{$table}site_cache
WHERE	`hash` = '$hash'
AND		`expire_time` >= '$now'
";
			$ret = mysql_query( $sql );

			if ( $ret ) {
				while ( $row = mysql_fetch_object( $ret ) ) {
					if ( $row->device_url == $device_url && strpos( $row->content, '<!-- page cached by WP SiteManager -->' ) !== false ) {
						$headers = unserialize( $row->headers );
						if ( $headers ) {
							foreach ( $headers as $key => $header ) {
								header( $key . ': ' . $header );
							}
						}
						echo $row->content;
						exit;
					}
				}
			}
			mysql_close( $dbh );
		}
	}
	
	
	function get_device_group() {
		foreach ( $this->device_regexes as $group => $regex ) {
			if ( preg_match( $regex, $_SERVER['HTTP_USER_AGENT'] ) ) {
				return $group;
			}
		}
		return false;
	}
}
new SiteManagerAdvancedCache();