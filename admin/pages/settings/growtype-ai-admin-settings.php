<?php

class Growtype_Ai_Admin_Settings
{
    public function __construct()
    {
        $this->load_tabs();

        add_action('admin_menu', array ($this, 'admin_menu_pages'));

        add_action('wp_loaded', array ($this, 'process_posted_data'));
    }

    /**
     * Register the options page with the Wordpress menu.
     */
    function admin_menu_pages()
    {
        /**
         * Options
         */
        add_submenu_page(
            'growtype-ai',
            'Settings',
            'Settings',
            'manage_options',
            Growtype_Ai_Admin::SETTINGS_PAGE_NAME,
            array ($this, 'options_page_content'),
            100
        );
    }

    /**
     * @return void
     */
    function options_page_content()
    {
        if (isset($_GET['page']) && $_GET['page'] == Growtype_Ai_Admin::SETTINGS_PAGE_NAME) { ?>

            <div class="wrap">

                <h1>Growtype AI - settings</h1>

                <?php
                if (isset($_GET['updated']) && 'true' == esc_attr($_GET['updated'])) {
                    echo '<div class="updated" ><p>Theme Settings Updated.</p></div>';
                }

                if (isset ($_GET['tab'])) {
                    $this->render_settings_tab_render($_GET['tab']);
                } else {
                    $this->render_settings_tab_render();
                }
                ?>

                <form method="post" action="options.php">
                    <?php

                    if (isset ($_GET['tab'])) {
                        $tab = $_GET['tab'];
                    } else {
                        $tab = Growtype_Ai_Admin::SETTINGS_DEFAULT_TAB;
                    }

                    switch ($tab) {
                        case 'general':
                            settings_fields('growtype_ai_settings_general');

                            echo '<h3>Image generating settings</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Ai_Admin::SETTINGS_PAGE_NAME, 'growtype_ai_image_generating_settings');
                            echo '</table>';

                            break;
                        case 'openai':
                            settings_fields('growtype_ai_settings_openai');

                            echo '<h3>Openai settings</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Ai_Admin::SETTINGS_PAGE_NAME, 'growtype_ai_openai_settings');
                            echo '</table>';

                            break;
                        case 'leonardo':
                            settings_fields('growtype_ai_settings_leonardo');

                            echo '<h3>Leonardo AI settings</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Ai_Admin::SETTINGS_PAGE_NAME, 'growtype_ai_leonardoai_settings');
                            echo '</table>';

                            break;
                        case 'cloudinary':
                            settings_fields('growtype_ai_settings_cloudinary');

                            echo '<h3>Cloudinary settings</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Ai_Admin::SETTINGS_PAGE_NAME, 'growtype_ai_cloudinary_settings');
                            echo '</table>';

                            break;
                        case 'replicate':
                            settings_fields('growtype_ai_settings_replicate');

                            echo '<h3>Replicate settings</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Ai_Admin::SETTINGS_PAGE_NAME, 'growtype_ai_replicate_settings');
                            echo '</table>';

                            break;
                        case 'tinypng':
                            settings_fields('growtype_ai_settings_tinypng');

                            echo '<h3>TinyPng settings</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Ai_Admin::SETTINGS_PAGE_NAME, 'growtype_ai_tinypng_settings');
                            echo '</table>';

                            break;
                        case 'optimization':
                            settings_fields('growtype_ai_settings_optimization');

                            echo '<h3>Optimize database</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Ai_Admin::SETTINGS_PAGE_NAME, 'growtype_ai_optimization_settings');
                            echo '</table>';

                            break;
                        case 'evaluation':
                            settings_fields('growtype_ai_settings_evaluation');

                            echo '<h3>Evaluate</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields(Growtype_Ai_Admin::SETTINGS_PAGE_NAME, 'growtype_ai_evaluation_settings');
                            echo '</table>';

                            break;
                    }

                    if ($tab === 'optimization') {
                        echo '<input type="hidden" name="growtype_ai_optimization" value="true" />';

                        submit_button('Optimize');
                    } elseif ($tab === 'evaluation') {
                        echo '<input type="hidden" name="growtype_ai_evaluation" value="true" />';

                        submit_button('Evaluate');
                    } else {
                        submit_button();
                    }

                    ?>
                </form>
            </div>

            <?php
        }
    }

    function process_posted_data()
    {
        if (isset($_POST) && !empty($_POST)) {
            if (isset($_POST['growtype_ai_optimization'])) {
                if (isset($_POST['growtype_ai_optimization_clean_duplicate_settings'])) {
                    Growtype_Ai_Database_Optimize::clean_duplicate_settings();
                }

                if (isset($_POST['growtype_ai_optimization_clean_duplicate_images'])) {
                    Growtype_Ai_Database_Optimize::clean_duplicate_images();
                }

                if (isset($_POST['growtype_ai_optimization_sync_local_images'])) {
                    growtype_ai_init_job('generate-model-content', json_encode([
                        'action' => 'sync-local-images'
                    ]), 30);
                }

                if (isset($_POST['growtype_ai_optimization_sync_models'])) {
                    Growtype_Ai_Database_Optimize::sync_models();
                }

                if (isset($_POST['growtype_ai_optimization_optimize_all_images'])) {
                    Growtype_Ai_Database_Optimize::optimize_all_images();
                }

                if (isset($_POST['growtype_ai_optimization_get_images_colors'])) {
                    Growtype_Ai_Database_Optimize::get_images_colors();
                }

                if (isset($_POST['growtype_ai_optimization_model_assign_categories'])) {
                    Growtype_Ai_Database_Optimize::model_assign_categories();
                }

                wp_redirect(admin_url('admin.php?page=growtype-ai-settings&tab=optimization&updated=true'));
                exit();
            }

            if (isset($_POST['growtype_ai_evaluation'])) {
                if (isset($_POST['growtype_ai_evaluation_image_colors'])) {
                    $image_id = $_POST['growtype_ai_evaluation_image_colors'];
                    echo Extract_Image_Colors_Job::get_image_details($image_id, true);
                    die();
                }

                wp_redirect(admin_url('admin.php?page=growtype-ai-settings&tab=evaluation&updated=true'));
                exit();
            }
        }
    }

    function settings_tabs()
    {
        return apply_filters('growtype_ai_admin_settings_tabs', []);
    }

    function render_settings_tab_render($current = Growtype_Ai_Admin::SETTINGS_DEFAULT_TAB)
    {
        $tabs = $this->settings_tabs();

        echo '<div id="icon-themes" class="icon32"><br></div>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $tab => $name) {
            $class = ($tab == $current) ? ' nav-tab-active' : '';
            echo "<a class='nav-tab$class' href='?page=growtype-ai-settings&tab=$tab'>$name</a>";

        }
        echo '</h2>';
    }

    public function load_tabs()
    {
        /**
         * Image generating settings
         */
        include_once GROWTYPE_AI_PATH . 'admin/pages/settings/tabs/GeneralSettings.php';
        new GeneralSettings();

        /**
         * Leonardo ai settings
         */
        include_once GROWTYPE_AI_PATH . 'admin/pages/settings/tabs/LeonardoAiSettings.php';
        new LeonardoAiSettings();

        /**
         * Cloudinary settings
         */
        include_once GROWTYPE_AI_PATH . 'admin/pages/settings/tabs/CloudinarySettings.php';
        new CloudinarySettings();

        /**
         * Openai
         */
        include_once GROWTYPE_AI_PATH . 'admin/pages/settings/tabs/OpenaiSettings.php';
        new OpenaiSettings();

        /**
         * Replicate
         */
        include_once GROWTYPE_AI_PATH . 'admin/pages/settings/tabs/ReplicateSettings.php';
        new ReplicateSettings();

        /**
         * Tinypng
         */
        include_once GROWTYPE_AI_PATH . 'admin/pages/settings/tabs/TinyPngSettings.php';
        new TinyPngSettings();

        /**
         * OptimizationSettings
         */
        include_once GROWTYPE_AI_PATH . 'admin/pages/settings/tabs/OptimizationSettings.php';
        new OptimizationSettings();

        /**
         * EvaluationSettings
         */
        include_once GROWTYPE_AI_PATH . 'admin/pages/settings/tabs/EvaluationSettings.php';
        new EvaluationSettings();
    }
}
