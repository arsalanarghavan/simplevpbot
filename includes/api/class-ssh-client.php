<?php
/**
 * SSH client wrapper (phpseclib3 preferred, ssh2 extension fallback).
 *
 * Usage:
 *     $ssh = new SimpleVPBot_SSH_Client( SimpleVPBot_Model_L2TP_Server::decrypted( $row ) );
 *     if ( $ssh->connect() ) { $r = $ssh->exec( 'uname -a' ); }
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_SSH_Client
 */
class SimpleVPBot_SSH_Client {

	const TIMEOUT = 15;

	/**
	 * Decrypted server row.
	 *
	 * @var object
	 */
	private $cfg;

	/**
	 * phpseclib SSH2 instance.
	 *
	 * @var mixed|null
	 */
	private $conn = null;

	/**
	 * Backend driver.
	 *
	 * @var string phpseclib|ssh2ext|null
	 */
	private $driver = '';

	/**
	 * Last error message.
	 *
	 * @var string
	 */
	public $last_error = '';

	/**
	 * Constructor.
	 *
	 * @param object $decrypted_server Decrypted row from SimpleVPBot_Model_L2TP_Server::decrypted.
	 */
	public function __construct( $decrypted_server ) {
		$this->cfg = $decrypted_server;
	}

	/**
	 * Available driver name or empty string if none.
	 *
	 * @return string
	 */
	public static function available_driver() {
		if ( class_exists( '\\phpseclib3\\Net\\SSH2' ) ) {
			return 'phpseclib';
		}
		if ( function_exists( 'ssh2_connect' ) ) {
			return 'ssh2ext';
		}
		return '';
	}

	/**
	 * Connect.
	 *
	 * @return bool
	 */
	public function connect() {
		$host = (string) ( $this->cfg->ssh_host ?? '' );
		$port = (int) ( $this->cfg->ssh_port ?? 22 );
		$user = (string) ( $this->cfg->ssh_user ?? '' );
		if ( '' === $host || '' === $user ) {
			$this->last_error = 'ssh host/user missing';
			return false;
		}
		$this->driver = self::available_driver();
		if ( 'phpseclib' === $this->driver ) {
			return $this->connect_phpseclib( $host, $port, $user );
		}
		if ( 'ssh2ext' === $this->driver ) {
			return $this->connect_ssh2ext( $host, $port, $user );
		}
		$this->last_error = 'No SSH driver available (install phpseclib3 or enable ssh2 extension).';
		return false;
	}

	/**
	 * Execute a command and return { ok, stdout, stderr, exit }.
	 *
	 * @param string $cmd Command (already escaped).
	 * @return array{ok:bool,stdout:string,stderr:string,exit:int}
	 */
	public function exec( $cmd ) {
		if ( ! $this->conn && ! $this->connect() ) {
			return array( 'ok' => false, 'stdout' => '', 'stderr' => $this->last_error, 'exit' => -1 );
		}
		if ( 'phpseclib' === $this->driver ) {
			/** @var \phpseclib3\Net\SSH2 $c */
			$c        = $this->conn;
			$c->setTimeout( self::TIMEOUT );
			$stdout   = (string) $c->exec( (string) $cmd );
			$stderr   = method_exists( $c, 'getStdError' ) ? (string) $c->getStdError() : '';
			$exit     = method_exists( $c, 'getExitStatus' ) ? (int) $c->getExitStatus() : 0;
			return array(
				'ok'     => 0 === $exit,
				'stdout' => $stdout,
				'stderr' => $stderr,
				'exit'   => $exit,
			);
		}
		$stream = @ssh2_exec( $this->conn, (string) $cmd );
		if ( ! $stream ) {
			return array( 'ok' => false, 'stdout' => '', 'stderr' => 'exec failed', 'exit' => -1 );
		}
		$errStream = @ssh2_fetch_stream( $stream, SSH2_STREAM_STDERR );
		@stream_set_blocking( $stream, true );
		@stream_set_blocking( $errStream, true );
		$stdout = (string) stream_get_contents( $stream );
		$stderr = $errStream ? (string) stream_get_contents( $errStream ) : '';
		@fclose( $stream );
		if ( $errStream ) {
			@fclose( $errStream );
		}
		return array( 'ok' => true, 'stdout' => $stdout, 'stderr' => $stderr, 'exit' => 0 );
	}

	/**
	 * Close connection.
	 */
	public function disconnect() {
		if ( 'phpseclib' === $this->driver && $this->conn && method_exists( $this->conn, 'disconnect' ) ) {
			$this->conn->disconnect();
		}
		$this->conn = null;
	}

	/**
	 * @param string $host Host.
	 * @param int    $port Port.
	 * @param string $user User.
	 * @return bool
	 */
	private function connect_phpseclib( $host, $port, $user ) {
		try {
			$ssh = new \phpseclib3\Net\SSH2( $host, $port, self::TIMEOUT );
			$ssh->setTimeout( self::TIMEOUT );
			$auth = (string) ( $this->cfg->ssh_auth ?? 'key' );
			$ok   = false;
			if ( 'password' === $auth ) {
				$ok = $ssh->login( $user, (string) ( $this->cfg->ssh_password ?? '' ) );
			} else {
				$private_raw = (string) ( $this->cfg->ssh_private_key ?? '' );
				$pass        = (string) ( $this->cfg->ssh_key_passphrase ?? '' );
				if ( '' === $private_raw ) {
					$this->last_error = 'private key is empty';
					return false;
				}
				$key = \phpseclib3\Crypt\PublicKeyLoader::load( $private_raw, '' !== $pass ? $pass : false );
				$ok  = $ssh->login( $user, $key );
			}
			if ( ! $ok ) {
				$this->last_error = 'ssh auth failed';
				return false;
			}
			$this->conn = $ssh;
			return true;
		} catch ( \Throwable $e ) {
			$this->last_error = 'ssh error: ' . $e->getMessage();
			return false;
		}
	}

	/**
	 * @param string $host Host.
	 * @param int    $port Port.
	 * @param string $user User.
	 * @return bool
	 */
	private function connect_ssh2ext( $host, $port, $user ) {
		$c = @ssh2_connect( $host, $port );
		if ( ! $c ) {
			$this->last_error = 'ssh2_connect failed';
			return false;
		}
		$auth = (string) ( $this->cfg->ssh_auth ?? 'key' );
		if ( 'password' === $auth ) {
			if ( ! @ssh2_auth_password( $c, $user, (string) ( $this->cfg->ssh_password ?? '' ) ) ) {
				$this->last_error = 'ssh2_auth_password failed';
				return false;
			}
		} else {
			$priv = (string) ( $this->cfg->ssh_private_key ?? '' );
			if ( '' === $priv ) {
				$this->last_error = 'private key is empty';
				return false;
			}
			$tmp  = function_exists( 'wp_tempnam' ) ? wp_tempnam( 'svp_ssh' ) : tempnam( sys_get_temp_dir(), 'svp_ssh' );
			if ( ! $tmp || false === file_put_contents( $tmp, $priv ) ) {
				$this->last_error = 'cannot write tmp key';
				return false;
			}
			@chmod( $tmp, 0600 );
			$pass = (string) ( $this->cfg->ssh_key_passphrase ?? '' );
			$ok   = @ssh2_auth_pubkey_file( $c, $user, $tmp . '.pub', $tmp, $pass );
			@unlink( $tmp );
			if ( ! $ok ) {
				$this->last_error = 'ssh2_auth_pubkey_file failed (pair may need .pub; use phpseclib3 instead)';
				return false;
			}
		}
		$this->conn = $c;
		return true;
	}
}
