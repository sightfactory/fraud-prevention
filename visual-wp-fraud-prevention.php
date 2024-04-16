<?php
/**
 * Plugin Name: VisualWP Anti-Spam and Fraud Prevention
 * Plugin URI: https://sightfactory.com/product/visualwp-anti-spam-and-fraud-prevention/
 * Description: Prevents fraudulent or spam account creation based on email assessment in WordPress and Woocommerce. Limits transaction volume.
 * Version: 1.3
 * Author: Sightfactory
 * Author URI: https://www.sightfactory.com
 * License: GPL v2 or later
 * Requires at least: 5.9
 * Tested up to:      6.5
 * Requires PHP:      7.4 
 */

//  If accessed directly, abort
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Hook for adding admin menus
add_action('admin_menu', 'vwpfp_prevention_menu');

// Action function for the above hook
function vwpfp_prevention_menu() {
    add_options_page('VisualWP Anti-Spam and Fraud Prevention Settings', 'VisualWP Anti-Spam and Fraud Prevention Settings', 'manage_options', 'vwpfp-wp-fraud-prevention', 'vwpfp_fraud_prevention_settings_page');
}

// Function to display the settings page
function vwpfp_fraud_prevention_settings_page() {
   $settings_file_path = plugin_dir_path(__FILE__) . 'settings.php';
    if (file_exists($settings_file_path)) {
        include($settings_file_path);
    }
}

// Simple function to assess email score
function vwpfp_fraud_prevention_assess_email_score($email) {
    // Initialize the score
    $score = 0;

    // Split the email into local part and domain
    list($localPart, $domain) = explode('@', $email);

    // Check for random character strings in local part (length between 10 and 20 characters)
    if (preg_match('/^[a-zA-Z0-9]{10,20}$/', $localPart)) {
        $score += 3;
    }

    // Check for consistent length of the local part
    if (strlen($localPart) >= 10 && strlen($localPart) <= 20) {
        $score += 2;
    }

    // Check for mix of letters and numbers in the local part
    if (preg_match('/[a-zA-Z].*[0-9]|[0-9].*[a-zA-Z]/', $localPart)) {
        $score += 1;
    }

    // Check for absence of common personal identifiers in the local part
    if (!preg_match('/[a-zA-Z]{3,}/', $localPart)) {
        $score += 2;
    }

    return $score;
}

// Hook to alter the registration process
add_filter('registration_errors', 'vwpfp_fraud_prevention_registration_check', 10, 3);

function vwpfp_fraud_prevention_registration_check($errors, $sanitized_user_login, $user_email) {
    $score = vwpfp_fraud_prevention_assess_email_score($user_email);
    $threshold = get_option('vwpfp_fraud_prevention_threshold', 6); // Default threshold

    if ($score >= $threshold) {
        $errors->add('fraudulent_email_error', __('Sorry, this email address was flagged as potentially fraudulent...Please try again','visual-wp-fraud-prevention'));
		
		return;
    }
	
	
    return $errors;
}

add_action('woocommerce_checkout_process', 'vwpfp_checkout_antifraud_field_validation');


function vwpfp_checkout_antifraud_field_validation() {	
	
	$max_transactions_per_minute = intval(get_option('vwpfp_max_transactions', 10)); 
	$transactionLimit = intval(vwpfp_limit_transactions_per_minute());

	if($transactionLimit > $max_transactions_per_minute) {
		wc_add_notice(__('Transaction Limit Exceeded. Please try again in a few minutes.', 'visual-wp-fraud-prevention'), 'error');
		
		// Send an email to the administrator
        $admin_email = filter_var(get_bloginfo('admin_email'), FILTER_SANITIZE_EMAIL);
        $subject = __('Transaction Attempt Limit Exceeded', 'visual-wp-fraud-prevention');
	    $user_identifier = is_user_logged_in() ? intval(get_current_user_id()) : filter_var($_SERVER['REMOTE_ADDR'], FILTER_SANITIZE_STRING);

		if(is_user_logged_in()) {
			$current_user = wp_get_current_user();
			$user_identifier = $current_user->user_email;
		}
		
        $message = __('The transaction limit of ', 'visual-wp-fraud-prevention') . $max_transactions_per_minute . __(' per minute has been exceeded by user id or IP address: ', 'visual-wp-fraud-prevention') . $user_identifier;
        wp_mail($admin_email, $subject, $message);
	}

	$score = NULL;
    $billing_email = WC()->checkout()->get_value('billing_email');

	if (isset($billing_email) && strlen($billing_email) > 0) {
        $score = vwpfp_fraud_prevention_assess_email_score($billing_email);
		$threshold = get_option('vwpfp_fraud_prevention_threshold', 6); 
		
		if($score > $threshold) {
			wc_add_notice(__('Sorry, this email address was flagged as potentially fraudulent...Please try again.', 'visual-wp-fraud-prevention'), 'error');
			return;
		}
		else {
			return;
		}
	}
	
	if($score === NULL) {
		wc_add_notice(__('Sorry, this email address was flagged as potentially fraudulent...Please try again.', 'visual-wp-fraud-prevention'), 'error');
		return;
	}
	
	return;
}


function vwpfp_limit_transactions_per_minute() {
	$max_transactions_per_minute = intval(get_option('vwpfp_max_transactions', 10)); 

    $user_identifier = is_user_logged_in() ? intval(get_current_user_id()) : filter_var($_SERVER['REMOTE_ADDR'], FILTER_SANITIZE_STRING);
    $transient_key = 'transaction_limit_' . $user_identifier . '_' . floor(time() / 60);

    // Initialize transaction count or increment if already set
    $transaction_count = intval(get_transient($transient_key));
    if ($transaction_count === false) {
        $transaction_count = 0;
    }
    $transaction_count++;

    set_transient($transient_key, $transaction_count, 60);

    if ($transaction_count > $max_transactions_per_minute) {
        
        return $transaction_count;
    }

}

/*Block user registration by domain*/
function vwpfp_block_user_registration_by_email_domain($errors, $sanitized_user_login, $user_email) {
    $blocked_domains = array('mailbox.imailfree.cc', 'email.imailfree.cc', 'inboxmail.imailfree.cc', 'mail.imailfree.cc', 'hidebox.org', 'inbox.imailfree.cc');

    $email_domain = strtolower(substr(strrchr($user_email, "@"), 1));

    if (in_array($email_domain, $blocked_domains)) {
        $errors->add('email_domain_blocked', __('Registration using this email domain is not allowed.', 'visual-wp-fraud-prevention'));
    }

    return $errors;
}

add_filter('registration_errors', 'vwpfp_block_user_registration_by_email_domain', 10, 3);

?>
