<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    growtype_quiz
 * @subpackage growtype_quiz/admin/partials
 */

class LeonardoAiSettings
{
    public function __construct()
    {
        add_action('admin_init', array ($this, 'admin_settings'));

        add_filter('growtype_ai_admin_settings_tabs', array ($this, 'settings_tab'));
    }

    function settings_tab($tabs)
    {
        $tabs['leonardo'] = 'Leonardo AI';

        return $tabs;
    }

    function admin_settings()
    {
        $access_settings = $this->get_access_settings();

        foreach ($access_settings as $settings_group) {
            foreach ($settings_group as $setting) {
                /**
                 *
                 */
                register_setting(
                    'growtype_ai_settings_leonardo',
                    $setting['key']
                );

                add_settings_field(
                    $setting['key'],
                    $setting['label'],
                    array (
                        $this,
                        'render_field',
                    ),
                    Growtype_Ai_Admin::SETTINGS_PAGE_NAME,
                    'growtype_ai_leonardoai_settings',
                    $setting
                );
            }
        }
    }

    public function get_access_settings()
    {
        return [
            [
                [
                    'key' => 'growtype_ai_leonardo_access_key',
                    'label' => 'Leonardo AI - Session Cookie',
                    'type' => 'textarea'
                ],
                [
                    'key' => 'growtype_ai_leonardo_access_token',
                    'label' => 'Leonardo AI - User Token',
                    'type' => 'textarea'
                ],
                [
                    'key' => 'growtype_ai_leonardo_user_id',
                    'label' => 'Leonardo AI - User ID'
                ]
            ],
            [
                [
                    'key' => 'growtype_ai_leonardo_access_key_2',
                    'label' => 'Leonardo AI - Session Cookie 2',
                    'type' => 'textarea'
                ],
                [
                    'key' => 'growtype_ai_leonardo_access_token_2',
                    'label' => 'Leonardo AI - User Token 2',
                    'type' => 'textarea'
                ],
                [
                    'key' => 'growtype_ai_leonardo_user_id_2',
                    'label' => 'Leonardo AI - User ID 2'
                ]
            ],
            [
                [
                    'key' => 'growtype_ai_leonardo_access_key_3',
                    'label' => 'Leonardo AI - Session Cookie 3',
                    'type' => 'textarea'
                ],
                [
                    'key' => 'growtype_ai_leonardo_access_token_3',
                    'label' => 'Leonardo AI - User Token 3',
                    'type' => 'textarea'
                ],
                [
                    'key' => 'growtype_ai_leonardo_user_id_3',
                    'label' => 'Leonardo AI - User ID 3'
                ]
            ]
        ];
    }

    function render_field($setting)
    {
        $value = get_option($setting['key']);

        if (isset($setting['type']) && $setting['type'] === 'textarea') {
            ?>
            <textarea type="text" rows="10" class="large-text code" name="<?php echo $setting['key'] ?>" value="<?php echo $value ?>"><?php echo $value ?></textarea>
            <?php
        } else {
            ?>
            <input type="text" class="regular-text ltr" name="<?php echo $setting['key'] ?>" value="<?php echo $value ?>"/>
            <?php
        }
    }
}


