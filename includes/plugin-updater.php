<?php
/**
 * Plugin Update Checker
 * Handles automatic updates from a remote server or GitHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class ACT_Plugin_Updater {
    
    private $plugin_slug;
    private $version;
    private $plugin_path;
    private $plugin_file;
    private $github_username;
    private $github_repo;
    private $access_token;
    
    public function __construct($plugin_file, $github_username, $github_repo, $access_token = '') {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = $this->get_plugin_version();
        $this->plugin_path = plugin_basename($plugin_file);
        $this->github_username = $github_username;
        $this->github_repo = $github_repo;
        $this->access_token = $access_token;
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
    }
    
    /**
     * Get current plugin version
     */
    private function get_plugin_version() {
        $plugin_data = get_plugin_data($this->plugin_file);
        return $plugin_data['Version'];
    }
    
    /**
     * Check for plugin updates
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Get remote version
        $remote_version = $this->get_remote_version();
        
        if (version_compare($this->version, $remote_version, '<')) {
            $transient->response[$this->plugin_path] = (object) array(
                'slug' => $this->plugin_slug,
                'plugin' => $this->plugin_path,
                'new_version' => $remote_version,
                'url' => $this->get_github_repo_url(),
                'package' => $this->get_download_url($remote_version)
            );
        }
        
        return $transient;
    }
    
    /**
     * Get plugin information for the update popup
     */
    public function plugin_info($false, $action, $response) {
        if ($action !== 'plugin_information' || $response->slug !== $this->plugin_slug) {
            return false;
        }
        
        $remote_version = $this->get_remote_version();
        $changelog = $this->get_changelog();
        
        return (object) array(
            'name' => 'WooCommerce Abandoned Cart Tracker',
            'slug' => $this->plugin_slug,
            'version' => $remote_version,
            'author' => 'Your Name',
            'homepage' => $this->get_github_repo_url(),
            'requires' => '5.0',
            'tested' => '6.4',
            'requires_php' => '7.4',
            'sections' => array(
                'description' => 'Comprehensive WooCommerce plugin to track abandoned carts and provide detailed statistics.',
                'changelog' => $changelog
            ),
            'download_link' => $this->get_download_url($remote_version)
        );
    }
    
    /**
     * Get remote version from GitHub releases
     */
    private function get_remote_version() {
        $request_uri = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repo
        );
        
        $args = array();
        if (!empty($this->access_token)) {
            $args['headers'] = array(
                'Authorization' => 'token ' . $this->access_token
            );
        }
        
        $response = wp_remote_get($request_uri, $args);
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['tag_name'])) {
                return ltrim($data['tag_name'], 'v'); // Remove 'v' prefix if present
            }
        }
        
        return $this->version; // Return current version if API call fails
    }
    
    /**
     * Get download URL for the latest release
     */
    private function get_download_url($version) {
        return sprintf(
            'https://github.com/%s/%s/archive/refs/tags/v%s.zip',
            $this->github_username,
            $this->github_repo,
            $version
        );
    }
    
    /**
     * Get GitHub repository URL
     */
    private function get_github_repo_url() {
        return sprintf(
            'https://github.com/%s/%s',
            $this->github_username,
            $this->github_repo
        );
    }
    
    /**
     * Get changelog from GitHub releases
     */
    private function get_changelog() {
        $request_uri = sprintf(
            'https://api.github.com/repos/%s/%s/releases',
            $this->github_username,
            $this->github_repo
        );
        
        $args = array();
        if (!empty($this->access_token)) {
            $args['headers'] = array(
                'Authorization' => 'token ' . $this->access_token
            );
        }
        
        $response = wp_remote_get($request_uri, $args);
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $releases = json_decode($body, true);
            
            $changelog = '';
            if (is_array($releases)) {
                foreach (array_slice($releases, 0, 5) as $release) { // Last 5 releases
                    $changelog .= sprintf(
                        "<h4>%s</h4>\n<p>%s</p>\n",
                        esc_html($release['tag_name']),
                        esc_html($release['body'])
                    );
                }
            }
            
            return $changelog;
        }
        
        return '<p>No changelog available.</p>';
    }
}
