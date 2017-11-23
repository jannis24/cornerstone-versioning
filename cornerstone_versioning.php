<?php
/*
Plugin Name: Cornerstone Versioning
Description: Creates versioning for Cornerstone
Plugin URI: https://thuemmig.com/
Author: Jannis Thümmig
URI: https://thuemmig.com/
Version: 1.0
Text Domain: cornerstone-versioning
Domain Path: /languages
*/

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();

/**
 * main class for the plugin
 */
class Cornerstone_Versioning {

    public static $_instance;
    private $plugin_name;
    private $version;
    private $text_domain;

    public function __construct() {

        $this->plugin_name = 'cornerstone-versioning';
        $this->version = '1.0';
        $this->text_domain = 'cvtdm';
        $this->capability = apply_filters('cv_main_capability', 'manage_options');
        $this->posttypes = apply_filters('cv_allowed_post_types', array(
            'page'
        ));
        $this->version_prefix = apply_filters('cv_version_prefix', '_cv_version_');

        $this->hooks();
    }

    public function hooks(){
        add_action('cornerstone_after_save_content', array($this, 'cv_cornerstone_after_save_content'), 20);
        add_action( 'add_meta_boxes', array($this, 'cv_notice_meta_box' ));
        add_action('add_meta_boxes', array($this, 'cv_restore_cornerstone'), 0);
        #add_action( 'save_post', array($this, 'save_cv_meta_box_data' ));
    }

    /**
     * Create the metaboxes based on the validated post types
     */
    function cv_notice_meta_box() {

        if(!$this->cv_is_user_valid())
            return;

        foreach ( $this->posttypes as $screen ) {
            add_meta_box(
                $this->plugin_name,
                __( 'Cornerstone Revisions', $this->text_domain ),
                array($this, 'cv_notice_meta_box_callback'),
                $screen,
                'normal',
                'low'
                //array( 'foo' => $var1, 'bar' => $var2 )
            );
        }
    }

    /**
     * Validates if cornerstone is available on the specified site
     *
     * @return bool - true if available, false if not
     */
    public function cv_is_cornerstone_available($id){
        if(empty($id))
            false;

        $cs_data = get_post_meta($id, '_cornerstone_data', true);
        if(!empty($cs_data))
            return true;

        return false;

    }

    /**
     * Callback of our Custom Meta Box
     *
     * @param $post - The global Post
     */
    function cv_notice_meta_box_callback( $post ) {
        $html_body = '';
        $restore_var = __('Restore', $this->text_domain);
        $delete_var = __('Delete', $this->text_domain);
        $revisions = $this->cv_get_revisions($post->ID);
        foreach($revisions as$revision){
            if(!empty($revision['meta_key'])){
                $args = array(
                    'cv_restore_data' => $revision['meta_key'],
                    'cv_action' => 'restore'
                );
                $restore_url = $this->cv_generate_action_url($args);
                $args = array(
                    'cv_restore_data' => $revision['meta_key'],
                    'cv_action' => 'delete'
                );
                $delete_url = $this->cv_generate_action_url($args);
                $version_name = 'Version: ' . str_replace($this->version_prefix, '', $revision['meta_key']);

                $html_body .= '<tr class="alternate">';
                $html_body .= '<td class="check-column" scope="row">' . $version_name . '</td>';
                $html_body .= '<td class="column-columnname">';
                $html_body .= '<span><a class="cr-restore-revision button button-primary" href="' . $restore_url . '">' . $restore_var . '</a></span> <span class="cr-sep">|</span> ';
                $html_body .= '<span><a class="cr-delete-revision" href="' . $delete_url . '">' . $delete_var . '</a></span>';
                $html_body .= '</td>';
                $html_body .= '</tr>';
            }
        }

        ob_start();
        ?>
        <style>
        #cornerstone-versioning .inside {
        padding: 0;
        margin: 0;
        }

        #cornerstone-versioning table {
        border: none;
        }

        #cornerstone-versioning th.cr-revision-head,
        #cornerstone-versioning th.cr-action-head {
        text-align: left;
        width: 500px;
        }

        #cornerstone-versioning th,
        #cornerstone-versioning td {
        padding: 10px 12px;
        vertical-align: middle;
        }

        #cornerstone-versioning th {
        font-weight: 600;
        }

        #cornerstone-versioning td {

        }

        #cornerstone-versioning .cr-delete-revision {
        border-color: transparent;
        background-color: transparent;
        box-shadow: none;
        color: #a00;
        }

        #cornerstone-versioning .cr-delete-revision:hover {
        color: #d54e21;
        }

        /* #cornerstone-versioning .cr-delete-revision:hover {
        background-color: #d54e21;
        border-color: #d54e21;
        color: #fff;
        } */

        #cornerstone-versioning tbody tr td {
        border-top: 1px solid #dfdfdf;
        background-color: #f7f7f7;
        }

        #cornerstone-versioning tbody tr:first-child td {
        border-top: none;
        }

        #cornerstone-versioning tbody tr:hover td {
        background-color: #fff;
        }

        #cornerstone-versioning td span {
        vertical-align: middle;
        display: inline-block;
        margin-right: 5px;
        }

        #cornerstone-versioning td span:last-child {
        margin-right: 0;
        }

        #cornerstone-versioning td span.cr-sep {
        color: #aaa;
        }
        </style>
        <table class="widefat fixed" cellspacing="0">
            <thead>
            <tr>
                <th class="cr-revision-head manage-column column-columnname num" scope="col" style="width:65%"><?php echo __('Revision', $this->text_domain); ?></th>
                <th class="cr-action-head manage-column column-columnname num" scope="col" style="width:35%"><?php echo __('Action', $this->text_domain); ?></th>
            </tr>
            </thead>

            <tbody>
                <?php echo $html_body; ?>
            </tbody>
        </table>
        <?php
        $res = ob_get_clean();
        echo $res;

    }

    /**
     * Helpers
     */

    /**
     * Create and return a meta key name for identifying the
     * generated version
     *
     * @return $string - The name of the currently saved post
     */
    public function cv_get_version_name(){

        $prefix = $this->version_prefix;
        $version_name = $prefix . date("Y-m-d-H-i-s");

        return apply_filters('cv_filter_version_name', $version_name, $prefix);
    }

    /**
     * Validates if a user is able to edit the product
     * AND check if cornerstone is available
     *
     * @return bool
     */
    public function cv_is_user_valid(){

        $return = true;
        if(!is_user_logged_in())
            $return = false;

        if(!current_user_can($this->capability))
            $return = false;

        if(!$this->cv_is_cornerstone_available(get_the_ID()))
            $return = false;

        $return = apply_filters('cv_is_user_able_to', $return);

        return $return;
    }

    /**
     * Generates an URL out of the current and given params
     *
     * @param array $args - an array of custom parameters
     * @return string - the url
     */
    public function cv_generate_action_url($args = array()){
        $params = array_merge($_GET, $args);
        return basename($_SERVER['PHP_SELF']).'?'.http_build_query($params);
    }

    /**
     * Retrieve a custom built array for saving all necessary values to the database
     *
     * @param $id - The post id
     * @return array|bool - false if no id is set
     */
    public function cv_get_cornerstone_array($id){
        if(empty($id))
            return false;

        $data = array();
        $cs_data = cs_get_serialized_post_meta($id, '_cornerstone_data', true);
        $cs_settings = cs_get_serialized_post_meta($id, '_cornerstone_settings', true);
        $cs_version = get_post_meta($id, '_cornerstone_version', true);
        $cs_post_content = get_post($id)->post_content;
        $save_as_json = apply_filters( 'cornerstone_store_as_json', true );

        if(!empty($cs_data)){
            if ( is_array( $cs_data ) && $save_as_json ) {
                $cs_data = wp_slash( cs_json_encode( $cs_data ) );
            }

            $data['_cornerstone_data'] = $cs_data;
        }


        if(!empty($cs_settings)){
            if ( is_array( $cs_settings ) && $save_as_json ) {
                $cs_settings = wp_slash( cs_json_encode( $cs_settings ) );
            }

            $data['_cornerstone_settings'] = $cs_settings;
        }

        if(!empty($cs_version)){
            $data['_cornerstone_version'] = $cs_version;
        }

        if(!empty($cs_post_content))
            $data['content'] = $cs_post_content;

        $data = apply_filters('cv_edit_custom_saveable_array', $data, $id);

        return $data;
    }

    /**
     * Core function for restoring the data out of our custom versioning array
     *
     * @param $data - the name of the meta key
     * @param $id - the post id
     * @return void
     */
    public function cv_restore_cornerstone_data($data, $id){

        if(empty($id) || empty($data))
            return;

        $id = intval($id);
        $data = get_post_meta($id, $data, true);

        if(isset($data['_cornerstone_data'])){
            if(!is_array($data['_cornerstone_data']))
                $data['_cornerstone_data'] = json_decode($data['_cornerstone_data'], true);

            if(!empty($data['_cornerstone_data']))
                cs_update_serialized_post_meta($id, '_cornerstone_data', $data['_cornerstone_data']);
        }

        if(isset($data['_cornerstone_settings'])){
            if(!is_array($data['_cornerstone_settings']))
                $data['_cornerstone_settings'] = json_decode($data['_cornerstone_settings'], true);

            if(!empty($data['_cornerstone_settings']))
                cs_update_serialized_post_meta($id, '_cornerstone_settings', $data['_cornerstone_settings']);
        }

        if(isset($data['_cornerstone_version'])){
            if(!empty($data['_cornerstone_version']))
                update_post_meta($id, '_cornerstone_version', $data['_cornerstone_version']);
        }

        if(isset($data['content'])){
            $args = array(
                'ID'           => $id,
                'post_content' => $data['content'],
            );

            wp_update_post( $args );
        }

        /**
         * Action for apply custom values as well
         */
        do_action('cv_after_restoring_data', $data, $id);

    }

    /**
     * Delete a specific versioning part of our arrays
     *
     * @param $data - The meta key)
     * @param $id - the post id
     * @return bool
     */
    public function cv_delete_cornerstone_data($data, $id){

        if(empty($id) || empty($data))
            return false;

        $id = intval($id);

        $check = delete_post_meta($id, $data);

        return $check;

    }

    /**
     * Save the cornerstone version to the postmeta keys
     *
     * @param $data - the meta key
     * @param $id - post_id
     * @return bool|int
     */
    public function update_cornerstone_version($data, $id){
        if(empty($data) || empty($id))
            return false;

        $check = update_post_meta($id, $this->cv_get_version_name(), $data);

        return $check;
    }

    /**
     *
     * Core logic
     *
     */

    /**
     * The global action to handle the restoring/deletion process
     */
    public function cv_restore_cornerstone(){
        if(!$this->cv_is_user_valid())
            return;

        global $post;
        $is_valid = false;
        $posttype = get_post_type($post->ID);
        foreach($this->posttypes as $single){
            if($posttype == $single)
                $is_valid = true;
        }

        if($is_valid) {
            if (isset($_GET['cv_restore_data'])) {

                if (isset($_GET['cv_action']) && $_GET['cv_action'] == 'restore') {
                    $this->cv_restore_cornerstone_data($_GET['cv_restore_data'], $post->ID);
                } elseif (isset($_GET['cv_action']) && $_GET['cv_action'] == 'delete') {
                    $this->cv_delete_cornerstone_data($_GET['cv_restore_data'], $post->ID);
                }

            }
        }
    }

    /**
     * Handler for updating the choosen version
     *
     * @param $post_id
     */
    public function cv_cornerstone_after_save_content($post_id){

        if(!$this->cv_is_user_valid())
            return;

        if(is_numeric($post_id)){
            $id = $post_id;
        } else {
            $id = get_the_ID();
        }

        if(!empty($id)){

            $cs_data = $this->cv_get_cornerstone_array($id);

            if(!empty($cs_data)){
                $this->update_cornerstone_version($cs_data, $id);
            }
        }

    }

    /**
     * Grab all available revision for the given post id from the database
     *
     * @param $id
     * @return array|bool|null|object
     */
    public function cv_get_revisions($id){
        if(empty($id))
            return false;

        global $wpdb;

        $id = intval($id);
        $query = "SELECT meta_key FROM {$wpdb->prefix}postmeta WHERE meta_key LIKE '$this->version_prefix%' AND post_id = $id";

        $results = $wpdb->get_results($query,ARRAY_A);
        return $results;
    }
}
new Cornerstone_Versioning();