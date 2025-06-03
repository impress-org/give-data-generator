<?php defined( 'ABSPATH' ) or exit; ?>

<div class="notice notice-error">
	<p>
		<strong><?php _e( 'Activation Error:', 'give-data-generator' ); ?></strong>
		<?php _e( 'You must have', 'give-data-generator' ); ?> <a href="https://givewp.com" target="_blank">GiveWP</a>
		<?php printf( __( 'plugin installed and activated for the %s add-on to activate', 'give-data-generator' ), GIVE_DATA_GENERATOR_NAME ); ?>
	</p>
</div>
