<?php

class Growtype_Ai_Cron
{
    const GROWTYPE_AI_JOBS_CRON = 'growtype_ai_jobs';
    const GROWTYPE_AI_BUNDLE_JOBS_CRON = 'growtype_ai_bundle_jobs';

    const  RETRIEVE_JOBS_LIMIT = 2;
    const  JOBS_ATTEMPTS_LIMIT = 3;

    const TEST_CRON = false;

    public function __construct()
    {
        add_action(self::GROWTYPE_AI_JOBS_CRON, array ($this, 'process_jobs'));

        add_action(self::GROWTYPE_AI_BUNDLE_JOBS_CRON, array ($this, 'generate_random_jobs'));

        add_filter('cron_schedules', array ($this, 'cron_custom_intervals'));

        add_action('wp_loaded', array (
            $this,
            'cron_activation'
        ));

        $this->load_jobs();

        if (self::TEST_CRON) {
            $this->process_jobs();
        }
    }

    /**
     * Load the required traits for this plugin.
     */
    private function load_jobs()
    {
        /**
         * Frontend traits
         */
        spl_autoload_register(function ($name) {
            $fileName = GROWTYPE_AI_PATH . 'includes/methods/cron/jobs/' . $name . '.php';

            if (file_exists($fileName)) {
                include $fileName;
            }
        });
    }

    function cron_custom_intervals()
    {
        $schedules['every10seconds'] = array (
            'interval' => 10,
            'display' => __('Once Every 10 seconds')
        );

        $schedules['every20seconds'] = array (
            'interval' => 20,
            'display' => __('Once Every 20 seconds')
        );

        $schedules['every30seconds'] = array (
            'interval' => 30,
            'display' => __('Once Every 30 seconds')
        );

        $schedules['everyminute'] = array (
            'interval' => 60,
            'display' => __('Once Every Minute')
        );

        $schedules['every5minute'] = array (
            'interval' => 60 * 5,
            'display' => __('Once Every 5 Minute')
        );

        $schedules['every10minute'] = array (
            'interval' => 60 * 10,
            'display' => __('Once Every 10 Minute')
        );

        $schedules['every30minute'] = array (
            'interval' => 60 * 30,
            'display' => __('Once Every 30 Minute')
        );

        return $schedules;
    }

    function cron_activation()
    {
        if (!wp_next_scheduled(self::GROWTYPE_AI_JOBS_CRON)) {
            wp_schedule_event(time(), 'every10seconds', self::GROWTYPE_AI_JOBS_CRON);
        }

        if (!wp_next_scheduled(self::GROWTYPE_AI_BUNDLE_JOBS_CRON)) {
            wp_schedule_event(time(), 'every10minute', self::GROWTYPE_AI_BUNDLE_JOBS_CRON);
        }
    }

    function process_jobs()
    {
        $jobs = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODEL_JOBS_TABLE);

        foreach ($jobs as $job) {
            $job_date = $job['available_at'];
            $job_payload = json_decode($job['payload'], true);

            if ($job_date > wp_date('Y-m-d H:i:s')) {
                continue;
            }

            /**
             * Check if new job is available
             */
            if (!$this->new_generate_job_is_available($job['queue'])) {

                error_log("Job - not available - " . $job['id'], 0);

                continue;
            }

            /**
             * Limit attempts
             */
            if ((int)$job['attempts'] > self::JOBS_ATTEMPTS_LIMIT - 1) {
                continue;
            }

            /**
             * If already reserved, skip
             */
            if ((int)$job['reserved'] === 1) {
                continue;
            }

            if (!self::TEST_CRON) {
                Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                    'reserved' => 1,
                    'attempts' => (int)$job['attempts'] + 1,
                ], $job['id']);
            }

            $this->init_job($job);
        }
    }

    function init_job($job)
    {
        try {
            error_log('job started - ' . $job['id'], 0);

            $jobs = [
                'extract-image-colors' => new Extract_Image_Colors_Job(),
                'generate-image-content' => new Generate_Image_Content_Job(),
                'optimize-database' => new Optimize_Database_Job(),
                'retrieve-model' => new Retrieve_Model_Job(),
                'upscale-image' => new Upscale_Image_Job(),
                'upscale-image-local' => new Upscale_Image_Local_Job(),
                'retrieve-upscale-image' => new Retrieve_Upscale_Image_Job(),
                'generate-model-content' => new Generate_Model_Content_Job(),
                'generate-model' => new Generate_Model_Job(),
                'download-model-images' => new Download_Model_Images_Job(),
                'download-cloudinary-folder' => new Download_Cloudinary_Folder_Job(),
                'model-assign-categories' => new Model_Assign_Categories_Job(),
            ];

            if (!isset($jobs[$job['queue']])) {
                throw new Exception('No job class registered');
            }

            /**
             * Run job
             */
            $jobs[$job['queue']]->run(json_decode($job['payload'], true));

            /**
             * Delete job
             */
            Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::MODEL_JOBS_TABLE, [$job['id']]);
        } catch (Exception $e) {
            Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                'exception' => $e->getMessage(),
                'reserved' => 0
            ], $job['id']);
        }
    }

    function new_generate_job_is_available($queue)
    {
        $retrieve_jobs = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
            [
                'key' => 'queue',
                'values' => [$queue],
            ]
        ]);

        $reserved_jobs = [];
        foreach ($retrieve_jobs as $job) {
            if ($job['reserved']) {
                array_push($reserved_jobs, $job['id']);
            }
        }

        $active_jobs = count($reserved_jobs);

        /**
         * Do not generate more than 5 retrieve jobs at the same time
         */
        if ($active_jobs >= self::RETRIEVE_JOBS_LIMIT) {
            return false;
        }

        return true;
    }

    function generate_random_jobs()
    {
        $models = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODELS_TABLE);
        $bundle_ids = explode(',', get_option('growtype_ai_bundle_ids'));

        if (empty($bundle_ids)) {
            return;
        }

        foreach ($models as $model) {
            if (!in_array($model['id'], $bundle_ids)) {
                continue;
            }

            growtype_ai_init_job('generate-model', json_encode(['model_id' => $model['id']]));

            sleep(5);
        }
    }
}
