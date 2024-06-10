<?php
/**
 * Plugin Name:         Ultimate Member - Network URL validation service reject time window
 * Description:         Extension to Ultimate Member for setting a network URL validation service reject time window for user registration email activation.
 * Version:             1.0.0
 * Requires PHP:        7.4
 * Author:              Miss Veronica
 * License:             GPL v3 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:          https://github.com/MissVeronica
 * Text Domain:         ultimate-member
 * Domain Path:         /languages
 * UM version:          2.8.6
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'UM' ) ) return;

class UM_Network_Validation_Reject_Time {

    public $time_window = array(
                                5   => '5',
                                10  => '10',
                                15  => '15',
                                20  => '20',
                                25  => '25',
                                30  => '30',
                                35  => '35',
                                40  => '40',
                                45  => '45',
                                50  => '50',
                                55  => '55',
                                60  => '60',
                                120 => '120',
                                180 => '180',
                                240 => '240',
                                300 => '300',
                                360 => '360',
                                420 => '420',
                                480 => '480',
                                540 => '540',
                                600 => '600',
                            );

    function __construct() {

        add_action( 'um_before_email_notification_sending', array( $this, 'um_before_email_notification_sending_new_transient' ), 10, 3 );
        add_action( 'init',                                 array( $this, 'activate_account_via_email_link_network_reject' ), 0 );
        add_filter( 'um_settings_structure',                array( $this, 'um_admin_settings_link_network_reject' ), 10, 1 );

    }

    public function um_before_email_notification_sending_new_transient( $email, $template, $args ) {

        if ( $template == 'checkmail_email' && ! empty( um_user( 'account_secret_hash' ))) {

            $exclude = $this->select_reject_window( 'um_network_reject_exclude', $email );
            $include = $this->select_reject_window( 'um_network_reject_domains', $email );

            if ( $exclude != 'exists' && $include != 'none' ) {

                $seconds = intval( UM()->options()->get( 'um_network_reject_seconds' ));
                if ( $seconds >= min( $this->time_window ) && $seconds <= max( $this->time_window ) ) {

                    set_transient( 'um_network_reject_' . um_user( 'account_secret_hash' ), array ( 'time' => time(), 'ID' => um_user( 'ID' )), $seconds );
                }
            }
        }
    }

    public function select_reject_window( $id, $email ) {

        $reply = 'empty';
        $domains = UM()->options()->get( $id );

        if ( ! empty( $domains )) {

            $domains = array_map( 'sanitize_text_field', array_map( 'trim', explode( "\n", $domains )));

            if ( is_array( $domains )) {

                $reply = 'none';
                $email_domain = explode( '@', $email );
                if ( in_array( $email_domain[1], $domains )) {
                    $reply = 'exists';
                }
            }
        }

        return $reply;
    }

    public function activate_account_via_email_link_network_reject() {

        if ( isset( $_REQUEST['act'] )     && 'activate_via_email' === sanitize_key( $_REQUEST['act'] ) && 
             isset( $_REQUEST['hash'] )    && is_string( $_REQUEST['hash'] ) && strlen( $_REQUEST['hash'] ) == 40 &&
             isset( $_REQUEST['user_id'] ) && is_numeric( $_REQUEST['user_id'] ) ) { // valid token

            $transient = get_transient( 'um_network_reject_' . $_REQUEST['hash'] );

            if ( ! empty( $transient ) && is_array( $transient ) && count( $transient ) == 2 ) {

                $seconds = intval( UM()->options()->get( 'um_network_reject_seconds' ));
                if ( isset( $transient['time'] ) && ( time() - $transient['time'] ) < $seconds ) {

                    if ( isset( $transient['ID'] ) && $transient['ID'] == $_REQUEST['user_id'] ) {

                        $message = sanitize_text_field( UM()->options()->get( 'um_network_reject_message' ));

                        if ( UM()->options()->get( 'um_network_reject_terminate' ) == 1 ) {
                            delete_transient( 'um_network_reject_' . $_REQUEST['hash'] );

                        } else {
                            $wait = $seconds - ( time() - $transient['time'] );
                            $message = str_replace( '{waiting-time}', $wait, $message );
                        }
                        
                        wp_die( $message );
                    }
                }
            }
        }
    }

    public function um_admin_settings_link_network_reject( $settings ) {

        $settings['advanced']['sections']['developers']['form_sections']['network_reject'] = array(

                                'title'	      => __( 'Network URL validation service reject time window', 'ultimate-member' ),
                                'description' => __( 'Setting a network URL validation service reject time window for user registration email activation.', 'ultimate-member' ),

                                'fields'      => array(
                                        array(
                                            'id'          => 'um_network_reject_seconds',
                                            'type'        => 'select',
                                            'size'        => 'short',
                                            'label'       => __( 'Select number of seconds', 'ultimate-member' ),
                                            'options'     => $this->time_window,
                                            'description' => __( 'Select number of seconds from sending the user activation email until the user activation click is accepted.', 'ultimate-member' ),
                                        ),

                                        array(
                                            'id'              => 'um_network_reject_terminate',
                                            'type'            => 'checkbox',
                                            'label'           => __( 'Terminate the reject time window after one click attempt', 'ultimate-member' ),
                                            'checkbox_label'  => __( 'Click to allow user email activation after first network URL validation service click attempt.', 'ultimate-member' ),
                                            'description'     => 'Unclicking to not terminate reject window you can use the {waiting-time} placeholder in the link reject text message.',
                                        ),

                                        array(
                                            'id'          => 'um_network_reject_message',
                                            'type'        => 'text',
                                            'label'       => __( 'Link reject message', 'ultimate-member' ),
                                            'default'     => __( 'Please click the user email activation link again.', 'ultimate-member' ),
                                            'description' => __( 'The text message to show when activation link is clicked within the reject time window either by an URL validation service or the User.', 'ultimate-member' ),
                                        ),

                                        array(
                                            'id'          => 'um_network_reject_exclude',
                                            'type'        => 'textarea',
                                            'size'        => 'medium',
                                            'label'       => __( 'Exclude the reject time window for these email domains', 'ultimate-member' ),
                                            'description' => __( 'Enter registration user email domains one per line like company.com', 'ultimate-member' ),
                                        ),

                                        array(
                                            'id'          => 'um_network_reject_domains',
                                            'type'        => 'textarea',
                                            'size'        => 'medium',
                                            'label'       => __( 'Include the reject time window for these email domains', 'ultimate-member' ),
                                            'description' => __( 'Enter registration user email domains one per line like company.com and an empty text field equals to all user email domains.', 'ultimate-member' ),
                                        ),

                                    ),
                            );

        return $settings;
    }
}


new UM_Network_Validation_Reject_Time();
