<?php

class Growtype_Ai_Crud
{
    const IMAGES_FOLDER_NAME = 'models';

    public function __construct()
    {
        define('RESMUSH_WEBSERVICE', 'http://api.resmush.it/ws.php?img=');

        $this->load_methods();
    }

    public function load_methods()
    {
        require_once GROWTYPE_AI_PATH . 'includes/methods/crud/openai/Openai_Crud.php';
        new Openai_Crud();

        require_once GROWTYPE_AI_PATH . 'includes/methods/crud/leonardoai/Leonardo_Ai_Crud.php';
        new Leonardo_Ai_Crud();

        require_once GROWTYPE_AI_PATH . 'includes/methods/crud/cloudinary/Cloudinary_Crud.php';
        require_once GROWTYPE_AI_PATH . 'includes/methods/crud/replicate/Replicate_Crud.php';
        require_once GROWTYPE_AI_PATH . 'includes/methods/crud/tinypng/TinyPng_Crud.php';
        require_once GROWTYPE_AI_PATH . 'includes/methods/crud/resmush/Resmush_Crud.php';
    }

    public static function delete_image($image_id)
    {
        $image_path = growtype_ai_get_image_path($image_id);

        if (!empty($image_path) && file_exists($image_path)) {
            unlink($image_path);
        }

        Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::IMAGES_TABLE, [$image_id]);
    }

    public static function save_image($image)
    {
        /**
         * Check generated image id, to prevent duplicates
         */
        $existing_generated_image_id = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
            [
                'key' => 'meta_key',
                'value' => 'generated_image_id',
            ],
            [
                'key' => 'meta_value',
                'value' => $image['id'],
            ]
        ], 'where');

        if (!empty($existing_generated_image_id)) {
            return null;
        }

        $image_url = isset($image['motionMP4URL']) && !empty($image['motionMP4URL']) ? $image['motionMP4URL'] : $image['url'];

        $filename = basename($image_url);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $new_name = uniqid();

        $file = [
            'name' => $new_name,
            'extension' => $ext,
            'width' => $image['imageWidth'],
            'height' => $image['imageHeight'],
            'url' => $image_url,
            'folder' => $image['folder'],
            'location' => $image['location'],
        ];

        /**
         * Save image record
         */
        $image_id = Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGES_TABLE, [
            'name' => $new_name,
            'extension' => $ext,
            'width' => $image['imageWidth'],
            'height' => $image['imageHeight'],
            'location' => $file['location'],
            'folder' => $file['folder']
        ]);

        /**
         * Save external id, to prevent duplicates
         */
        Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
            'image_id' => $image_id,
            'meta_key' => 'generated_image_id',
            'meta_value' => $image['id']
        ]);

        /**
         * Save meta details
         */
        if (isset($image['meta_details'])) {
            foreach ($image['meta_details'] as $key => $meta) {
                Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                    'image_id' => $image_id,
                    'meta_key' => $meta['key'],
                    'meta_value' => $meta['value']
                ]);
            }
        }

        /**
         * Backup images to local drive if they are stored in cloudinary
         */
        $backup_images = false;

        if ($backup_images && $file['location'] === 'cloudinary') {
            $saving_locations = ['locally', 'cloudinary'];
            foreach ($saving_locations as $saving_location) {
                $file['location'] = $saving_location;
                $saved_image = growtype_ai_save_external_file($file, $file['folder']);
            }
        } else {
            $saved_image = growtype_ai_save_external_file($file, $file['folder']);
        }

        /**
         * Update reference id
         */
        Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::IMAGES_TABLE, [
            'reference_id' => isset($saved_image['asset_id']) ? $saved_image['asset_id'] : null
        ], $image_id);

        /**
         * Upscale image
         */
        if (get_option('growtype_ai_replicate_enabled', false)) {
            $cloudinary_public_id = $file['folder'] . '/' . $new_name;

//        $transformed_image_url = $cloudinary_crud->adjust_image($cloudinary_public_id, 'brightness', 'auto');
            $upscale_image_url = $saved_image['url'];

            $real_esrgan = new ReplicateCrud();

            $real_esrgan->upscale($upscale_image_url, [
                'id' => $image['id'],
                'public_id' => $cloudinary_public_id
            ]);
        }

        return [
            'id' => $image_id,
            'details' => $saved_image
        ];
    }
}
