<?php defined( 'ABSPATH' ) or exit; ?>

<strong>
	<?php _e( 'Activation Error:', 'give-data-generator' ); ?>
</strong>
<?php _e( 'You must have', 'give-data-generator' ); ?> <a href="https://givewp.com" target="_blank">GiveWP</a>
<?php _e( 'version', 'give-data-generator' ); ?> <?php echo GIVE_DATA_GENERATOR_MIN_GIVE_VERSION; ?>+
<?php printf( esc_html__( 'for the %1$s add-on to activate', 'give-data-generator' ), GIVE_DATA_GENERATOR_NAME ); ?>.
