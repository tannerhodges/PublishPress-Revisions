<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Gutenberg Block Editor support
//
// This script executes on the 'init' action if is_admin() and $pagenow is 'post-new.php' or 'post.php' and the block editor is active.
//

add_action( 'enqueue_block_editor_assets', array( 'RVY_PostBlockEditUI', 'act_object_guten_scripts' ) );

class RVY_PostBlockEditUI {
	public static function act_object_guten_scripts() {
        global $current_user;
        
        if ( ! $post_id = rvy_detect_post_id() ) {
            return;
        }

        $post_type = rvy_detect_post_type();

		if ( ! $type_obj = get_post_type_object( $post_type ) ) {
            return;
        }

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        
        global $post;

        $do_pending_revisions = rvy_get_option('pending_revisions');
        $do_scheduled_revisions = rvy_get_option('scheduled_revisions');

        if ( ('revision' == $post_type) || rvy_is_revision_status($post->post_status) ) {
            wp_enqueue_script( 'rvy_object_edit', RVY_URLPATH . "/admin/rvy_revision-block-edit{$suffix}.js", array('jquery', 'jquery-form'), RVY_VERSION, true );

            $_arg = ('page' == $post->post_type) ? 'page_id=' : 'p=';
			$view_link = add_query_arg( 'preview', true, str_replace( 'p=', $_arg, get_post_permalink( $post ) ) );

            if ($can_publish = agp_user_can($type_obj->cap->edit_post, rvy_post_id($post->ID), '', array('skip_revision_allowance' => true))) {
                $view_caption = ('future-revision' == $post->post_status) ? __('View / Publish') : __('View / Approve');
                $view_title = __('View / moderate saved revision', 'revisionary');
            } else {
                $view_caption = __('View');
                $view_title = __('View saved revision', 'revisionary');
            }

            $preview_title = __('View unsaved changes', 'revisionary');

            $_revisions = wp_get_post_revisions($post_id);
            if ($_revisions && count($_revisions) > 1) {
                $revisions_caption = sprintf(_n('<span class="dashicons dashicons-backup"></span>&nbsp;%s Revision Edit', '<span class="dashicons dashicons-backup"></span>&nbsp;%s Revision Edits', count($_revisions), 'revisionary'), count($_revisions));
            } else {
                $revisions_caption = '';
            }

            $args = array(
                'saveRevision' => __('Update Revision'),
                'viewURL' => $view_link,
                'viewCaption' => $view_caption,
                'viewTitle' => $view_title,
                'previewTitle' => $preview_title,
                'revisionEdits' => $revisions_caption,
            );  

        } elseif ( agp_user_can( $type_obj->cap->edit_post, $post_id, '', array( 'skip_revision_allowance' => true ) ) ) {
            wp_enqueue_script( 'rvy_object_edit', RVY_URLPATH . "/admin/rvy_post-block-edit{$suffix}.js", array('jquery', 'jquery-form'), RVY_VERSION, true );

            // for logged user who can fully edit a published post, clarify the meaning of setting future publish date
            // @todo  ?>
            <!--
            <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready( function($) {
                postL10n.schedule = "<?php _e('Schedule Revision', 'revisionary' )?>";
            });
            /* ]]> */
            </script>
            -->
            <?php
            $args = array();

            if (!isset($preview_url)) {
                $preview_url = '';
            }

            $future_status = 'future-revision';
            $pending_status = 'pending-revision';
            $args = array(
                'redirectURLscheduled' => admin_url("edit.php?post_type={$post_type}&revision_submitted={$future_status}&post_id={$post_id}"),
                'redirectURLpending' => admin_url("edit.php?post_type={$post_type}&revision_submitted={$pending_status}&post_id={$post_id}"),
                'userID' => $current_user->ID,
                'ScheduleCaption' => ($do_scheduled_revisions) ? __('Schedule Revision', 'revisionary') : '',
                'UpdateCaption' => __('Update'),
                'revision' => ($do_pending_revisions) ? __('Pending Revision', 'revisionary') : '',
                'revisionTitle' => attribute_escape(__('Do not publish current changes yet, but save to Revision Queue', 'revisionary')), 
                'revisionTitleFuture' => attribute_escape(__('Do not schedule current changes yet, but save to Revision Queue', 'revisionary')), 
                'ajaxurl' => admin_url(''),
                'SaveCaption' => ($do_pending_revisions) ? __('Save Revision', 'revisionary') : '',
                'previewURL' => $preview_url,
            );

            if ($do_pending_revisions && $_revisions = rvy_get_post_revisions($post_id, 'pending-revision', ['orderby' => 'ID', 'order' => 'ASC'])) {
                $status_obj = get_post_status_object('pending-revision');
                $args['pendingRevisionsCaption'] = sprintf(_n('<span class="dashicons dashicons-edit"></span>&nbsp;%s Pending Revision', '<span class="dashicons dashicons-edit"></span>&nbsp;%s Pending Revisions', count($_revisions), 'revisionary'), count($_revisions));

                //$last_revision = array_pop($_revisions);
                //$args['pendingRevisionsURL'] = admin_url("revision.php?revision=$last_revision->ID");   // @todo: fix i8n
                $args['pendingRevisionsURL'] = admin_url("revision.php?post_id=$post_id&revision=pending-revision");   // @todo: fix i8n
            } else {
                $args['pendingRevisionsURL'] = '';
            }

            if ($do_scheduled_revisions && $_revisions = rvy_get_post_revisions($post_id, 'future-revision', ['orderby' => 'ID', 'order' => 'ASC'])) {
                $status_obj = get_post_status_object('future-revision');
                $args['scheduledRevisionsCaption'] = sprintf(_n('<span class="dashicons dashicons-clock"></span>&nbsp;%s Scheduled Revision', '<span class="dashicons dashicons-clock"></span>&nbsp;%s Scheduled Revisions', count($_revisions), 'revisionary'), count($_revisions));
                
                //$last_revision = array_pop($_revisions);
                //$args['scheduledRevisionsURL'] = admin_url("revision.php?revision=$last_revision->ID");
                $args['scheduledRevisionsURL'] = admin_url("revision.php?post_id=$post_id&revision=future-revision");
            } else {
                $args['scheduledRevisionsURL'] = '';
            }

            // clear scheduled revision redirect flag
            delete_post_meta( $post_id, "_new_scheduled_revision_{$current_user->ID}" );
            delete_post_meta( $post_id, "_save_as_revision_{$current_user->ID}" );

        } elseif($do_pending_revisions) {
            //div.editor-post-publish-panel button.editor-post-publish-button
            wp_enqueue_script( 'rvy_object_edit', RVY_URLPATH . "/admin/rvy_post-block-edit-revisor{$suffix}.js", array('jquery', 'jquery-form'), RVY_VERSION, true );
            
            $status = 'pending-revision';
            $args = array(
                'publish' =>    __('Submit Revision'), 
                'saveAs' =>     __('Submit Revision'), 
                'prePublish' => __( 'Workflow&hellip;', 'revisionary' ),
                'redirectURL' => admin_url("edit.php?post_type={$post_type}&revision_submitted={$status}&post_id={$post_id}"),
            );  
        }

        wp_localize_script( 'rvy_object_edit', 'rvyObjEdit', $args );
    }
} // end class
