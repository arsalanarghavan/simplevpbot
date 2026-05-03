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
	 * @return array{built:bool, sent:int, failed:int, zip?:string}
	 */
	public static function run() {
		$out = array(
			'built'  => false,
			'sent'   => 0,
			'failed' => 0,
		);
		if ( ! SimpleVPBot_Settings::get( 'enabled', true ) ) {
			return $out;
		}
		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			SimpleVPBot_Logger::info( 'backup: skipped (lock)' );
			return $out;
		}
		set_transient( self::LOCK_TRANSIENT, 1, 20 * MINUTE_IN_SECONDS );

		try {
			return self::run_locked( $out );
		} finally {
			delete_transient( self::LOCK_TRANSIENT );
		}
	}

	/**
	 * Inner run while lock held.
	 *
	 * @param array{built:bool, sent:int, failed:int, zip?:string} $out Seed.
	 * @return array{built:bool, sent:int, failed:int, zip?:string}
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

		$panel_tmp_paths = array();
		$panel_entries   = array();
		$labels_ok       = array();

		$panels = class_exists( 'SimpleVPBot_Model_Panel' ) ? SimpleVPBot_Model_Panel::all_active_ordered() : array();
		if ( ! empty( $panels ) ) {
			foreach ( $panels as $row ) {
				if ( ! is_object( $row ) ) {
					continue;
				}
				$pid   = (int) ( $row->id ?? 0 );
				if ( $pid < 1 ) {
					continue;
				}
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

				$res = SimpleVPBot_Xui_Client::run_with_panel(
					$pid,
					function () use ( $tmp_path ) {
						if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
							return array( 'ok' => false, 'step' => 'login' );
						}
						$db = SimpleVPBot_Xui_Client::get_db_binary();
						if ( false === $db || '' === $db ) {
							return array( 'ok' => false, 'step' => 'download' );
						}
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
						if ( false === file_put_contents( $tmp_path, $db ) ) {
							return array( 'ok' => false, 'step' => 'write' );
						}
						return array( 'ok' => true );
					}
				);
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
				$tmp_path = $dir . 'panel-legacy-' . $stamp . '.db';
				$res      = SimpleVPBot_Xui_Client::run_with_panel(
					0,
					function () use ( $tmp_path ) {
						if ( ! SimpleVPBot_Xui_Client::login_with_retries( 6, 300000 ) ) {
							return array( 'ok' => false, 'step' => 'login' );
						}
						$db = SimpleVPBot_Xui_Client::get_db_binary();
						if ( false === $db || '' === $db ) {
							return array( 'ok' => false, 'step' => 'download' );
						}
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
						if ( false === file_put_contents( $tmp_path, $db ) ) {
							return array( 'ok' => false, 'step' => 'write' );
						}
						return array( 'ok' => true );
					}
				);
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
					SimpleVPBot_Logger::error( 'backup: legacy panel db failed', array( 'step' => $step ) );
					if ( is_readable( $tmp_path ) ) {
						@unlink( $tmp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					}
				}
			}
		}

		$zip_result = SimpleVPBot_Backup_Export::build_zip( $stamp, $panel_entries );
		foreach ( $panel_tmp_paths as $p ) {
			if ( is_string( $p ) && is_readable( $p ) ) {
				@unlink( $p ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
		if ( is_wp_error( $zip_result ) ) {
			SimpleVPBot_Logger::error( 'backup: zip build failed', array( 'err' => $zip_result->get_error_message() ) );
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
				return $out;
			}
		}

		$out['built'] = true;
		$out['zip']   = basename( $filepath );
		update_option( 'simplevpbot_last_backup_built_at', $now );

		if ( ! empty( $s['backup_store_on_site'] ) ) {
			$copied = SimpleVPBot_Backup_Export::copy_zip_to_site_storage( $filepath, $stamp );
			if ( ! $copied ) {
				SimpleVPBot_Logger::error( 'backup: site storage copy failed', array( 'path' => $filepath ) );
			}
			$keep = max( 1, (int) ( $s['backup_site_retention_count'] ?? 14 ) );
			SimpleVPBot_Backup_Export::prune_site_backups( $keep );
		}

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
		if ( $send_tg_chan && $tg_tok && 0 === $tg_chan ) {
			SimpleVPBot_Logger::warning( 'backup: telegram channel send enabled but backup_telegram_chat_id is 0' );
		}
		if ( $send_bl_chan && $bl_tok && 0 === $bl_chan ) {
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

		$caption = self::build_caption( $jalali_human, $labels_ok );
		$sent    = 0;
		$fail    = 0;

		if ( $send_tg_adm && $tg_tok ) {
			$tg = new SimpleVPBot_Telegram_Client( $tg_tok );
			foreach ( $tg_ids as $cid ) {
				$r = $tg->send_document_file(
					array(
						'chat_id'  => (int) $cid,
						'caption'  => $caption,
						'document' => $filepath,
					)
				);
				if ( ! empty( $r['ok'] ) ) {
					$sent++;
				} else {
					$fail++;
					SimpleVPBot_Logger::error( 'backup: telegram send failed', array( 'chat' => (int) $cid, 'res' => $r ) );
				}
				usleep( 350000 );
			}
		}
		if ( $send_bl_adm && $bl_tok ) {
			$bl = new SimpleVPBot_Bale_Client( $bl_tok );
			foreach ( $bl_ids as $cid ) {
				$r = $bl->send_document_file(
					array(
						'chat_id'  => (int) $cid,
						'caption'  => $caption,
						'document' => $filepath,
					)
				);
				if ( ! empty( $r['ok'] ) ) {
					$sent++;
				} else {
					$fail++;
					SimpleVPBot_Logger::error( 'backup: bale send failed', array( 'chat' => (int) $cid, 'res' => $r ) );
				}
				usleep( 350000 );
			}
		}

		if ( $send_tg_chan && $tg_tok && 0 !== $tg_chan ) {
			$tg = new SimpleVPBot_Telegram_Client( $tg_tok );
			$r  = $tg->send_document_file(
				array(
					'chat_id'  => $tg_chan,
					'caption'  => $caption,
					'document' => $filepath,
				)
			);
			if ( ! empty( $r['ok'] ) ) {
				$sent++;
			} else {
				$fail++;
				SimpleVPBot_Logger::error( 'backup: telegram channel send failed', array( 'chat' => $tg_chan, 'res' => $r ) );
			}
			usleep( 350000 );
		}
		if ( $send_bl_chan && $bl_tok && 0 !== $bl_chan ) {
			$bl = new SimpleVPBot_Bale_Client( $bl_tok );
			$r  = $bl->send_document_file(
				array(
					'chat_id'  => $bl_chan,
					'caption'  => $caption,
					'document' => $filepath,
				)
			);
			if ( ! empty( $r['ok'] ) ) {
				$sent++;
			} else {
				$fail++;
				SimpleVPBot_Logger::error( 'backup: bale channel send failed', array( 'chat' => $bl_chan, 'res' => $r ) );
			}
			usleep( 350000 );
		}

		$out['sent']   = $sent;
		$out['failed'] = $fail;
		SimpleVPBot_Logger::info( 'backup completed', array( 'file' => basename( $filepath ), 'sent' => $sent, 'failed' => $fail ) );
		if ( $sent > 0 ) {
			update_option( 'simplevpbot_last_backup_at', $now );
		}
		@unlink( $filepath ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $out;
	}

	/**
	 * Telegram/Bale caption with human time and panel labels.
	 *
	 * @param string             $jalali_human Human-readable timestamp.
	 * @param array<int, string> $labels_ok    Panel labels included in zip.
	 * @return string
	 */
	private static function build_caption( $jalali_human, array $labels_ok ) {
		$base = '📦 SimpleVPBot ' . (string) $jalali_human;
		if ( empty( $labels_ok ) ) {
			$tail = "\n📂 " . __( 'Panels: (no panel DB in zip — plugin tables only)', 'simplevpbot' );
		} else {
			$show = array_slice( $labels_ok, 0, 8 );
			$tail = "\n📂 " . __( 'Panels:', 'simplevpbot' ) . ' ' . implode( ', ', $show );
			$rest = count( $labels_ok ) - count( $show );
			if ( $rest > 0 ) {
				$tail .= ' +' . $rest;
			}
		}
		$out = $base . $tail;
		if ( strlen( $out ) > 1000 ) {
			$out = function_exists( 'mb_substr' ) ? mb_substr( $out, 0, 997 ) . '…' : substr( $out, 0, 997 ) . '…';
		}
		return $out;
	}
}
