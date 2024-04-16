<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {    
    // Verify nonce
    if ( ! isset($_POST['vwpfp_fraud_settings_nonce']) || ! wp_verify_nonce( sanitize_text_field( wp_unslash ($_POST['vwpfp_fraud_settings_nonce'] ) ), 'vwpfp_fraud_settings_nonce')) {
        // Nonce verification failed
        wp_die( 'Sorry, nonce verification failed' );
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Define the allowed HTML tags
    $allowed_tags = array(
        'p' => array()
    );  

    // Update the threshold value
    $vwpfp_setting_updated = 0;

    $vwpfp_threshold = isset($_POST['threshold']) ? intval($_POST['threshold']) : 5;
    if ( $vwpfp_threshold >= 1 && $vwpfp_threshold <=10 ) {
        update_option('vwpfp_fraud_prevention_threshold', intval($vwpfp_threshold));
        $vwpfp_setting_updated = 1;
    }
    else {       
        echo wp_kses( __( 'Threshold must be a number between 1 and 10', 'visual-wp-fraud-prevention' ), $allowed_tags ) . "<br>";

        $vwpfp_setting_updated = 0; // Corrected variable name
    }
    
    $vwpfp_max_transaction = isset($_POST['max_transaction']) ? intval($_POST['max_transaction']) : 10;
    if (  $vwpfp_max_transaction >= 1 && $vwpfp_max_transaction <=100 ) {
        update_option('vwpfp_max_transactions', intval($vwpfp_max_transaction));
        $vwpfp_setting_updated = 1; // Updated variable name
    }
    else {
        echo wp_kses( __( 'Max transactions must be a number between 1 and 100', 'visual-wp-fraud-prevention' ), $allowed_tags ) . "<br>";
        $vwpfp_setting_updated = 0; // Updated variable name
    }
}

$vwpfp_nonce = wp_create_nonce( 'vwpfp_fraud_settings_nonce' );

// Fetch the current threshold value
$current_threshold = intval(get_option('vwpfp_fraud_prevention_threshold', 6));
$current_max_transactions = intval(get_option('vwpfp_max_transactions', 10));
?>

<div class="wrap">
    <h1>VisualWP Fraud Prevention Settings</h1>
    <form method="POST" action="">
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Score Threshold</th>
                <td>
                    <input type="number" name="threshold" value="<?php echo intval($current_threshold); ?>" />
                    <p class="description">Set the score threshold to flag an email as fraudulent.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Max Transactions Per Minute</th>
                <td>
                    <input type="number" name="max_transaction" value="<?php echo intval($current_max_transactions); ?>" />
                    <p class="description">Set the max number of attempted transaction per minute by a single user or IP address.</p>
                </td>
            </tr>
        </table>
        <input type="hidden" name="vwpfp_fraud_settings_nonce" value="<?php echo esc_attr( $vwpfp_nonce ); ?>" />

        <?php submit_button(); ?>
    </form>
</div>
