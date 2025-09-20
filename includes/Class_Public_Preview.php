<?php

declare(strict_types=1);

class PLD_Public_Preview {

    /** @var string */
    private $option_name;

    public function __construct(string $option_name) {
        $this->option_name = $option_name;
    }

    public function register(): void {
        if (is_admin()) {
            add_filter('page_row_actions', [$this, 'add_action_row_link'], 10, 2);
            add_filter('post_row_actions', [$this, 'add_action_row_link'], 10, 2);
            add_action('post_submitbox_misc_actions', [$this, 'add_classic_editor_button']);
            add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
            add_action('save_post', [$this, 'save_classic_meta'], 20, 2);
            add_action('wp_ajax_pld_toggle_public_preview', [$this, 'ajax_toggle_public_preview']);
            add_action('transition_post_status', [$this, 'unregister_on_status_change'], 20, 3);
            add_action('post_updated', [$this, 'unregister_on_edit'], 20, 2);
        } else {
            add_filter('query_vars', [$this, 'add_query_var']);
            add_action('pre_get_posts', [$this, 'show_public_preview']);
        }
    }

    private function get_defaults(): array {
        return [
            'post_types' => ['post' => true, 'page' => true],
            'statuses'   => ['draft' => true, 'pending' => true],
            'max_days'   => 3,
        ];
    }

    private function get_options(): array {
        $defaults = $this->get_defaults();
        $saved    = get_option($this->option_name, []);
        if (! is_array($saved)) {
            $saved = [];
        }
        return array_replace_recursive($defaults, $saved);
    }

    private function enabled_post_types(): array {
        $options = $this->get_options();
        $post_types = [];
        if (! empty($options['post_types']) && is_array($options['post_types'])) {
            foreach ($options['post_types'] as $slug => $enabled) {
                if ($enabled) {
                    $post_types[] = $slug;
                }
            }
        }
        return $post_types;
    }

    private function allowed_statuses(): array {
        $options  = $this->get_options();
        $allowed  = [];
        $statuses = ['draft', 'pending', 'future'];
        foreach ($statuses as $st) {
            if (! empty($options['statuses'][$st])) {
                $allowed[] = $st;
            }
        }
        return $allowed;
    }

    private function is_post_type_enabled(string $post_type): bool {
        return in_array($post_type, $this->enabled_post_types(), true);
    }

    private function is_status_allowed(string $status): bool {
        // Exclude published/private/trash etc.
        if (in_array($status, ['publish', 'private', 'trash', 'inherit', 'auto-draft'], true)) {
            return false;
        }
        return in_array($status, $this->allowed_statuses(), true);
    }

    private function get_enabled_post_ids(): array {
        $ids = get_option('pld_enabled_posts', []);
        if (! is_array($ids)) {
            $ids = [];
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function set_enabled_post_ids(array $ids): bool {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        return update_option('pld_enabled_posts', $ids);
    }

    private function is_post_enabled(int $post_id): bool {
        return in_array($post_id, $this->get_enabled_post_ids(), true);
    }

    public function add_query_var(array $vars): array {
        $vars[] = 'pld';
        return $vars;
    }

    public function show_public_preview(WP_Query $query): void {
        if ($query->is_main_query() && $query->is_preview() && $query->is_singular() && $query->get('pld')) {
            if (! headers_sent()) {
                nocache_headers();
                header('X-Robots-Tag: noindex');
            }
            if (function_exists('wp_robots_no_robots')) {
                add_filter('wp_robots', 'wp_robots_no_robots');
            } else {
                add_action('wp_head', 'wp_no_robots');
            }
            add_filter('posts_results', [$this, 'set_post_to_publish'], 10, 2);
        }
    }

    public function set_post_to_publish(array $posts, WP_Query $query) {
        remove_filter('posts_results', [$this, 'set_post_to_publish'], 10);
        if (empty($posts)) {
            return $posts;
        }
        $post_id = (int) $posts[0]->ID;

        $this->maybe_redirect_to_published_post($post_id);

        $nonce = get_query_var('pld');
        if (! $this->verify_nonce(is_string($nonce) ? $nonce : '', 'pld_' . $post_id)) {
            wp_die(
                esc_html__('This link has expired or is invalid!', 'public-links-drafts'),
                esc_html__('Public Preview', 'public-links-drafts'),
                array('response' => 403)
            );
        }

        // Check post type and status are allowed and post is enabled
        if (! $this->is_post_type_enabled($posts[0]->post_type) || ! $this->is_status_allowed($posts[0]->post_status) || ! $this->is_post_enabled($post_id)) {
            wp_die(
                esc_html__('Public preview is not available for this content.', 'public-links-drafts'),
                esc_html__('Public Preview', 'public-links-drafts'),
                array('response' => 403)
            );
        }

        if ('publish' !== $posts[0]->post_status) {
            $posts[0]->post_status = 'publish';
            add_filter('comments_open', '__return_false');
            add_filter('pings_open', '__return_false');
            add_filter('wp_link_pages_link', [$this, 'filter_wp_link_pages_link'], 10, 2);
        }
        return $posts;
    }

    public function filter_wp_link_pages_link(string $link, int $page_number): string {
        $post = get_post();
        if (! $post) {
            return $link;
        }
        $preview_link = $this->get_preview_link($post);
        $preview_link = add_query_arg('page', $page_number, $preview_link);
        return preg_replace('~href=(["|\'])(.+?)\1~', 'href=$1' . esc_url($preview_link) . '$1', $link);
    }

    private function maybe_redirect_to_published_post(int $post_id): void {
        if (in_array(get_post_status($post_id), ['publish', 'private'], true)) {
            wp_safe_redirect(get_permalink($post_id), 301);
            exit;
        }
    }

    public function get_preview_link(WP_Post $post): string {
        if ('page' === $post->post_type) {
            $args = ['page_id' => $post->ID];
        } elseif ('post' === $post->post_type) {
            $args = ['p' => $post->ID];
        } else {
            $args = ['p' => $post->ID, 'post_type' => $post->post_type];
        }
        $args['preview'] = 'true';
        $args['pld']     = $this->create_nonce('pld_' . $post->ID);
        return add_query_arg($args, home_url('/'));
    }

    private function nonce_tick(): int {
        $options  = $this->get_options();
        $max_days = isset($options['max_days']) ? (int) $options['max_days'] : 3;
        $max_days = max(1, min(365, $max_days));
        $nonce_life = $max_days * DAY_IN_SECONDS;
        return (int) ceil(time() / ($nonce_life / 2));
    }

    private function create_nonce($action = -1): string {
        $i = $this->nonce_tick();
        return substr(wp_hash($i . $action, 'nonce'), -12, 10);
    }

    private function verify_nonce(string $nonce, $action = -1) {
        $i = $this->nonce_tick();
        if (substr(wp_hash($i . $action, 'nonce'), -12, 10) === $nonce) {
            return 1;
        }
        if (substr(wp_hash(($i - 1) . $action, 'nonce'), -12, 10) === $nonce) {
            return 2;
        }
        return false;
    }

    public function add_action_row_link(array $actions, WP_Post $post): array {
        if ($this->is_post_type_enabled($post->post_type) && $this->is_status_allowed($post->post_status) && $this->is_post_enabled((int) $post->ID)) {
            $link = $this->get_preview_link($post);
            $actions['pld-public-preview'] = '<a href="' . esc_url($link) . '" title="' . esc_attr__('Public preview link', 'public-links-drafts') . '">' . esc_html__('Public Preview', 'public-links-drafts') . '</a>';
        }
        return $actions;
    }

    public function add_classic_editor_button(): void {
        global $post;
        if (! ($post instanceof WP_Post)) {
            return;
        }
        if ($this->is_post_type_enabled($post->post_type) && $this->is_status_allowed($post->post_status)) {
            $enabled = $this->is_post_enabled((int) $post->ID);
            $link    = $this->get_preview_link($post);
            wp_nonce_field('pld_public_preview_' . $post->ID, 'pld_public_preview_wpnonce');
            echo '<div class="misc-pub-section public-post-preview">';
            echo '<label><input type="checkbox" name="pld_public_preview" id="pld-public-preview" value="1" ' . checked($enabled, true, false) . ' /> ' . esc_html__('Enable public preview', 'public-links-drafts') . ' <span id="pld-public-preview-ajax"></span></label>';
            echo '<div id="pld-public-preview-link" style="margin-top:6px' . ($enabled ? '' : ';display:none') . '">';
            echo '<label><input type="text" name="pld_public_preview_link" class="regular-text" value="' . esc_attr($enabled ? $link : '') . '" style="width:99%" readonly /></label>';
            echo '</div>';
            echo '</div>';
        }
    }

    public function enqueue_admin_styles(): void {
        wp_enqueue_style('pld-editor-css', PLD_URL . 'assets/css/editor-button.css', [], PLD_VERSION);
    }

    public function enqueue_block_editor_assets(): void {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && ! $screen->is_block_editor()) {
            return;
        }
        $post = get_post();
        if (! ($post instanceof WP_Post)) {
            return;
        }
        if (! $this->is_post_type_enabled($post->post_type) || ! $this->is_status_allowed($post->post_status)) {
            return;
        }

        wp_register_script(
            'pld-editor-js',
            PLD_URL . 'assets/js/editor-button.js',
            ['wp-edit-post', 'wp-plugins', 'wp-components', 'wp-element', 'wp-i18n', 'wp-compose', 'wp-data', 'wp-notices'],
            PLD_VERSION,
            true
        );
        wp_localize_script('pld-editor-js', 'pldEditorData', [
            'link'  => $this->get_preview_link($post),
            'title' => __('Link to preview this content publicly', 'public-links-drafts'),
            'text'  => __('Public Preview', 'public-links-drafts'),
            'copyLabel' => __('Copy the preview URL', 'public-links-drafts'),
            'copiedNotice' => __('Preview link copied to clipboard.', 'public-links-drafts'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'postId' => (int) $post->ID,
            'previewEnabled' => $this->is_post_enabled((int) $post->ID),
            'nonce' => wp_create_nonce('pld_toggle_' . (int) $post->ID),
        ]);
        wp_enqueue_script('pld-editor-js');
    }

    public function save_classic_meta(int $post_id, WP_Post $post): bool {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }
        if (wp_is_post_revision($post_id)) {
            return false;
        }
        if (empty($_POST['pld_public_preview_wpnonce'])) {
            return false;
        }
        $nonce = sanitize_text_field(wp_unslash((string) $_POST['pld_public_preview_wpnonce']));
        if (! wp_verify_nonce($nonce, 'pld_public_preview_' . $post_id)) {
            return false;
        }
        if (! current_user_can('edit_post', $post_id)) {
            return false;
        }
        $ids = $this->get_enabled_post_ids();
        $enabled_raw = isset($_POST['pld_public_preview']) ? sanitize_text_field(wp_unslash((string) $_POST['pld_public_preview'])) : '';
        $enabled = ! empty($enabled_raw);
        if ($enabled && ! in_array($post_id, $ids, true)) {
            $ids[] = $post_id;
        } elseif (! $enabled && in_array($post_id, $ids, true)) {
            $ids = array_values(array_diff($ids, [$post_id]));
        } else {
            return false; // nothing changed
        }
        return $this->set_enabled_post_ids($ids);
    }

    public function unregister_on_status_change(string $new_status, string $old_status, WP_Post $post): bool {
        $disallowed = ['publish', 'private', 'trash'];
        if (in_array($new_status, $disallowed, true)) {
            return $this->unregister_post((int) $post->ID);
        }
        return false;
    }

    public function unregister_on_edit(int $post_id, WP_Post $post): bool {
        $disallowed = ['publish', 'private', 'trash'];
        if (in_array($post->post_status, $disallowed, true)) {
            return $this->unregister_post($post_id);
        }
        return false;
    }

    private function unregister_post(int $post_id): bool {
        $ids = $this->get_enabled_post_ids();
        if (! in_array($post_id, $ids, true)) {
            return false;
        }
        $ids = array_values(array_diff($ids, [$post_id]));
        return $this->set_enabled_post_ids($ids);
    }

    public function ajax_toggle_public_preview(): void {
        if (! isset($_POST['post_ID'], $_POST['checked'], $_POST['_ajax_nonce'])) {
            wp_send_json_error('incomplete_data');
        }
        $post_id = isset($_POST['post_ID']) ? absint(wp_unslash((string) $_POST['post_ID'])) : 0;
        if (! $post_id) {
            wp_send_json_error('invalid_post');
        }
        // Verify nonce first for AJAX requests.
        check_ajax_referer('pld_toggle_' . $post_id);
        $checked = isset($_POST['checked']) ? sanitize_text_field(wp_unslash((string) $_POST['checked'])) : '';
        $post = get_post($post_id);
        if (! $post) {
            wp_send_json_error('invalid_post');
        }
        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error('cannot_edit');
        }
        if (! $this->is_post_type_enabled($post->post_type) || ! $this->is_status_allowed($post->post_status)) {
            wp_send_json_error('not_allowed');
        }
        $ids = $this->get_enabled_post_ids();
        if ('true' === $checked && ! in_array($post_id, $ids, true)) {
            $ids[] = $post_id;
        } elseif ('false' === $checked && in_array($post_id, $ids, true)) {
            $ids = array_values(array_diff($ids, [$post_id]));
        } else {
            wp_send_json_error('unknown_status');
        }
        $saved = $this->set_enabled_post_ids($ids);
        if (! $saved) {
            wp_send_json_error('not_saved');
        }
        $data = null;
        if ('true' === $checked) {
            $data = ['preview_url' => $this->get_preview_link($post)];
        }
        wp_send_json_success($data);
    }
}
