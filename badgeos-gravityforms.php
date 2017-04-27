<?php
/*
 * Plugin Name: BadgeOS Gravity Forms Add-On
 * Description: This BadgeOS add-on replaces the standard submission form with a gravity form.
 * Tags: gravityforms, badgeos
 * Author: ctrlweb
 * Version: 1.0.0
 * Author URI: https://ctrlweb.ca/
 * License: MIT
 * Text Domain: badgefactor
 * Domain Path: /languages
 */

/*
 * Copyright (c) 2017 Digital Pygmalion
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the "Software"), to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and
 * to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of
 * the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO
 * THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */


/**
 * Register the [badgeos_gravityform_submission] shortcode.
 * @since 1.0.0
 */
function bos_gform_register_submission_shortcode() {

    badgeos_register_shortcode( array(
        'name'            => __( 'GravityForm Submission', 'badgeos' ),
        'description'     => __( 'Render a GravityForm submission form.', 'badgeos' ),
        'slug'            => 'badgeos_gravityform_submission',
        'output_callback' => 'bos_gform_submission_form',
        'attributes'      => array(
            'gravityform_id' => array(
                'name'        => __( 'GravityForm ID', 'badgeos' ),
                'description' => __( 'GravityForm ID to use.', 'badgeos' ),
                'type'        => 'text',
            ),
        ),

    ) );
}
add_action( 'init', 'bos_gform_register_submission_shortcode' );

/**
 * Gravity Form Shortcode.
 * @since  1.0.0
 * @param  array $atts Shortcode attributes.
 * @return string 	   HTML markup.
 */
function bos_gform_submission_form( $atts = array() ) {

    // Parse attributes
    $atts = shortcode_atts( array(
        'gravityform_id' => get_the_ID(),
    ), $atts, 'badgeos_submission' );

    // Initialize output
    $output = '';

    // Verify user is logged in
    if ( is_user_logged_in() ) {

        $form = GFAPI::get_form($atts['gravityform_id']);
        $achievement_id = bos_gform_get_achievement_id($form);

        // If user has already submitted something, show their submissions
        if ( badgeos_check_if_user_has_submission( get_current_user_id(), $achievement_id ) ) {
            $output .= badgeos_get_user_submissions( get_current_user_id(), $achievement_id );
        }

        // If user has access to submission form, return it
        elseif ( badgeos_user_has_access_to_submission_form( get_current_user_id(), $achievement_id ) ) {
            $output .= bos_gform_get_submission_form( array( 'user_id' => get_current_user_id(), 'gravityform_id' => $atts['gravityform_id']) );
        }

        else {
            $output .= sprintf( '<p><em>%s</em></p>', __( 'You do not have access to this submission form.', 'badgeos' ) );
        }

        // Logged-out users have no access
    } else {
        $output .= sprintf( '<p><em>%s</em></p>', __( 'You must be logged in to post a submission.', 'badgeos' ) );
    }

    return $output;
}

/**
 * Get the submission form
 * @param  array  $args The meta box arguemnts
 * @return string       The concatenated markup
 */
function bos_gform_get_submission_form( $args = array() ) {

    if ( !isset($args['gravityform_id']) ) {

        return __( 'You must specify a GravityForm ID.', 'badgeos' );

    } else {

        $sub_form = do_shortcode("[gravityform id={$args['gravityform_id']}]");
        return apply_filters('badgeos_get_submission_form', $sub_form);
    }
}

/**
 * Returns the achievement_id hidden field from a form, or null if absent
 * @param $form
 * @return mixed GF_Field_Hidden or null
 */
function bos_gform_get_achievement_id_field( $form ) {
    // Checks whether or not there is an achievement_id hidden field in the GravityForm fields
    $achievement_id_field = null;
    foreach ( $form['fields'] as $field )
    {
        if ( is_a($field, 'GF_Field_Hidden') && isset($field->label) && isset($field->defaultValue) && 'achievement_id' === $field->label ) {
            $achievement_id_field = $field;
            break;
        }
    }
    return $achievement_id_field;
}

/**
 * Returns the achievement id from a form, or null if absent
 * @param $form
 * @return mixed int or null
 */
function bos_gform_get_achievement_id( $form ) {
    $achievement_id_field = bos_gform_get_achievement_id_field( $form );
    return null !== $achievement_id_field ? $achievement_id_field->defaultValue : null;
}

/**
 * Validates whether or not the form is a valid BadgeOS Submission Form
 * @param $form
 * @return mixed
 */
function bos_gform_prepare_form_submission( $form ) {

    //$achievement_id_field = bos_gform_get_achievement_id_field( $form );

    // If achievement_id field is well-defined, call the Gravity PDF Save hook
    //if (is_object($achievement_id_field) && $_POST['input_'.$achievement_id_field->id] == $achievement_id_field->defaultValue) {
    //    unset($_POST['input_'.$achievement_id_field->id]);
    //}

    return $form;

}
add_filter( 'gform_pre_submission', 'bos_gform_prepare_form_submission' );

/**
 * Create BadgeOS Submission
 * @param $form_id
 * @param $entry_id
 * @param $settings
 * @param $pdf_path
 */
function bos_gform_do_form_submission( $pdf_path, $filename, $settings, $entry, $form ) {

    $submission_title = $form['title'];
    $achievement_id = bos_gform_get_achievement_id( $form );
    $user_login = wp_get_current_user()->user_login;

    if ( null !== $achievement_id ) {

        $search_criteria['status'] = 'active';
        $search_criteria['field_filters'][] = array( 'key' => 'created_by', 'value' => wp_get_current_user()->ID);

        $entries = GFAPI::get_entries($form['id'], $search_criteria);
        $pdf_link = "#";
        foreach ($entries as $entry)
        {
            $pdf = GPDFAPI::get_form_pdfs($form['id']);
            $pdf_link = "/pdf/".key($pdf)."/".$entry['id'];
        }

        $submission_id = bos_gform_create_submission( $achievement_id, $submission_title, "<a href='$pdf_link' target='_blank'>" . __('Submitted Form', 'badgefactor') . "</a>", get_current_user_id() );


    }
}
add_action('gfpdf_post_save_pdf', 'bos_gform_do_form_submission', 10, 5);


/**
 * Create Submission form
 * @since  1.0.0
 * @param  integer $achievement_id The achievement ID intended for submission
 * @param  string  $title          The title of the post
 * @param  string  $content        The post content
 * @param  integer $user_id        The user ID
 * @return boolean                 Returns true if able to create form
 */
function bos_gform_create_submission( $achievement_id  = 0, $title = '', $content = '', $user_id = 0 ) {

    $submission_data = array(
        'post_title'	=>	$title,
        'post_content'	=>	$content,
        'post_status'	=>	'publish',
        'post_author'	=>	$user_id,
        'post_type'		=>	'submission',
    );

    //insert the post into the database
    if ( $submission_id = wp_insert_post( $submission_data ) ) {
        // save the achievement ID related to the submission
        add_post_meta( $submission_id, '_badgeos_submission_achievement_id', $achievement_id );

        // Available action for other processes
        do_action( 'badgeos_save_submission', $submission_id );

        // Submission status workflow
        $status_args = array(
            'achievement_id' => $achievement_id,
            'user_id' => $user_id
        );

        $status = 'pending';

        // Check if submission is auto approved or not
        if ( badgeos_is_submission_auto_approved( $submission_id ) ) {
            $status = 'approved';

            $status_args[ 'auto' ] = true;
        }

        badgeos_set_submission_status( $submission_id, $status, $status_args );

        return $submission_id;

    } else {

        return false;

    }
}

function bos_gform_get_user_submissions( $userId, $achievement_id ) {

    $args = array();

    // Setup our default args
    $defaults = array(
        'author'           => $userId,
        'post_type'        => 'submission',
        'show_attachments' => true,
        'show_comments'    => true,
        'meta_query' => array(
            array(
                'key' => '_badgeos_submission_achievement_id',
                'value' => $achievement_id
            )
        )
    );
    $args = wp_parse_args( $args, $defaults );

    // Grab our submissions
    return get_posts( $args );
}