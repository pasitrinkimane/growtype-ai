<?php

require GROWTYPE_AI_PATH . '/vendor/autoload.php';

use Orhanerday\OpenAi\OpenAi;

class Openai_Crud
{

    public function __construct()
    {
        $this->open_ai_key = get_option('growtype_ai_openai_api_key');
    }

    public function generate_content($text, $type)
    {
        if (empty($text)) {
            return '';
        }

//        error_log('GPT generating content');
        $open_ai = new OpenAi($this->open_ai_key);

        $text = str_replace('{prompt_variable}', '', $text);

        $content = '';
        $already_used_answers = [];
        for ($i = 0; $i < 2; $i++) {
            $ignorance_text = 'Do not give answers like ' . implode(',', $already_used_answers) . '.';
            switch ($type) {
                case 'title':
                case 'caption':
                    $content = "Create creative, short artwork title from few words without artist name and without quotes, dots, question marks or punctuation marks, inspired by text - '" . $text . "'. " . $ignorance_text;
                    break;
                case 'description':
                case 'alt_text':
                    $content = "Create short, modest artwork description, from maximum 3 sentences, inspired by text - '" . $text . "'. Do not use quotes, do not mention measuring, format, technique, product type, words like 'canvas, print' or artists names. " . $ignorance_text;
                    break;
                case 'tags':
                    $content = "return only array, (no extra text or sentences), with maximum 20 single words, without numbers, extracted from text - '" . $text . "'. Select only descriptive words (not general like f.e. 'style,art') that later could be used for search purpose. " . $ignorance_text;
                    break;
            }

            $complete = $open_ai->chat([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        "role" => "system",
                        "content" => "You are a helpful assistant."
                    ],
                    [
                        "role" => "user",
                        "content" => $content
                    ],
                ],
                'temperature' => 1.0,
                'max_tokens' => 3000,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
            ]);

            $completion = json_decode($complete, true);

            $content = isset($completion['choices'][0]['message']['content']) ? $completion['choices'][0]['message']['content'] : null;

            if (strpos($content, "Im sorry") !== false || strpos($content, "Sure! Here is") !== false) {
                $content = null;
            }

            if (!empty($content)) {

                array_push($already_used_answers, $content);

                if ($type === 'tags') {
                    $decoded_content = json_decode($content, true);

                    if (!empty($decoded_content)) {
                        $content = implode(',', $decoded_content);
                        $content = strtolower($content);
                        $content = explode(',', $content);
                        $content = json_encode($content);
                    } else {
                        $content = str_replace("'", "", $content);
                        $content = str_replace("[", "", $content);
                        $content = str_replace("]", "", $content);

                        if (strpos($content, ",") !== false) {
                            $content = explode(',', $content);
                        } else {
                            $content = explode(' ', $content);
                        }

                        $cleaned_content = [];
                        foreach ($content as $word) {
                            array_push($cleaned_content, trim(strtolower($word)));
                        }

                        $content = json_encode($cleaned_content);
                    }
                } elseif ($type === 'caption') {
                    $content = str_replace('"', "", $content);
                    $content = str_replace("'", "", $content);
                }

                if (!empty($content) && ($type === 'caption' || $type === 'title')) {
                    global $wpdb;
                    $records = $wpdb->get_results("SELECT meta_value FROM wp_growtype_ai_image_settings where meta_key='caption' and meta_value != '' group by meta_value", ARRAY_A);

                    if (in_array($content, array_pluck($records, 'meta_value'))) {
                        $content = null;
                    }
                }
            }

            if (!empty($content)) {
                break;
            }
        }

        return $content;
    }

    public function format_models($generation_type = null, $regenerate_values = false, $model_id = null)
    {
        if (!empty($model_id)) {
            $models = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODELS_TABLE, [
                [
                    'key' => 'id',
                    'values' => [$model_id],
                ]
            ]);
        } else {
            $models = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODELS_TABLE);
        }

        $generation_types = [
            'title' => [
                'meta_key' => 'title',
                'encode' => false,
            ],
            'tags' => [
                'meta_key' => 'tags',
                'encode' => true,
            ],
            'description' => [
                'meta_key' => 'description',
                'encode' => false,
            ],
        ];

        if (!empty($generation_type)) {
            $generation_types = [$generation_types[$generation_type]];
        }

        foreach ($generation_types as $type) {
            foreach ($models as $model) {
                growtype_ai_init_job('generate-model-content', json_encode([
                    'meta_key' => $type['meta_key'],
                    'model_id' => $model_id,
                    'encode' => $type['encode'],
                    'prompt' => $model['prompt'],
                ]), 30);
            }
        }
    }

    public function format_model_images($model_id = null, $regenerate_values = false)
    {
        if (empty($model_id)) {
            $models = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODELS_TABLE);
        } else {
            $models = [growtype_ai_get_model_details($model_id)];
        }

//        global $wpdb;
//        $records = $wpdb->get_results("SELECT image_id, meta_value, COUNT(meta_value) FROM wp_growtype_ai_image_settings where meta_key='caption' group by meta_value HAVING COUNT(meta_value) > 1", ARRAY_A);
//
////        var_dump(count($records));
//
//        foreach (array_slice($records, 0, 300) as $record) {
//            $this->format_image($record['image_id'], true);
//        }
//
//        dd('done');
//
//        $existing_captions = [];
//        d(growtype_ai_get_image_details($image['id']));


        foreach ($models as $model) {
            $images = growtype_ai_get_model_images($model['id']);

            foreach ($images as $image) {
                $this->format_image_job($image['id'], $regenerate_values);
            }
        }
    }

    public function format_image($image_id, $regenerate_values = false)
    {
        $Generate_Image_Content_Job = new Generate_Image_Content_Job();
        $Generate_Image_Content = $Generate_Image_Content_Job->run([
            'image_id' => $image_id,
            'regenerate_content' => true,
        ]);

//        d(growtype_ai_get_image_details($image_id));
    }

    public function format_image_job($image_id, $regenerate_values = false)
    {
        growtype_ai_init_job('generate-image-content', json_encode([
            'image_id' => $image_id,
            'regenerate_content' => $regenerate_values,
        ]), 5);
    }
}

