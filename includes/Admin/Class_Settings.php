<?php

declare(strict_types=1);

class PLD_Admin_Settings {

    /** @var string */
    private $option_name;

    public function __construct(string $option_name) {
        $this->option_name = $option_name;
    }

    public function register(): void {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_settings_page(): void {
        add_options_page(
            __('Public Preview Links', 'public-links-drafts'),
            __('Public Preview Links', 'public-links-drafts'),
            'manage_options',
            'pld-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void {
        register_setting('pld_settings', $this->option_name, [$this, 'sanitize_options']);

        add_settings_section(
            'pld_main',
            '',
            '__return_false',
            'pld-settings'
        );

        add_settings_field(
            'pld_post_types',
            __('Enabled post types', 'public-links-drafts'),
            [$this, 'render_post_types_field'],
            'pld-settings',
            'pld_main'
        );

        add_settings_field(
            'pld_statuses',
            __('Allowed statuses', 'public-links-drafts'),
            [$this, 'render_statuses_field'],
            'pld-settings',
            'pld_main'
        );

        add_settings_field(
            'pld_max_days',
            __('Link expiry (days)', 'public-links-drafts'),
            [$this, 'render_max_days_field'],
            'pld-settings',
            'pld_main'
        );
    }

    public function render_settings_page(): void {
        if (! current_user_can('manage_options')) {
            return;
        }
?>
        <div class="wrap">
            <h1><?php echo esc_html__('Public Preview Links', 'public-links-drafts'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('pld_settings');
                do_settings_sections('pld-settings');
                submit_button();
                ?>
            </form>
        </div>
<?php
    }

    public function get_default_options(): array {
        return [
            'post_types' => [
                'post' => true,
                'page' => true,
            ],
            'statuses' => [
                'draft' => true,
                'pending' => true,
            ],
            'max_days' => 3,
        ];
    }

    public function get_options(): array {
        $defaults = $this->get_default_options();
        $saved    = get_option($this->option_name, []);
        if (! is_array($saved)) {
            $saved = [];
        }
        return array_replace_recursive($defaults, $saved);
    }

    public function render_post_types_field(): void {
        $options = $this->get_options();
        $chosen  = isset($options['post_types']) && is_array($options['post_types']) ? $options['post_types'] : [];
        $post_types = get_post_types([], 'objects');
        foreach ($post_types as $post_type) {
            if (! is_post_type_viewable($post_type) || 'attachment' === $post_type->name) {
                continue;
            }
            $checked = ! empty($chosen[$post_type->name]);
            printf(
                '<label style="display:block;margin:4px 0;"><input type="checkbox" name="%1$s[post_types][%2$s]" value="1" %3$s /> %4$s <span class="description" style="opacity:.7">(%2$s)</span></label>',
                esc_attr($this->option_name),
                esc_attr($post_type->name),
                checked($checked, true, false),
                esc_html($post_type->labels->name)
            );
        }
    }

    public function render_statuses_field(): void {
        $options  = $this->get_options();
        $chosen   = isset($options['statuses']) && is_array($options['statuses']) ? $options['statuses'] : [];
        $statuses = [
            'draft'   => __('Draft', 'public-links-drafts'),
            'pending' => __('Pending Review', 'public-links-drafts'),
            'future'  => __('Scheduled', 'public-links-drafts'),
        ];
        foreach ($statuses as $status => $label) {
            $checked = ! empty($chosen[$status]);
            printf(
                '<label style="display:inline-block;margin:0 16px 8px 0;"><input type="checkbox" name="%1$s[statuses][%2$s]" value="1" %3$s /> %4$s</label>',
                esc_attr($this->option_name),
                esc_attr($status),
                checked($checked, true, false),
                esc_html($label)
            );
        }
        echo '<p class="description">' . esc_html__('Choose which non-published statuses are allowed for public previews.', 'public-links-drafts') . '</p>';
    }

    public function render_max_days_field(): void {
        $options  = $this->get_options();
        $max_days = isset($options['max_days']) ? (int) $options['max_days'] : 3;
        $max_days = max(1, min(365, $max_days));
        printf(
            '<input type="number" name="%1$s[max_days]" value="%2$s" min="1" max="365" class="small-text" /> %3$s',
            esc_attr($this->option_name),
            esc_attr((string) $max_days),
            esc_html__('days', 'public-links-drafts')
        );
    }

    public function sanitize_options($input): array {
        $defaults = $this->get_default_options();
        $output   = ['post_types' => [], 'statuses' => [], 'max_days' => 3];

        if (isset($input['post_types']) && is_array($input['post_types'])) {
            $post_types = get_post_types([], 'objects');
            foreach ($post_types as $post_type) {
                if (! is_post_type_viewable($post_type) || 'attachment' === $post_type->name) {
                    continue;
                }
                $output['post_types'][$post_type->name] = ! empty($input['post_types'][$post_type->name]);
            }
        } else {
            $output['post_types'] = $defaults['post_types'];
        }

        $allowed_statuses = ['draft', 'pending', 'future'];
        if (isset($input['statuses']) && is_array($input['statuses'])) {
            foreach ($allowed_statuses as $status) {
                $output['statuses'][$status] = ! empty($input['statuses'][$status]);
            }
        } else {
            $output['statuses'] = $defaults['statuses'];
        }

        $max_days = isset($input['max_days']) ? (int) $input['max_days'] : $defaults['max_days'];
        $output['max_days'] = max(1, min(365, $max_days));

        return $output;
    }
}
