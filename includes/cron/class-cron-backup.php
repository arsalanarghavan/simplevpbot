<?php
/**
 * Backup: panel DB file(s) (optional) + WordPress plugin tables in one zip; deliver per settings flags.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Cron_Backup
 */
class SimpleVPBot_Cron_Backup {

	const LOCK_TRANSIENT = 'simplevpbot_backup_running';

	/**
	 * Run job.
	 *
	 * @param array<string, mixed> $args force?:bool, ignore_enabled?:bool.
	 * @return array<string, mixed>
	 */
	public static function run( array $args = array() ) {
		$force          = ! empty( $args['force'] );
		$ignore_enabled = ! empty( $args['ignore_enabled'] );
		$out            = array(
			'built'           => false,
			'sent'            => 0,
			'failed'          => 0,
			'skipped_reason'  => '',
			'delivery'        => array(),
			'stored_on_site'  => false,
			'panel_db_critical' => false,
		);
		if ( ! $ignore_enabled && ! SimpleVPBot_Settings::get( 'enabled', true ) ) {
			SimpleVPBot_Logger::info( 'backup: skipped (plugin disabled)' );
			$out['skipped_reason'] = 'enabled';
			self::persist_last_run( $out );
			return $out;
		}
		if ( ! $force && get_transient( self::LOCK_TRANSIENT ) ) {
			SimpleVPBot_Logger::info( 'backup: skipped (lock)' );
			$out['skipped_reason'] = 'lock';
			self::persist_last_run( $out );
			return $out;
		}
		if ( ! $force ) {
			set_transient( self::LOCK_TRANSIENT, 1, 20 * MINUTE_IN_SECONDS );
		}

		try {
			$out = self::run_locked( $out );
			self::persist_last_run( $out );
			return $out;
		} finally {
			if ( ! $force ) {
				delete_transient( self::LOCK_TRANSIENT );
			}
		}
	}

	/**
	 * Store last backup run summary for dashboard diagnostics.
	 *
	 * @param array<string, mixed> $out Run output.
	 */
	private static function persist_last_run( array $out ) {
		update_option(
			'simplevpbot_last_backup_run',
			array(
				'at'              => time(),
				'built'           => ! empty( $out['built'] ),
				'sent'            => (int) ( $out['sent'] ?? 0 ),
				'failed'          => (int) ( $out['failed'] ?? 0 ),
				'skipped_reason'  => (string) ( $out['skipped_reason'] ?? '' ),
				'delivery'        => isset( $out['delivery'] ) && is_array( $out['delivery'] ) ? $out['delivery'] : array(),
				'stored_on_site'  => ! empty( $out['stored_on_site'] ),
				'storage_fallback' => ! empty( $out['storage_fallback'] ),
			),
			false
		);
	}

	/**
	 * Whether a backup channel chat id is configured (0 = empty; negative TG supergroup ids are valid).
	 *
	 * @param int|string $id Stored chat id.
	 * @return bool
	 */
	private static function backup_channel_chat_id_is_set( $id ) {
		return 0 !== (int) $id;
	}

	/**
	 * Validate backup delivery settings before run (when any send flag is on).
	 *
	 * @return array{ok:bool, message?:string}
	 */
	public static function validate_delivery_config() {
		$s = SimpleVPBot_Settings::all();
		$issues = array();

		$send_tg_adm  = ! empty( $s['backup_send_telegram_admins'] );
		$send_bl_adm  = ! empty( $s['backup_send_bale_admins'] );
		$send_tg_chan = ! empty( $s['backup_send_telegram_channel'] );
		$send_bl_chan = ! empty( $s['backup_send_bale_channel'] );

		if ( ! $send_tg_adm && ! $send_bl_adm && ! $send_tg_chan && ! $send_bl_chan ) {
			return array( 'ok' => true );
		}

		$tg_tok = trim( (string) ( $s['telegram_token'] ?? '' ) );
		$bl_tok = trim( (string) ( $s['bale_token'] ?? '' ) );
		$tg_ids = array_filter( array_map( 'intval', (array) ( $s['admin_telegram_ids'] ?? array() ) ) );
		$bl_ids = array_filter( array_map( 'intval', (array) ( $s['admin_bale_ids'] ?? array() ) ) );
		$tg_chan = (int) ( $s['backup_telegram_chat_id'] ?? 0 );
		$bl_chan = (int) ( $s['backup_bale_chat_id'] ?? 0 );

		if ( $send_tg_adm ) {
			if ( '' === $tg_tok ) {
				$issues[] = __( 'ارسال به ادمین تلگرام فعال است ولی توکن تلگرام خالی است.', 'simplevpbot' );
			} elseif ( empty( $tg_ids ) ) {
				$issues[] = __( 'ارسال به ادمین تلگرام فعال است ولی شناسه ادمین‌های تلگرام در تنظیمات عمومی خالی است.', 'simplevpbot' );
			}
		}
		if ( $send_tg_chan ) {
			if ( '' === $tg_tok ) {
				$issues[] = __( 'ارسال به کانال تلگرام فعال است ولی توکن تلگرام خالی است.', 'simplevpbot' );
			} elseif ( ! self::backup_channel_chat_id_is_set( $tg_chan ) ) {
				$issues[] = __( 'ارسال به کانال تلگرام فعال است ولی شناسه چت کانال بکاپ تنظیم نشده.', 'simplevpbot' );
			}
		}
		if ( $send_bl_adm ) {
			if ( '' === $bl_tok ) {
				$issues[] = __( 'ارسال به ادمین بله فعال است ولی توکن بله خالی است.', 'simplevpbot' );
			} elseif ( empty( $bl_ids ) ) {
				$issues[] = __( 'ارسال به ادمین بله فعال است ولی شناسه ادمین‌های بله در تنظیمات عمومی خالی است.', 'simplevpbot' );
			}
		}
		if ( $send_bl_chan ) {
			if ( '' === $bl_tok ) {
				$issues[] = __( 'ارسال به کانال بله فعال است ولی توکن بله خالی است.', 'simplevpbot' );
			} elseif ( ! self::backup_channel_chat_id_is_set( $bl_chan ) ) {
				$issues[] = __( 'ارسال به کانال بله فعال است ولی شناسه چت کانال بکاپ تنظیم نشده.', 'simplevpbot' );
			}
		}

		if ( ! empty( $issues ) ) {
			return array(
				'ok'      => false,
				'message' => implode( ' ', $issues ),
			);
		}
		return array( 'ok' => true );
	}

	/**
	 * Send document with retries on transient API failures.
	 *
	 * @param object               $client Telegram or Bale client.
	 * @param array<string, mixed> $params send_document_file params.
	 * @return array<string, mixed>
	 */
	private static function send_document_with_retry( $client, array $params ) {
		$last = array( 'ok' => false );
		for ( $i = 0; $i < 2; $i++ ) {
			if ( $i > 0 ) {
				usleep( 400000 );
			}
			$last = $client->send_document_file( $params );
			if ( ! empty( $last['ok'] ) ) {
				return $last;
			}
			$desc = strtolower( (string) ( $last['description'] ?? '' ) );
			$code = (int) ( $last['error_code'] ?? 0 );
			$retryable = $code >= 500 || 429 === $code || false !== strpos( $desc, 'timeout' ) || false !== strpos( $desc, 'timed out' );
			if ( ! $retryable ) {
				break;
			}
		}
		return $last;
	}

	/**
	 * Inner run while lock held.
	 *
	 * @param array<string, mixed> $out Seed.
	 * @return array<string, mixed>
	 */
	private static function run_locked( array $out ) {
		$s    = SimpleVPBot_Settings::all();
		$dir  = SimpleVPBot_Backup_Export::base_tmp_dir();
		$now  = time();
		$stamp = class_exists( 'SimpleVPBot_Jalali_Date' )
			? SimpleVPBot_Jalali_Date::format_datetime_filename( $now )
			: gmdate( 'Ymd-His', $now );
		$jalali_human = class_exists( 'SimpleVPBot_Jalali_Date' )
			? SimpleVPBot_Jalali_Date::format_datetime_precise( $now )
			: gmdate( 'Y-m-d H:i:s', $now );

		$panel_tmp_paths   = array();
		$panel_entries     = array();
		$panel_failures    = array();
		$labels_ok         = array();
		$panels_expected   = 0;

		$panels = class_exists( 'SimpleVPBot_Model_Panel' ) ? SimpleVPBot_Model_Panel::all_active_ordered() : array();
		if ( ! empty( $panels ) ) {
			foreach ( $panels as $row ) {
				if ( ! is_object( $row ) ) {
					continue;
				}
				$pid = (int) ( $row->id ?? 0 );
				if ( $pid < 1 ) {
					continue;
				}
				$panels_expected++;
			}
			$panel_index = 0;
			foreach ( $panels as $row ) {
				if ( ! is_object( $row ) ) {
					continue;
				}
				$pid   = (int) ( $row->id ?? 0 );
				if ( $pid < 1 ) {
					continue;
				}
				if ( $panel_index > 0 && class_exists( 'SimpleVPBot_Xui_Client' ) ) {
					SimpleVPBot_Xui_Client::clear_session();
				}
				++$panel_index;
				$label = trim( (string) ( $row->label ?? '' ) );
				if ( '' === $label ) {
					$label = 'panel-' . $pid;
				}
				$safe = sanitize_file_name( $label );
				if ( '' === $safe ) {
					$safe = 'p' . $pid;
				}
				$zip_name = 'panel/panel-' . $pid . '-' . $safe . '.db';
				$tmp_path = $dir . 'panel-' . $pid . '-' . $stamp . '.db';

				$res = self::download_panel_db_to_path( $pid, $tmp_path );
				if ( is_array( $res ) && ! empty( $res['ok'] ) && is_readable( $tmp_path ) ) {
					$panel_tmp_paths[] = $tmp_path;
					$panel_entries[]   = array(
						'path'     => $tmp_path,
						'zip_name' => $zip_name,
					);
					$labels_ok[] = $label;
					SimpleVPBot_Logger::info( 'backup: panel db ok', array( 'panel_id' => $pid, 'label' => $label ) );
				} else {
					$step = is_array( $res ) && isset( $res['step'] ) ? (string) $res['step'] : 'unknown';
					$panel_failures[] = array(
						'panel_id'  => $pid,
						'label'     => $label,
						'step'      => $step,
						'getdb_url' => is_array( $res ) ? (string) ( $res['getdb_url'] ?? '' ) : '',
					);
					SimpleVPBot_Logger::error(
						'backup: panel db failed',
						array( 'panel_id' => $pid, 'label' => $label, 'step' => $step )
					);
					if ( is_readable( $tmp_path ) ) {
						@unlink( $tmp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					}
				}
			}
		} else {
			$legacy_url = trim( (string) ( $s['panel_url'] ?? '' ) );
			if ( '' !== $legacy_url ) {
				$panels_expected = 1;
				$tmp_path        = $dir . 'panel-legacy-' . $stamp . '.db';
				$res             = self::download_panel_db_to_path( 0, $tmp_path );
				if ( is_array( $res ) && ! empty( $res['ok'] ) && is_readable( $tmp_path ) ) {
					$panel_tmp_paths[] = $tmp_path;
					$panel_entries[]   = array(
						'path'     => $tmp_path,
						'zip_name' => 'panel/panel-legacy.db',
					);
					$labels_ok[] = 'legacy';
					SimpleVPBot_Logger::info( 'backup: legacy panel db ok' );
				} else {
					$step = is_array( $res ) && isset( $res['step'] ) ? (string) $res['step'] : 'unknown';
					$panel_failures[] = array(
						'panel_id'  => 0,
						'label'     => 'legacy',
						'step'      => $step,
						'getdb_url' => is_array( $res ) ? (string) ( $res['getdb_url'] ?? '' ) : '',
					);
					SimpleVPBot_Logger::error( 'backup: legacy panel db failed', array( 'step' => $step ) );
					if ( is_readable( $tmp_path ) ) {
						@unlink( $tmp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					}
				}
			}
		}

		$zip_result = SimpleVPBot_Backup_Export::build_zip( $stamp, $panel_entries, $panel_failures, $panels_expected );
		foreach ( $panel_tmp_paths as $p ) {
			if ( is_string( $p ) && is_readable( $p ) ) {
				@unlink( $p ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
		if ( is_wp_error( $zip_result ) ) {
			SimpleVPBot_Logger::error( 'backup: zip build failed', array( 'err' => $zip_result->get_error_message() ) );
			$out['skipped_reason'] = 'zip';
			return $out;
		}
		$filepath = $zip_result;

		$max_mb = max( 0, (int) ( $s['backup_max_zip_mb'] ?? 0 ) );
		if ( $max_mb > 0 ) {
			$fs = @filesize( $filepath ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false !== $fs && $fs > $max_mb * 1024 * 1024 ) {
				SimpleVPBot_Logger::error(
					'backup: zip exceeds backup_max_zip_mb',
					array( 'bytes' => (int) $fs, 'max_mb' => $max_mb )
				);
				@unlink( $filepath ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$out['skipped_reason'] = 'max_size';
				return $out;
			}
		}

		$out['built']              = true;
		$out['zip']                = basename( $filepath );
		$out['panel_db_ok']        = count( $panel_entries );
		$out['panel_db_failed']    = count( $panel_failures );
		$out['panels_expected']    = $panels_expected;
		$out['panel_db_failures']  = $panel_failures;
		if ( $panels_expected > 0 && count( $panel_entries ) < 1 ) {
			$out['panel_db_critical'] = true;
		}
		update_option( 'simplevpbot_last_backup_built_at', $now );

		$stored_on_site = false;
		if ( ! empty( $s['backup_store_on_site'] ) ) {
			$copied = SimpleVPBot_Backup_Export::copy_zip_to_site_storage( $filepath, $stamp );
			if ( $copied ) {
				$stored_on_site = true;
			} else {
				SimpleVPBot_Logger::error( 'backup: site storage copy failed', array( 'path' => $filepath ) );
			}
			$keep = max( 1, (int) ( $s['backup_site_retention_count'] ?? 14 ) );
			SimpleVPBot_Backup_Export::prune_site_backups( $keep );
		}
		$out['stored_on_site'] = $stored_on_site;

		$tg_ids = array_values(
			array_filter(
				array_map( 'intval', (array) ( $s['admin_telegram_ids'] ?? array() ) ),
				static function ( $id ) {
					return 0 !== (int) $id;
				}
			)
		);
		$bl_ids = array_values(
			array_filter(
				array_map( 'intval', (array) ( $s['admin_bale_ids'] ?? array() ) ),
				static function ( $id ) {
					return 0 !== (int) $id;
				}
			)
		);
		$tg_tok = (string) $s['telegram_token'];
		$bl_tok = (string) $s['bale_token'];

		$send_tg_adm  = ! empty( $s['backup_send_telegram_admins'] );
		$send_bl_adm  = ! empty( $s['backup_send_bale_admins'] );
		$send_tg_chan = ! empty( $s['backup_send_telegram_channel'] );
		$send_bl_chan = ! empty( $s['backup_send_bale_channel'] );

		$tg_chan = (int) ( $s['backup_telegram_chat_id'] ?? 0 );
		$bl_chan = (int) ( $s['backup_bale_chat_id'] ?? 0 );

		if ( $send_tg_adm && $tg_tok && empty( $tg_ids ) ) {
			SimpleVPBot_Logger::warning( 'backup: telegram admin send enabled but admin_telegram_ids is empty' );
		}
		if ( $send_bl_adm && $bl_tok && empty( $bl_ids ) ) {
			SimpleVPBot_Logger::warning( 'backup: bale admin send enabled but admin_bale_ids is empty' );
		}
		if ( $send_tg_chan && $tg_tok && ! self::backup_channel_chat_id_is_set( $tg_chan ) ) {
			SimpleVPBot_Logger::warning( 'backup: telegram channel send enabled but backup_telegram_chat_id is 0' );
		}
		if ( $send_bl_chan && $bl_tok && ! self::backup_channel_chat_id_is_set( $bl_chan ) ) {
			SimpleVPBot_Logger::warning( 'backup: bale channel send enabled but backup_bale_chat_id is 0' );
		}
		if ( $send_tg_adm && ! $tg_tok ) {
			SimpleVPBot_Logger::warning( 'backup: telegram admin send enabled but telegram_token is empty' );
		}
		if ( $send_bl_adm && ! $bl_tok ) {
			SimpleVPBot_Logger::warning( 'backup: bale admin send enabled but bale_token is empty' );
		}
		if ( $send_tg_chan && ! $tg_tok ) {
			SimpleVPBot_Logger::warning( 'backup: telegram channel send enabled but telegram_token is empty' );
		}
		if ( $send_bl_chan && ! $bl_tok ) {
			SimpleVPBot_Logger::warning( 'backup: bale channel send enabled but bale_token is empty' );
		}

		$caption  = self::build_caption( $jalali_human, $labels_ok, $panel_failures );
		$sent     = 0;
		$fail     = 0;
		$delivery = array(
			'telegram_admins'  => array( 'enabled' => $send_tg_adm, 'ok' => 0, 'fail' => 0, 'skipped' => 0 ),
			'telegram_channel' => array( 'enabled' => $send_tg_chan, 'ok' => 0, 'fail' => 0, 'skipped' => 0 ),
			'bale_admins'      => array( 'enabled' => $send_bl_adm, 'ok' => 0, 'fail' => 0, 'skipped' => 0 ),
			'bale_channel'     => array( 'enabled' => $send_bl_chan, 'ok' => 0, 'fail' => 0, 'skipped' => 0 ),
		);

		if ( $send_tg_adm && $tg_tok ) {
			$tg = new SimpleVPBot_Telegram_Client( $tg_tok );
			if ( empty( $tg_ids ) ) {
				$delivery['telegram_admins']['skipped'] = 1;
			}
			foreach ( $tg_ids as $cid ) {
				$r = self::send_document_with_retry(
					$tg,
					array(
						'chat_id'  => (int) $cid,
						'caption'  => $caption,
						'document' => $filepath,
					)
				);
				if ( ! empty( $r['ok'] ) ) {
					$sent++;
					$delivery['telegram_admins']['ok']++;
				} else {
					$fail++;
					$delivery['telegram_admins']['fail']++;
					SimpleVPBot_Logger::error( 'backup: telegram send failed', array( 'chat' => (int) $cid, 'res' => $r ) );
				}
				usleep( 350000 );
			}
		}
		if ( $send_bl_adm && $bl_tok ) {
			$bl = new SimpleVPBot_Bale_Client( $bl_tok );
			if ( empty( $bl_ids ) ) {
				$delivery['bale_admins']['skipped'] = 1;
			}
			foreach ( $bl_ids as $cid ) {
				$r = self::send_document_with_retry(
					$bl,
					array(
						'chat_id'  => (int) $cid,
						'caption'  => $caption,
						'document' => $filepath,
					)
				);
				if ( ! empty( $r['ok'] ) ) {
					$sent++;
					$delivery['bale_admins']['ok']++;
				} else {
					$fail++;
					$delivery['bale_admins']['fail']++;
					SimpleVPBot_Logger::error( 'backup: bale send failed', array( 'chat' => (int) $cid, 'res' => $r ) );
				}
				usleep( 350000 );
			}
		}

		if ( $send_tg_chan && $tg_tok ) {
			if ( ! self::backup_channel_chat_id_is_set( $tg_chan ) ) {
				$delivery['telegram_channel']['skipped'] = 1;
			} else {
				$tg = new SimpleVPBot_Telegram_Client( $tg_tok );
				$r  = self::send_document_with_retry(
					$tg,
					array(
						'chat_id'  => $tg_chan,
						'caption'  => $caption,
						'document' => $filepath,
					)
				);
				if ( ! empty( $r['ok'] ) ) {
					$sent++;
					$delivery['telegram_channel']['ok'] = 1;
				} else {
					$fail++;
					$delivery['telegram_channel']['fail'] = 1;
					SimpleVPBot_Logger::error( 'backup: telegram channel send failed', array( 'chat' => $tg_chan, 'res' => $r ) );
				}
				usleep( 350000 );
			}
		}
		if ( $send_bl_chan && $bl_tok ) {
			if ( ! self::backup_channel_chat_id_is_set( $bl_chan ) ) {
				$delivery['bale_channel']['skipped'] = 1;
			} else {
				$bl = new SimpleVPBot_Bale_Client( $bl_tok );
				$r  = self::send_document_with_retry(
					$bl,
					array(
						'chat_id'  => $bl_chan,
						'caption'  => $caption,
						'document' => $filepath,
					)
				);
				if ( ! empty( $r['ok'] ) ) {
					$sent++;
					$delivery['bale_channel']['ok'] = 1;
				} else {
					$fail++;
					$delivery['bale_channel']['fail'] = 1;
					SimpleVPBot_Logger::error( 'backup: bale channel send failed', array( 'chat' => $bl_chan, 'res' => $r ) );
				}
				usleep( 350000 );
			}
		}

		$any_send_enabled = $send_tg_adm || $send_bl_adm || $send_tg_chan || $send_bl_chan;
		if ( $any_send_enabled && $sent < 1 && ! $stored_on_site ) {
			$copied = SimpleVPBot_Backup_Export::copy_zip_to_site_storage( $filepath, $stamp );
			if ( $copied ) {
				$stored_on_site           = true;
				$out['stored_on_site']    = true;
				$out['storage_fallback']  = true;
				SimpleVPBot_Logger::warning( 'backup: no delivery succeeded; kept copy on site as fallback' );
			}
		}

		$out['sent']      = $sent;
		$out['failed']    = $fail;
		$out['delivery']  = $delivery;
		SimpleVPBot_Logger::info( 'backup completed', array( 'file' => basename( $filepath ), 'sent' => $sent, 'failed' => $fail ) );
		if ( $sent > 0 || $stored_on_site ) {
			update_option( 'simplevpbot_last_backup_at', $now );
		}
		if ( $stored_on_site || $sent > 0 ) {
			@unlink( $filepath ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} else {
			SimpleVPBot_Logger::warning( 'backup: temp zip kept (no delivery, no site storage)', array( 'path' => $filepath ) );
		}
		return $out;
	}

	/**
	 * Download panel SQLite to a temp path (login + getDb retries).
	 *
	 * @param int    $panel_id Panel id (0 = legacy settings).
	 * @param string $tmp_path Destination path.
	 * @return array{ok:bool, step?:string}
	 */
	private static function download_panel_db_to_path( $panel_id, $tmp_path ) {
		$last = SimpleVPBot_Xui_Client::run_with_panel(
			(int) $panel_id,
			function () use ( $tmp_path, $panel_id ) {
				$getdb_url = SimpleVPBot_Xui_Client::diag_url( 'server/getDb', 'api' );
				$fail      = static function ( $step ) use ( $getdb_url, $panel_id ) {
					return array(
						'ok'        => false,
						'step'      => (string) $step,
						'getdb_url' => $getdb_url,
						'panel_id'  => (int) $panel_id,
					);
				};

				if ( SimpleVPBot_Xui_Client::has_cookie_credentials() ) {
					if ( ! SimpleVPBot_Xui_Client::login_with_cookie_session( 3, 350000 ) ) {
						return $fail( 'login' );
					}
				} elseif ( ! SimpleVPBot_Xui_Client::has_api_token() ) {
					return $fail( 'missing_cookie_creds' );
				}

				$db = SimpleVPBot_Xui_Client::get_db_binary_with_retries( 3, true );
				if ( false === $db || '' === $db ) {
					$step = SimpleVPBot_Xui_Client::last_get_db_step();
					if ( '' === $step ) {
						$step = 'download';
					}
					if ( SimpleVPBot_Xui_Client::has_cookie_credentials() ) {
						SimpleVPBot_Xui_Client::clear_session();
						if ( SimpleVPBot_Xui_Client::login_with_cookie_session( 3, 350000 ) ) {
							$db = SimpleVPBot_Xui_Client::get_db_binary_with_retries( 2, true );
						}
					}
					if ( false === $db || '' === $db ) {
						$step = SimpleVPBot_Xui_Client::last_get_db_step();
						if ( '' === $step ) {
							$step = 'download';
						}
						SimpleVPBot_Logger::error(
							'backup: getDb failed',
							array(
								'panel_id'  => (int) $panel_id,
								'step'      => $step,
								'getdb_url' => $getdb_url,
								'auth_flow' => (string) ( SimpleVPBot_Xui_Client::get_last_auth_diag()['auth_flow'] ?? '' ),
							)
						);
						return $fail( $step );
					}
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
				if ( false === file_put_contents( $tmp_path, $db ) ) {
					return $fail( 'write' );
				}
				return array( 'ok' => true );
			}
		);
		return is_array( $last ) ? $last : array( 'ok' => false, 'step' => 'unknown' );
	}

	/**
	 * Telegram/Bale caption with human time and panel labels.
	 *
	 * @param string                          $jalali_human Human-readable timestamp.
	 * @param array<int, string>              $labels_ok    Panel labels included in zip.
	 * @param array<int, array<string,mixed>> $failures     Panels that failed getDb.
	 * @return string
	 */
	private static function build_caption( $jalali_human, array $labels_ok, array $failures = array() ) {
		$base = '📦 SimpleVPBot ' . (string) $jalali_human;
		if ( empty( $labels_ok ) && empty( $failures ) ) {
			$tail = "\n📂 " . __( 'Panels: (no panel DB in zip — plugin tables only)', 'simplevpbot' );
		} elseif ( ! empty( $labels_ok ) && empty( $failures ) ) {
			$show = array_slice( $labels_ok, 0, 8 );
			$tail = "\n📂 " . __( 'Panels:', 'simplevpbot' ) . ' ' . implode( ', ', $show );
			$rest = count( $labels_ok ) - count( $show );
			if ( $rest > 0 ) {
				$tail .= ' +' . $rest;
			}
		} elseif ( ! empty( $labels_ok ) ) {
			$show = array_slice( $labels_ok, 0, 6 );
			$tail = "\n📂 " . __( 'Panels OK:', 'simplevpbot' ) . ' ' . implode( ', ', $show );
			$fail_labels = array();
			foreach ( $failures as $f ) {
				if ( ! is_array( $f ) ) {
					continue;
				}
				$lbl = trim( (string) ( $f['label'] ?? '' ) );
				if ( '' === $lbl ) {
					$lbl = 'panel-' . (int) ( $f['panel_id'] ?? 0 );
				}
				$step = trim( (string) ( $f['step'] ?? '' ) );
				$fail_labels[] = '' !== $step ? $lbl . '(' . $step . ')' : $lbl;
			}
			if ( ! empty( $fail_labels ) ) {
				$tail .= "\n⚠️ " . __( 'Panel DB missing:', 'simplevpbot' ) . ' ' . implode( ', ', array_slice( $fail_labels, 0, 6 ) );
			}
		} else {
			$fail_labels = array();
			foreach ( $failures as $f ) {
				if ( ! is_array( $f ) ) {
					continue;
				}
				$lbl = trim( (string) ( $f['label'] ?? '' ) );
				if ( '' === $lbl ) {
					$lbl = 'panel-' . (int) ( $f['panel_id'] ?? 0 );
				}
				$step = trim( (string) ( $f['step'] ?? '' ) );
				$fail_labels[] = '' !== $step ? $lbl . '(' . $step . ')' : $lbl;
			}
			$tail = "\n⚠️ " . __( 'Panel DB failed (plugin tables only):', 'simplevpbot' ) . ' ' . implode( ', ', array_slice( $fail_labels, 0, 8 ) );
		}
		$out = $base . $tail;
		if ( strlen( $out ) > 1000 ) {
			$out = function_exists( 'mb_substr' ) ? mb_substr( $out, 0, 997 ) . '…' : substr( $out, 0, 997 ) . '…';
		}
		return $out;
	}
}
