<?php
/**
 * GitHub Auto-Update Checker using YahnisElsts/plugin-update-checker v5
 *
 * @package MisterCoffeeOutletFinder
 */

if (!defined('ABSPATH')) {
    exit;
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class MC_Outlet_Updater {

    private static $updateChecker = null;

    public static function init($plugin_file) {
        $puc_path = MC_OUTLET_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
        if (!file_exists($puc_path)) {
            return;
        }

        require_once $puc_path;

        $github_repo = get_option('mc_github_repo', 'mistercoffee/mister-coffee-outlet-finder');
        $github_token = get_option('mc_github_token', '');

        if (empty($github_repo)) {
            return;
        }

        // Format repository URL
        if (strpos($github_repo, 'http') === false) {
            $repo_url = 'https://github.com/' . trim($github_repo, '/');
        } else {
            $repo_url = $github_repo;
        }

        try {
            self::$updateChecker = PucFactory::buildUpdateChecker(
                $repo_url,
                $plugin_file,
                'mister-coffee-outlet-finder'
            );

            // Set main branch
            self::$updateChecker->setBranch('main');

            // Optional authentication token for private repository
            if (!empty($github_token)) {
                self::$updateChecker->setAuthentication($github_token);
            }

            // Enable Release assets support (matches GitHub Release zip files)
            if (method_exists(self::$updateChecker->getVcsApi(), 'enableReleaseAssets')) {
                self::$updateChecker->getVcsApi()->enableReleaseAssets();
            }
        } catch (\Exception $e) {
            error_log('Mister Coffee PUC error: ' . $e->getMessage());
        }

        // AJAX handler for manual check button in settings
        add_action('wp_ajax_mc_check_github_update', [__CLASS__, 'ajax_check_update']);
    }

    public static function get_checker() {
        return self::$updateChecker;
    }

    /**
     * AJAX handler for 1-click update check in settings
     */
    public static function ajax_check_update() {
        check_ajax_referer('mc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (!self::$updateChecker) {
            wp_send_json_error('Update checker is not initialized. Please verify your GitHub repository in Settings.');
        }

        $update = self::$updateChecker->requestUpdate();

        if ($update !== null && version_compare(MC_OUTLET_VERSION, $update->version, '<')) {
            wp_send_json_success([
                'current_version' => MC_OUTLET_VERSION,
                'latest_version' => $update->version,
                'has_update' => true,
                'release_name' => 'v' . $update->version,
                'release_url' => $update->details_url ?? ('https://github.com/' . get_option('mc_github_repo')),
                'published_at' => !empty($update->last_updated) ? date('M j, Y', strtotime($update->last_updated)) : 'Recently',
                'changelog' => $update->upgrade_notice ?? ''
            ]);
        } else {
            wp_send_json_success([
                'current_version' => MC_OUTLET_VERSION,
                'latest_version' => MC_OUTLET_VERSION,
                'has_update' => false,
                'release_name' => 'v' . MC_OUTLET_VERSION,
                'release_url' => 'https://github.com/' . get_option('mc_github_repo'),
                'published_at' => date('M j, Y'),
                'changelog' => ''
            ]);
        }
    }
}
