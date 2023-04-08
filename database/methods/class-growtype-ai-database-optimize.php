<?php

class Growtype_Ai_Database_Optimize
{
    public static function clean_duplicate_settings()
    {
        global $wpdb;

        $records = $wpdb->get_results("SELECT * FROM wp_growtype_ai_model_settings", ARRAY_A);

        $filtered_records = [];
        foreach ($records as $record) {
            if (!isset($filtered_records[$record['model_id']][$record['meta_key']])) {
                $filtered_records[$record['model_id']][$record['meta_key']] = $record;
            } else {
                $wpdb->delete('wp_growtype_ai_model_settings', array ('id' => $record['id']));
            }
        }

        $records = $wpdb->get_results("SELECT * FROM wp_growtype_ai_image_settings", ARRAY_A);

        $filtered_records = [];
        foreach ($records as $record) {
            if (!isset($filtered_records[$record['image_id']][$record['meta_key']])) {
                $filtered_records[$record['image_id']][$record['meta_key']] = $record;
            } else {
                $wpdb->delete('wp_growtype_ai_image_settings', array ('id' => $record['id']));
            }
        }
    }

    public static function sync_models()
    {
        error_log('optimize: sync_models');

        $images = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::IMAGES_TABLE);

        foreach ($images as $image) {
            $model_image = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODEL_IMAGE_TABLE, [
                [
                    'key' => 'image_id',
                    'values' => [$image['id']],
                ]
            ]);

            if (empty($model_image)) {

                $model = Growtype_Ai_Database_Crud::get_single_record(Growtype_Ai_Database::MODELS_TABLE, [
                    [
                        'key' => 'image_folder',
                        'values' => [$image['folder']],
                    ]
                ]);

                if (!empty($model)) {
                    Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODEL_IMAGE_TABLE, [
                        'model_id' => $model['id'],
                        'image_id' => $image['id'],
                    ]);
                } else {
                    d('empty model');
                }
            }
        }
    }

    public static function clean_duplicate_images($generator = 'leonardoai')
    {
        /**
         * Remove duplicates
         */
        $images = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::IMAGES_TABLE);

        $unique_images = [];
        foreach ($images as $image) {

            $uniqeu_key = $image['name'] . $image['folder'];

            if (!isset($unique_images[$uniqeu_key])) {
                $unique_images[$uniqeu_key] = $image;
            } else {
                $image_details = growtype_ai_get_image_details($image['id']);

                if (empty($image_details['settings'])) {
                    Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::IMAGES_TABLE, [$image['id']]);
                    continue;
                } else {
                    Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::IMAGES_TABLE, [$unique_images[$uniqeu_key]['id']]);
                    continue;
                }
            }

            $image_url = growtype_ai_get_upload_dir() . '/' . $image['folder'] . '/' . $image['name'] . '.' . $image['extension'];

            if (!file_exists($image_url)) {
                $alternative_extension = $image['extension'] === 'jpg' ? 'png' : 'jpg';
                $image_url = growtype_ai_get_upload_dir() . '/' . $image['folder'] . '/' . $image['name'] . '.' . $alternative_extension;

                if (file_exists($image_url)) {

                    $image = growtype_ai_get_image_details($image['id']);

                    if (!empty($image)) {

                        Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::IMAGES_TABLE, [
                            'extension' => $alternative_extension
                        ], $image['id']);

                        continue;
                    }
                }

                Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::IMAGES_TABLE, [$image['id']]);
            }
        }

        return count($unique_images);
    }

    public static function sync_local_images($generator = 'leonardoai')
    {
        error_log('Syncing local images...');

        /**
         * Uploaded existing images in folders
         */
        $upload_dir = growtype_ai_get_upload_dir() . '/' . $generator;
        $folders = glob($upload_dir . '/*', GLOB_ONLYDIR);

//        $folders = array_slice($folders, 100, 150);

        $existing_images_amount = 0;
        foreach ($folders as $folder) {

            error_log('Syncing folder: ' . $folder);

            $folder_images = glob($folder . '/*');

            $existing_images_amount += count($folder_images);

            foreach ($folder_images as $folder_image) {
                $img_path = str_replace(growtype_ai_get_upload_dir() . '/', '', $folder_image);
                $img_full_name = substr(strrchr($img_path, '/'), 1);
                $img_folder = str_replace('/' . $img_full_name, '', $img_path);
                $img_name = explode('.', $img_full_name)[0];
                $img_ext = explode('.', $img_full_name)[1];

                $image = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::IMAGES_TABLE, [
                    [
                        'key' => 'name',
                        'value' => $img_name,
                    ],
                    [
                        'key' => 'folder',
                        'value' => $img_folder,
                    ]
                ], 'where');

                if (empty($image)) {

                    $size = getimagesize($folder_image);

                    Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGES_TABLE, [
                        'name' => $img_name,
                        'extension' => $img_ext,
                        'width' => $size[0],
                        'height' => $size[1],
                        'location' => 'locally',
                        'folder' => $img_folder
                    ]);
                }
            }

            error_log('Images amount: ' . $existing_images_amount);
        }

        return $existing_images_amount;
    }

    public static function optimize_all_images($generator = 'leonardoai')
    {
        error_log('Optimizing local images...');

        /**
         * Uploaded existing images in folders
         */
        $upload_dir = growtype_ai_get_upload_dir() . '/' . $generator;
        $folders = glob($upload_dir . '/*', GLOB_ONLYDIR);

//        $folders = array_slice($folders, 0, 500);

        foreach ($folders as $folder) {

            $folder_images = glob($folder . '/*');

            if (empty($folder_images)) {
                continue;
            }

            $delay = 5;
            foreach ($folder_images as $folder_image) {
                $size = getimagesize($folder_image);

                $max_width = 650;

                if ($size[0] < 650) {
                    growtype_ai_init_job('upscale-image-local', json_encode([
                        'path' => $folder_image,
                        'max_width' => $max_width,
                    ]), $delay += 1);
                }
            }
        }
    }

    public static function get_images_colors()
    {
        $images = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::IMAGES_TABLE);
        $images = array_slice($images, 5000, 10000);

//        $images = growtype_ai_get_model_images(356);

        foreach ($images as $image) {

//            $colors = Extract_Image_Colors_Job::get_image_colors_groups(22739);
//////
//            d($colors);

//            Extract_Image_Colors_Job::update_image_colors_groups($image['id']);

            growtype_ai_init_job('extract-image-colors', json_encode([
                'image_id' => $image['id']
            ]), 1);
        }
    }

    public static function model_assign_categories()
    {
        $models = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODELS_TABLE);

        foreach ($models as $model) {
            growtype_ai_init_job('model-assign-categories', json_encode([
                'model_id' => $model['id']
            ]), 1);
        }
    }
}
