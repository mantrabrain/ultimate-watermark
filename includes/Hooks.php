<?php

namespace Ultimate_Watermark;
class Hooks
{
    public function __construct()
    {
        add_filter('wp_handle_upload', array($this, 'handle_upload_files'));

        add_filter('attachment_fields_to_edit', array($this, 'attachment_fields_to_edit'), 10, 2);

        add_action('load-upload.php', array($this, 'watermark_bulk_action'));

        add_action('delete_attachment', array($this, 'delete_attachment'));

    }

    public function handle_upload_files($file)
    {


        ultimate_watermark()->utils->check_extensions();
        // is extension available?
        if (ultimate_watermark()->utils->get_extension()) {
            // determine ajax frontend or backend request
            $script_filename = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';

            // try to figure out if frontend AJAX request... if we are DOING_AJAX; let's look closer
            if ((defined('DOING_AJAX') && DOING_AJAX)) {
                // from wp-includes/functions.php, wp_get_referer() function.
                // required to fix: https://core.trac.wordpress.org/ticket/25294
                $ref = '';
                if (!empty($_REQUEST['_wp_http_referer']))
                    $ref = wp_unslash($_REQUEST['_wp_http_referer']);
                elseif (!empty($_SERVER['HTTP_REFERER']))
                    $ref = wp_unslash($_SERVER['HTTP_REFERER']);

                // if referer does not contain admin URL and we are using the admin-ajax.php endpoint, this is likely a frontend AJAX request
                if (((strpos($ref, admin_url()) === false) && (basename($script_filename) === 'admin-ajax.php')))
                    $this->is_admin = false;
                else
                    $this->is_admin = true;
                // not an AJAX request, simple here
            } else {
                if (is_admin())
                    $this->is_admin = true;
                else
                    $this->is_admin = false;
            }
                        // admin
            if ($this->is_admin === true || current_user_can('upload_files')) {
                if (ultimate_watermark_automatic_watermarking() && ultimate_watermark_watermark_image() != 0 && in_array($file['type'], ultimate_watermark()->utils->get_allowed_mime_types())) {

                    add_filter('wp_generate_attachment_metadata', array(ultimate_watermark()->watermark, 'apply_watermark'), 10, 2);

                }
                // frontend
            } else {
                if (ultimate_watermark_frontend_watermarking() && ultimate_watermark_watermark_image() != 0 && in_array($file['type'], ultimate_watermark()->utils->get_allowed_mime_types())) {
                    add_filter('wp_generate_attachment_metadata', array(ultimate_watermark()->watermark, 'apply_watermark'), 10, 2);
                }
            }
        }

        return $file;
    }

    public function attachment_fields_to_edit($form_fields, $post)
    {
        if (ultimate_watermark_manual_watermarking() && ultimate_watermark_backup_image()) {

            $data = wp_get_attachment_metadata($post->ID, false);

            // is this really an image?
            if (in_array(get_post_mime_type($post->ID), ultimate_watermark()->utils->get_allowed_mime_types()) && is_array($data)) {
                $form_fields['ultimate_watermark'] = array(
                    'show_in_edit' => false,
                    'tr' => '
					<div id="ultimate_watermark_buttons"' . (get_post_meta($post->ID, 'ulwm-is-watermarked', true) ? ' class="watermarked"' : '') . ' data-id="' . $post->ID . '" style="display: none;">
						<label class="setting">
							<span class="name">' . __('Ultimate Watermark', 'ultimate-watermark') . '</span>
							<span class="value" style="width: 63%"><a href="#" class="ulwm-watermark-action" data-action="applywatermark" data-id="' . $post->ID . '">' . __('Apply watermark', 'ultimate-watermark') . '</a> | <a href="#" class="ulwm-watermark-action delete-watermark" data-action="removewatermark" data-id="' . $post->ID . '">' . __('Remove watermark', 'ultimate-watermark') . '</a></span>
						</label>
						<div class="clear"></div>
					</div>
					<script>
						jQuery( document ).ready( function ( $ ) {
							if ( typeof watermarkImageActions != "undefined" ) {
								$( "#ultimate_watermark_buttons" ).show();
							}
						});
					</script>'
                );
            }
        }
        return $form_fields;
    }

    public function watermark_bulk_action()
    {
        global $pagenow;

        if ($pagenow == 'upload.php' && ultimate_watermark()->utils->get_extension()) {
            $wp_list_table = _get_list_table('WP_Media_List_Table');
            $action = false;

            switch ($wp_list_table->current_action()) {
                case 'applywatermark':
                    $action = 'applywatermark';
                    break;

                case 'removewatermark':
                    $action = 'removewatermark';
            }

            // only if manual watermarking is turned and we have a valid action
            // if the action is NOT "removewatermark" we also require a watermark image to be set
            if ($action && ultimate_watermark_manual_watermarking() && (ultimate_watermark_watermark_image() != 0 || $action == 'removewatermark')) {
                // security check
                check_admin_referer('bulk-media');

                $location = esc_url(remove_query_arg(array('watermarked', 'watermarkremoved', 'skipped', 'trashed', 'untrashed', 'deleted', 'message', 'ids', 'posted'), wp_get_referer()));

                if (!$location)
                    $location = 'upload.php';

                $location = esc_url(add_query_arg('paged', $wp_list_table->get_pagenum(), $location));

                // make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
                if (isset($_REQUEST['media']))
                    $post_ids = array_map('intval', $_REQUEST['media']);

                // do we have selected attachments?
                if ($post_ids) {
                    $watermarked = $watermarkremoved = $skipped = 0;
                    $messages = array();

                    foreach ($post_ids as $post_id) {
                        $data = wp_get_attachment_metadata($post_id, false);

                        // is this really an image?
                        if (in_array(get_post_mime_type($post_id), ultimate_watermark()->utils->get_allowed_mime_types()) && is_array($data)) {
                            if ($action === 'applywatermark') {
                                $success = ultimate_watermark()->watermark->apply_watermark($data, $post_id, 'manual');
                                if (!empty($success['error']))
                                    $messages[] = $success['error'];
                                else {
                                    $watermarked++;
                                    $watermarkremoved = -1;
                                }
                            } elseif ($action === 'removewatermark') {
                                $success = ultimate_watermark()->watermark->remove_watermark($data, $post_id, 'manual');

                                if ($success)
                                    $watermarkremoved++;
                                else
                                    $skipped++;

                                $watermarked = -1;
                            }
                        } else
                            $skipped++;
                    }

                    $location = esc_url(add_query_arg(array('watermarked' => $watermarked, 'watermarkremoved' => $watermarkremoved, 'skipped' => $skipped, 'messages' => $messages), $location), null, '');
                }

                wp_redirect($location);
                exit();
            } else
                return;
        }
    }

    public function delete_attachment($attachment_id)
    {
        // see get_attached_file() in wp-includes/post.php
        $filepath = get_post_meta($attachment_id, '_wp_attached_file', true);
        $backup_filepath = ultimate_watermark()->utils->get_image_backup_filepath($filepath);

        if (file_exists($backup_filepath)) {
            unlink($backup_filepath);
        }
    }

}