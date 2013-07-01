<?php
/*
 * cms module:			キャッシュ
 * Module Description:	キャッシュ機能を利用可能にします。
 * Order:				120
 * First Introduced:	1.0.3
 * Major Changes In:	
 * Builtin:				false
 * Free:				true
 * License:				GPLv2 or later
*/
class WP_SiteManager_cache{
	
	private $cache_dir;
	private $advance_cache_tpl;
	private $regex_include_tpl;
	private $headers = array();

function __construct( $parent ) {
	$this->advance_cache_tpl = plugin_dir_path( dirname( __FILE__ ) ) . 'advanced_cache_tpl/advanced-cache.tpl';
	$this->regex_include_tpl = plugin_dir_path( dirname( __FILE__ ) ) . 'advanced_cache_tpl/regex_include.tpl';
	$this->parent = $parent;
	if ( is_admin() ) {
		add_action( 'admin_menu'                                   , array( &$this, 'add_setting_menu' ) );
		add_action( 'load-wp-sitemanager_page_wp-sitemanager-cache', array( &$this, 'update_cache_setting' ) );
//		add_action( 'theme_switcher/device_updated'                , array( &$this, 'clear_all_cache' ) );
		add_action( 'theme_switcher/device_updated'                , array( &$this, 'generate_advanced_cache_file' ) );
//		add_action( 'theme_switcher/device_group_updated'          , array( &$this, 'clear_all_cache' ) );
		add_action( 'theme_switcher/device_group_updated'          , array( &$this, 'generate_advanced_cache_file' ) );
		add_action( 'transition_post_status'                       , array( &$this, 'post_publish_clear_cache' ), 10, 3 );
//		add_action( 'delete_term'                                  , array( &$this, 'clear_all_cache' ) );
//		add_action( 'edited_term'                                  , array( &$this, 'clear_all_cache' ) );
//		add_action( 'deleted_user'                                 , array( &$this, 'clear_all_cache' ) );
//		add_action( 'profile_update'                               , array( &$this, 'clear_all_cache' ) );
	} else {
		add_action( 'init'                                         , array( &$this, 'buffer_start' ) );
		add_action( 'init'                                         , array( &$this, 'check_installed' ) );
//		add_action( 'template_redirect'                            , array( &$this, 'check_vars' ) );
	}
//	add_action( 'transition_comment_status'                        , array( &$this, 'transition_comment_status' ), 10, 3 );
//	add_action( 'comment_post'                                     , array( &$this, 'new_comment' ), 10, 2 );
}

function check_installed() {
	if ( ! get_option( 'site_manager_cache_installed' ) ) {
		$this->create_cache_table();
		$this->generate_advanced_cache_file();
	}
}
function create_cache_table() {
	global $wpdb;
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	$sql = "
CREATE TABLE `{$wpdb->prefix}site_cache` (
 `hash` varchar(32) NOT NULL,
 `content` longtext NOT NULL,
 `device_url` text NOT NULL,
 `type` varchar(10) NOT NULL,
 `post_type` varchar(200) NOT NULL,
 `headers` text NOT NULL,
 `create_time` datetime NOT NULL,
 `expire_time` datetime NOT NULL,
 PRIMARY KEY  (`hash`),
 KEY `expire_time` (`expire_time`),
 KEY `type` (`type`,`post_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";
	dbDelta( $sql, true );
	
	$sql = "SHOW TABLES FROM `{$wpdb->dbname}` LIKE '{$wpdb->prefix}site_cache'";
	$table_exists = $wpdb->get_var( $sql );
	if ( $table_exists ) {
		update_option( 'site_manager_cache_installed', 1 );
	}
}

function add_setting_menu() {
	add_submenu_page( $this->parent->root, 'キャッシュ', 'キャッシュ', 'administrator', basename( $this->parent->root ) . '-cache', array( &$this, 'cache_setting_page' ) );
}

function cache_setting_page() {
	$life_time = get_option( 'site_cache_life', array( 'home' => 60, 'archive' => 60, 'singular' => 360, 'exclude' => '', 'update' => 'none' ) );
	$clear_link = add_query_arg( array( 'del_cache' => '1' ) );
?>
<div class="wrap">
<h2>キャッシュ設定</h2>
<h3>キャッシュの有効期限</h3>
<form method="post" action="">
	<?php wp_nonce_field( 'site-cache-settings' ); ?>
	<table class="form-table">
		<tr>
			<th>トップページ</th>
			<td>
				<input type="text" size="2" name="site_cache_life[home]" value="<?php echo esc_attr( $life_time['home'] ); ?>" /> 分
			</td>
		</tr>
		<tr>
			<th>アーカイブ（一覧）</th>
			<td>
				<input type="text" size="2" name="site_cache_life[archive]" value="<?php echo esc_attr( $life_time['archive'] ); ?>" /> 分
			</td>
		</tr>
		<tr>
			<th>記事詳細</th>
			<td>
				<input type="text" size="2" name="site_cache_life[singular]" value="<?php echo esc_attr( $life_time['singular'] ); ?>" /> 分
			</td>
		</tr>
		<tr>
			<th>キャッシュ除外URL</th>
			<td>
				<textarea cols="70" rows="5" name="site_cache_life[exclude]"><?php echo esc_html( $life_time['exclude'] ); ?></textarea>
				<br />キャッシュを除外したい、URLパターン（正規表現利用可）を指定できます。複数のパターンを指定する場合は、改行を入れてください。
			</td>
		</tr>
	</table>
<h3>記事公開時に削除するキャッシュの範囲</h3>
	<select name="site_cache_life[update]">
		<option value="none"<?php echo $life_time['update'] == 'none' ? ' selected="selected"' : ''; ?>>削除しない</option>
		<option value="single"<?php echo $life_time['update'] == 'single' ? ' selected="selected"' : ''; ?>>記事のみ</option>
		<option value="with-front"<?php echo $life_time['update'] == 'with-front' ? ' selected="selected"' : ''; ?>>記事とトップページ</option>
		<option value="all"<?php echo $life_time['update'] == 'all' ? ' selected="selected"' : ''; ?>>すべて</option>
	</select>
		<?php submit_button( NULL, 'primary', 'site-cache-settings' ); ?>
</form>
<h3>キャッシュのクリア</h3>
	<a href="<?php echo $clear_link; ?>" class="button">キャッシュを全てクリア</a>
</div>
<?php
}

function update_cache_setting() {
	if ( isset( $_GET['del_cache'] ) && $_GET['del_cache'] == '1' ) {
		$this->clear_all_cache();
		$redirect = remove_query_arg( 'del_cache' );
		wp_redirect( $redirect );
		exit;
	}
	if ( isset( $_POST['site-cache-settings'] ) && isset( $_POST['site_cache_life'] ) && is_array( $_POST['site_cache_life'] ) ) {
		check_admin_referer( 'site-cache-settings' );
		$settings = array();
		foreach ( $_POST['site_cache_life'] as $key => $minutes ) {
			if ( function_exists( 'mb_convert_kana' ) && ! in_array( $key, array( 'exclude', 'update' ) ) ) {
				$minutes = mb_convert_kana( $minutes, 'n', 'UTF-8' );
				$minutes = preg_replace( '/[\D]/', '', $minutes );
				$minutes = absint( $minutes );
			}
			$settings[$key] = $minutes;
		}
		$return = update_option( 'site_cache_life', $settings );
		if ( $return ) {
			$this->clear_all_cache();
		}
	}
}

function check_vars() {
	var_dump( $_SERVER );
}

function buffer_start() {
	ob_start( 'write_cache_file' );
}

function write_cache_file() {
	$buffer = ob_get_contents();
}


function clear_all_cache() {
	global $wpdb;
	$sql = "TRUNCATE TABLE `{$wpdb->prefix}site_cache`";
	$wpdb->query( $sql );
}


function clear_front_cache() {
	global $wpdb;
	$sql = "
DELETE
FROM	`{$wpdb->prefix}site_cache`
WHERE	`type` = 'front'
";
	$wpdb->query( $sql );
}


function clear_single_cache( $post ) {
	global $wpdb;
	$permalink = get_permalink( $post->ID );
	$permalink = parse_url( $permalink );
	$device_url = '|' . $permalink['path'];
	if ( isset( $permalink['query'] ) && $permalink['query'] ) {
		$device_url .= '?' . $permalink['query'];
	}

	$sql = "
DELETE
FROM	`{$wpdb->prefix}site_cache`
WHERE	`type` = 'single'
AND		`device_url` LIKE '%$device_url%'
";
	$wpdb->query( $sql );
}


function post_publish_clear_cache( $new_status, $old_status, $post ) {
	if ( $new_status == 'publish' ) {
		$life_time = get_option( 'site_cache_life', array( 'update' => 'none' ) );
		switch ( $life_time['update'] ) {
			case 'with-front' :
				$this->clear_front_cache();
			case 'single' :
				$this->clear_single_cache( $post );
				break;
			case 'all' :
				$this->clear_all_cache();
				break;
			case 'none' :
			default :
		}
	}
}


function transition_comment_status( $new_status, $old_status, $comment ) {
	if ( $new_status == 'approved' || $old_status == 'approved' ) {
		$this->clear_all_cache();
	}
}


function new_comment( $comment_ID, $approved ) {
	if ( $approved === 1 ) {
		$this->clear_all_cache();
	}
}


function generate_advanced_cache_file() {
	global $wpdb;

	$advanced_cache_file = WP_CONTENT_DIR . '/advanced-cache.php';

	if ( file_exists( $advanced_cache_file ) && is_writable( $advanced_cache_file ) || is_writable( WP_CONTENT_DIR ) ) {

		if ( file_exists( $this->advance_cache_tpl ) && is_readable( $this->advance_cache_tpl ) ) {
			$advanced_cache_data = file_get_contents( $this->advance_cache_tpl );
			
			$device_regexes = '';
			$regexes = get_option( 'sitemanager_device_rules', array() );
			foreach ( $regexes as $group => $arr ) {
				$regex = '/' . implode( '|', $arr['regex'] ) . '/';
				$device_regexes .= "\t\t'" . $group . "' => '" . $regex . "',\n";
			}

			if ( is_multisite() ) {
				$sql = "
SELECT	`blog_id`, `domain`, `path`
FROM	`{$wpdb->blogs}`
WHERE	`public` = 1
AND		`spam` = 0
AND		`deleted` = 0
ORDER BY `blog_id` ASC";
				$blogs = $wpdb->get_results( $sql );
				$sites_array = '';

				if ( is_subdomain_install() ) {
					$site_mode = "'domain'";
					$property = 'domain';
				} else {
					$site_mode = "'directory'";
					$property = 'path';
				}
				if ( $blogs ) {
					foreach ( $blogs as $blog ) {
						$sites_array .= "\t\t'" . $blog->$property . "' => '" . $blog->blog_id . "',\n";
					}
				}
				if ( file_exists( $this->regex_include_tpl ) && is_readable( $this->regex_include_tpl ) ) {
					$regex_include_file = WP_CONTENT_DIR . '/regex-include-' . get_current_blog_id() . '.php';
					$regex_include_data = file_get_contents( $this->regex_include_tpl );
					$replaces = array(
						'### DEVICE REGEX ###' => $device_regexes,
					);
					$regex_include_data = str_replace( array_keys( $replaces ), $replaces, $regex_include_data );
					@file_put_contents( $regex_include_file, $regex_include_data );
					$regex_include = "
		\$regex_include_file = dirname( __FILE__ ) . '/regex-include-' . \$site_id . '.php';
		if ( file_exists( \$regex_include_file ) ) {
			include( \$regex_include_file );
		} else {
			return;
		}
";
		
				}
				$device_regexes = '';
			} else {
				$site_mode = 'false';
				$sites_array = '';
				$regex_include = '';
			}

			$replaces = array(
				'### DEVICE REGEX ###' => $device_regexes,
				'### SITES ARRAY ###' => $sites_array,
				'### SITE MODE ###' => $site_mode,
				'### REGEX INCLUDE ###' => $regex_include
			);
			$advanced_cache_data = str_replace( array_keys( $replaces ), $replaces, $advanced_cache_data );

			@file_put_contents( $advanced_cache_file, $advanced_cache_data );
		}
	}
}

} // class end
$this->instance->$instanse = new WP_SiteManager_cache( $this );

function write_cache_file( $buffer ) {
	global $WP_SiteManager, $wpdb;

	if ( $_SERVER['REQUEST_METHOD'] == 'GET' && ! is_404() && ! is_search() && ! is_user_logged_in() && ! is_admin() && preg_match( '#/index\.php$#', $_SERVER['SCRIPT_NAME'] )  && ! isset( $GLOBALS['http_response_code'] ) ) {
		$life_time = get_option( 'site_cache_life', array( 'home' => 60, 'archive' => 60, 'singular' => 360 ) );
		
		if ( $life_time['exclude'] ) {
			$rules = explode( "\n", $life_time['exclude'] );
			$regex = array();
			foreach ( $rules as $rule ) {
				$regex[] = str_replace( '/', '\/', trim( $rule ) );
			}
			$regex = '/' . implode( '|', $regex ) . '/';
			if ( preg_match( $regex, $_SERVER['REQUEST_URI'] ) ) {
				$buffer .= '<!-- cache excluded -->';
				return $buffer;
			}
		}

		$cache = $buffer . "\n" . '<!-- page cached by WP SiteManager -->';
		
		$ua = $_SERVER['HTTP_USER_AGENT'];
		$regexes = get_option( 'sitemanager_device_rules', array() );

		$group = '';
		foreach ( $regexes as $current_group => $arr ) {
			$regex = '/' . implode( '|', $arr['regex'] ) . '/';
			if ( preg_match( $regex, $ua ) ) {
				$group = $current_group;
				break;
			}
		}
		if ( $_COOKIE['site-view'] == 'PC' ) { $group = ''; }

		$protocol = isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
		$device_url = array(
			$group,
			$protocol,
			$_SERVER['SERVER_NAME'],
			$_SERVER['REQUEST_URI']
		);
		$device_url = implode( '|', $device_url );
		$hash = md5( $device_url );
		$sql = "
SELECT	*
FROM	{$wpdb->prefix}site_cache
WHERE	`hash` = '$hash'
";
		$row = false;
		$rows = $wpdb->get_results( $sql );
		if ( $rows ) {
			foreach ( $rows as $r ) {
				if ( $r->device_url == $device_url ) {
					$row = $r;
					break;
				}
			}
		}
		
		$header_arr = array();
		$headers = headers_list();
		foreach ( $headers as $header ) {
			list( $key, $val ) = explode( ': ', $header, 2 );
			if ( $key == 'Vary' && strpos( $val, 'Cookie' ) === false ) {
				$val .= ',Cookie';
			}
			$header_arr[$key] = $val;
		}
		$header_arr['X-Static-Cached-By'] = 'WP SiteManager';
		
		if ( is_front_page() ) {
			$type = 'front';
			$post_type = 'page';
			$life_time_key = 'home';
		} elseif ( is_singular() ) {
			$type = 'single';
			if ( is_single() ) {
				$post_type = 'post';
			} elseif ( is_page() ) {
				$post_type = 'page';
			} else {
				$post_type = get_query_var( 'post_type' );
			}
			$life_time_key = 'singular';
		} elseif ( is_category() ) {
			$type = 'taxonomy';
			$post_type = 'category'. '|' . get_query_var( 'category_name' );
			$life_time_key = 'archive';
		} elseif ( is_tag() ) {
			$type = 'taxonomy';
			$post_type = 'post_tag'. '|' . get_query_var( 'tag_name' );
			$life_time_key = 'archive';
		} elseif ( is_tax() ) {
			$type = 'taxonomy';
			$post_type = get_query_var( 'taxonomy' ) . '|' . get_query_var( 'term' );
			$life_time_key = 'archive';
		} elseif ( is_date() ) {
			$type = 'date';
			if ( get_query_var( 'post_type' ) ) {
				$post_type = get_query_var( 'post_type' );
			} else {
				$post_type = 'post';
			}
			$life_time_key = 'archive';
		} elseif ( is_post_type_archive() ) {
			$type = 'post_type_archive';
			$post_type = get_query_var( 'post_type' );
			$life_time_key = 'archive';
		} elseif ( is_author() ) {
			$type = 'author';
			if ( get_query_var( 'post_type' ) ) {
				$post_type = get_query_var( 'post_type' );
			} else {
				$post_type = 'post';
			}
			$life_time_key = 'archive';
		} elseif ( is_home() ) {
			$type = 'home';
			$post_type = 'post';
			$life_time_key = 'home';
		} elseif ( is_single() ) {
			$post_type = 'post';
			$type = 'single';
			$life_time_key = 'singular';
		}
		
		$data = array(
			'hash'        => $hash,
			'content'     => $cache,
			'device_url'  => $device_url,
			'type'        => $type,
			'post_type'   => $post_type,
			'headers'     => serialize( $header_arr ),
			'create_time' => date( 'Y-m-d H:i:s' ),
			'expire_time' => date( 'Y-m-d H:i:s', time() + $life_time[$life_time_key] * 60 ),
		);

		if ( ! $row ) {
			$wpdb->insert( $wpdb->prefix . 'site_cache', $data );
		} elseif ( $row->expire_time < date( 'Y-m-d H:i:s' ) ) {
			$wpdb->update( $wpdb->prefix . 'site_cache', $data, array( 'hash' => $hash ) );
		} elseif ( strpos( $row->content, '<!-- page cached by WP SiteManager -->' ) === false ) {
			$wpdb->update( $wpdb->prefix . 'site_cache', $data, array( 'hash' => $hash ) );
		}
	}
	return $buffer;
}