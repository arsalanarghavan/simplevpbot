<?php
/**
 * Config display label override from settings / reseller profile.
 *
 * @package SimpleVPBot
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ConfigLabelOverrideTest extends TestCase {

	/**
	 * Resolver and label builder must exist.
	 */
	public function test_helpers_exist(): void {
		$root = dirname( __DIR__ );
		$br   = (string) file_get_contents( $root . '/includes/helpers/class-reseller-branding.php' );
		$sn   = (string) file_get_contents( $root . '/includes/helpers/class-service-naming.php' );
		$this->assertStringContainsString( 'config_label_override_for_user', $br );
		$this->assertStringContainsString( 'config_labels_from_uris', $sn );
		$this->assertStringContainsString( 'config_label_override_for_user', $sn );
	}

	/**
	 * Multi-uri labels use -N suffix when override is active.
	 */
	public function test_config_labels_from_uris_multi_suffix_contract(): void {
		$root = dirname( __DIR__ );
		$sn   = (string) file_get_contents( $root . '/includes/helpers/class-service-naming.php' );
		$this->assertStringContainsString( '$override . \'-\' . $idx', $sn );
	}

	/**
	 * prefix_numbered mode helpers and label format.
	 */
	public function test_prefix_numbered_naming_contract(): void {
		$root     = dirname( __DIR__ );
		$sn       = (string) file_get_contents( $root . '/includes/helpers/class-service-naming.php' );
		$br       = (string) file_get_contents( $root . '/includes/helpers/class-reseller-branding.php' );
		$settings = (string) file_get_contents( $root . '/includes/class-settings.php' );
		$actions  = (string) file_get_contents( $root . '/includes/admin/class-admin-actions.php' );
		$prov     = (string) file_get_contents( $root . '/includes/helpers/class-service-provisioner.php' );

		$this->assertStringContainsString( 'prefix_numbered', $sn );
		$this->assertStringContainsString( 'uses_prefix_numbered_for_new', $sn );
		$this->assertStringContainsString( 'format_prefix_numbered_label', $sn );
		$this->assertStringContainsString( 'format_prefix_numbered_label_from_number', $sn );
		$this->assertStringContainsString( 'next_service_number_for_new', $sn );
		$this->assertStringContainsString( "return \$pref . '-' . \$n", $sn );
		$this->assertStringContainsString( 'unique_panel_client_id', $sn );
		$this->assertStringContainsString( 'is_internal_panel_email', $sn );
		$this->assertStringContainsString( 'display_panel_client_name', $sn );
		$this->assertStringContainsString( 'config_label_prefix_for_user', $br );
		$this->assertStringContainsString( 'config_label_prefix', $settings );
		$this->assertStringContainsString( 'config_label_number_start', $settings );
		$this->assertStringContainsString( "'prefix_numbered'", $actions );
		$this->assertStringContainsString( 'uses_prefix_numbered_for_new', $prov );
	}

	/**
	 * Inbound prefix, numbered mode, and display name helpers.
	 */
	public function test_inbound_display_and_numbered_contract(): void {
		$root = dirname( __DIR__ );
		$this->assertFileExists( $root . '/includes/helpers/class-inbound-display-name.php' );
		$this->assertFileExists( $root . '/includes/helpers/class-config-inbound-match.php' );
		$this->assertFileExists( $root . '/includes/models/class-model-reseller-inbound-display-name.php' );

		$sn = (string) file_get_contents( $root . '/includes/helpers/class-service-naming.php' );
		$this->assertStringContainsString( 'format_with_inbound', $sn );
		$this->assertStringContainsString( 'format_numbered_label', $sn );
		$this->assertStringContainsString( "'numbered'", $sn );
		$this->assertStringContainsString( 'uses_numbered_for_new', $sn );

		$actions = (string) file_get_contents( $root . '/includes/admin/class-admin-actions.php' );
		$this->assertStringContainsString( "case 'service_naming':", $actions );
		$this->assertStringContainsString( "'numbered'", $actions );

		$activator = (string) file_get_contents( $root . '/includes/class-activator.php' );
		$this->assertStringContainsString( '2.3.6', $activator );
		$this->assertStringContainsString( 'svp_reseller_inbound_display_names', $activator );
	}

	/**
	 * Canonical naming: provision helpers, legacy email pattern, empty UI subscription_id.
	 */
	public function test_canonical_service_naming_contract(): void {
		$root = dirname( __DIR__ );
		$sn   = (string) file_get_contents( $root . '/includes/helpers/class-service-naming.php' );
		$prov = (string) file_get_contents( $root . '/includes/helpers/class-service-provisioner.php' );
		$handler = (string) file_get_contents( $root . '/includes/bot/handlers/class-handler-service.php' );
		$activator = (string) file_get_contents( $root . '/includes/class-activator.php' );

		$this->assertStringContainsString( 'provision_canonical_label', $sn );
		$this->assertStringContainsString( 'provision_panel_email', $sn );
		$this->assertStringContainsString( 'uses_prefix_numbered_for_new() || self::uses_numbered_for_new()', $sn );
		$this->assertStringContainsString( 'unique_panel_client_id( $canonical )', $sn );
		$this->assertStringContainsString( '$next  = self::next_service_number_for_new( $uid )', $sn );
		$this->assertStringContainsString( '$next   = self::next_service_number_for_new( $uid, $prefix )', $sn );
		$this->assertStringContainsString( 'next_numbered_candidate', $sn );
		$this->assertStringContainsString( 'Heydas-1001 + 1 => Heydas-1002', $sn );
		$this->assertStringContainsString( '1001 + 2 => 1003', $sn );
		$this->assertStringContainsString( 'return $canonical;', $sn );
		$this->assertStringContainsString( 'canonical_label_for_service', $sn );
		$this->assertStringContainsString( 'public_label_for_service', $sn );
		$this->assertStringContainsString( 'generate_legacy_canonical_email', $sn );
		$this->assertStringContainsString( "'u' . \$user_id . '-' . \$slug . '@svp.local'", $sn );
		$this->assertStringContainsString( "'subscription_id'   => ''", $sn );
		$this->assertStringContainsString( 'provision_canonical_label', $prov );
		$this->assertStringNotContainsString( 'شناسه اتصال', $handler );
		$this->assertStringContainsString( '🆔 شناسه:', $handler );
		$this->assertStringContainsString( "if ( '' === trim( (string) ( \$v['sub_id'] ?? '' ) ) )", $handler );
		$this->assertStringContainsString( 'SimpleVPBot_Config_Link::replace_uri_fragment', $handler );
		$this->assertStringContainsString( 'if ( $lip > 1 && $total > 0 )', $handler );
		$this->assertStringContainsString( 'display_label', $activator );
		$this->assertStringContainsString( '2.3.7', $activator );
		$this->assertStringContainsString( "'display_label' => \$text", $handler );

		$ops = (string) file_get_contents( $root . '/includes/admin/services/class-service-admin-ops.php' );
		$mut = (string) file_get_contents( $root . '/includes/admin/class-dashboard-admin-mutations.php' );
		$this->assertStringContainsString( 'service_apply_canonical_panel_identity', $ops );
		$this->assertStringContainsString( 'client_email_new', $ops );
		$this->assertStringContainsString( 'service_apply_canonical_panel_identity', $mut );
	}

	/**
	 * Optional inbound prefix in config labels (site toggle + alias-only resolver).
	 */
	public function test_config_label_prepend_inbound_contract(): void {
		$root     = dirname( __DIR__ );
		$settings = (string) file_get_contents( $root . '/includes/class-settings.php' );
		$actions  = (string) file_get_contents( $root . '/includes/admin/class-admin-actions.php' );
		$sn       = (string) file_get_contents( $root . '/includes/helpers/class-service-naming.php' );
		$inb      = (string) file_get_contents( $root . '/includes/helpers/class-inbound-display-name.php' );

		$this->assertStringContainsString( "'config_label_prepend_inbound'", $settings );
		$this->assertStringContainsString( 'config_label_prepend_inbound', $settings );
		$this->assertStringContainsString( 'config_label_prepend_inbound', $actions );
		$this->assertStringContainsString( 'config_label_prepend_inbound()', $sn );
		$this->assertStringContainsString( 'for_config_label', $inb );
		$this->assertStringContainsString( 'for_config_label', $sn );
		$this->assertStringNotContainsString(
			'panel_inbound_remark',
			substr(
				$inb,
				(int) strpos( $inb, 'function for_config_label' ),
				(int) strpos( $inb, 'function panel_inbound_remark' ) - (int) strpos( $inb, 'function for_config_label' )
			)
		);
	}
}
