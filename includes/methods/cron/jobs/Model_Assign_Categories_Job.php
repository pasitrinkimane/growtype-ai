<?php

class Model_Assign_Categories_Job
{
    public function run($job_payload)
    {
        $model_id = $job_payload['model_id'];
        $model = growtype_ai_get_model_details($model_id);

//        d($model['settings']);

        if (isset($model['settings']) && !isset($model['settings']['categories']) && isset($model['settings']['tags']) && !empty($model['settings']['tags'])) {
            $tags = $model['settings']['tags'];
            $tags = json_decode($tags, true);

//            ddd($model);
//
//            ddd($model_id);
//
//            ddd($tags);
//
//            ddd(growtype_ai_get_images_categories());

            $existing_categories = growtype_ai_get_images_categories();

            $assigned_categories = [];
            foreach ($tags as $tag) {
                foreach ($existing_categories as $category => $values) {
                    $formatted_category = strtolower($category);

                    if (str_contains($formatted_category, $tag)) {
                        $assigned_categories[$category] = [];
                    }
                }
            }

            if (!empty($assigned_categories)) {
                Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
                    'model_id' => $model_id,
                    'meta_key' => 'categories',
                    'meta_value' => json_encode($assigned_categories),
                ]);
            }
        }
    }
}
