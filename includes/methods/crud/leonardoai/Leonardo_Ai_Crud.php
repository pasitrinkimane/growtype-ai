<?php

require GROWTYPE_AI_PATH . '/vendor/autoload.php';

use React\EventLoop\Loop;

class Leonardo_Ai_Crud
{
    const PROVIDER = 'leonardoai';

    public static function user_credentials()
    {
        return [
            '1' => [
                'cookie' => get_option('growtype_ai_leonardo_access_key'),
                'user_id' => get_option('growtype_ai_leonardo_user_id'),
                'access_token' => get_option('growtype_ai_leonardo_access_token')
            ],
            '2' => [
                'cookie' => get_option('growtype_ai_leonardo_access_key_2'),
                'user_id' => get_option('growtype_ai_leonardo_user_id_2'),
                'access_token' => get_option('growtype_ai_leonardo_access_token_2')
            ],
            '3' => [
                'cookie' => get_option('growtype_ai_leonardo_access_key_3'),
                'user_id' => get_option('growtype_ai_leonardo_user_id_3'),
                'access_token' => get_option('growtype_ai_leonardo_access_token_3')
            ]
        ];
    }

    function get_user_credentials($user_nr)
    {
        if (empty($user_nr)) {
            $user_nr = 1;
        }

        return self::user_credentials()[$user_nr];
    }

    public function generate_model($model_id = null)
    {
        $token = $this->get_access_token();

        $generation_details = $this->get_generation_details($token, $model_id);

        growtype_ai_init_job('retrieve-model', json_encode([
            'user_nr' => $generation_details['user_nr'],
            'amount' => 1,
            'model_id' => $model_id,
            'generation_id' => $generation_details['generation_id'],
        ]), 30);
    }

    public function get_generation_details($token, $model_id)
    {
        $user_nr = 1;
        $cookie = $this->get_user_credentials($user_nr)['cookie'];
        $token = $this->retrieve_access_token($cookie);
        $generation_id = $this->init_image_generating($token, $model_id);

        if (empty($generation_id)) {
            $user_nr = 2;
            $cookie = $this->get_user_credentials($user_nr)['cookie'];
            $token = $this->retrieve_access_token($cookie);
            $generation_id = $this->init_image_generating($token, $model_id);

            if (empty($generation_id)) {
                $user_nr = 3;
                $cookie = $this->get_user_credentials($user_nr)['cookie'];
                $token = $this->retrieve_access_token($cookie);
                $generation_id = $this->init_image_generating($token, $model_id);

                if (empty($generation_id)) {
                    throw new Exception('No generationId');
                }
            }
        }

        return [
            'generation_id' => $generation_id,
            'user_nr' => $user_nr
        ];
    }

    public function retrieve_models($amount, $model_id = null, $user_nr = null)
    {
        $token = $this->get_access_token($user_nr);

        $generations = $this->get_generations($token, $amount, $user_nr);

        if (empty($generations)) {
            return null;
        }

        $this->save_generations($generations, $model_id);

        $this->delete_external_generations($token, $generations);

        return $generations;
    }

    public function retrieve_single_generation($model_id, $user_nr, $generation_id)
    {
        $token = $this->get_access_token($user_nr);

        $generations = $this->get_generations($token, 30, $user_nr);

        if (empty($generations)) {
            throw new Exception('Empty generations. Token: ' . $token);
        }

        $single_generation = '';
        foreach ($generations as $generation) {
            if ($generation['id'] === $generation_id) {
                $single_generation = $generation;
                break;
            }
        }

        if (empty($single_generation)) {
            throw new Exception('No generation');
        }

        $generations = [$single_generation];

        /**
         * Save generation
         */
        $this->save_generations($generations, $model_id);

        /**
         * Delete generation from Leonardo.ai
         */
        $this->delete_external_generations($token, $generations);

        return $generations;
    }

//    ------

    public function get_access_token($user_nr = null)
    {
        $cookie = $this->get_user_credentials($user_nr)['cookie'];
        $access_token = $this->get_user_credentials($user_nr)['access_token'];

        $token = !empty($access_token) ? $access_token : $this->retrieve_access_token($cookie);

        if (empty($token)) {
            throw new Exception('No token user_nr: ' . $user_nr);
        }

        return $token;
    }

    function get_login_details($cookie)
    {
        $url = 'https://app.leonardo.ai/api/auth/me';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'cookie' => $cookie,
            ),
            'method' => 'GET',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $responceData;
    }

    function retrieve_access_token($cookie)
    {
        $url = 'https://app.leonardo.ai/api/auth/access-token';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'cookie' => $cookie,
            ),
            'method' => 'GET',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return isset($responceData['accessToken']) ? $responceData['accessToken'] : null;
    }

    function return_generation($token, $generation_id)
    {
        $generations = $this->get_generations($token);

        foreach ($generations as $generation) {
            if ($generation['id'] === $generation_id && isset($generation['generated_images']) && !empty($generation['generated_images'])) {
                return $generation;
            }
        }

        return null;
    }

    public function retrieve_generation($token, $generation_id)
    {
        $timer = Loop::addPeriodicTimer(7, function () use ($token, $generation_id) {
            $latest_generation = $this->return_generation($token, $generation_id);

            if (!empty($latest_generation)) {
                Loop::stop();
                d($latest_generation);
            }
        });

        Loop::addTimer(28, function () use ($timer) {
            Loop::cancelTimer($timer);
        });
    }

    public function init_image_generating($token, $model_id = null)
    {
        $url = 'https://api.leonardo.ai/v1/graphql';

        if (!empty($model_id)) {
            $model_details = growtype_ai_get_model_details($model_id);

            $parameters = [
                'operationName' => 'CreateSDGenerationJob',
                'variables' => [
                    'arg1' => [
                        'prompt' => $model_details['prompt'],
                        'negative_prompt' => $model_details['negative_prompt'],
                        'nsfw' => true,
                        'num_images' => 1,
                        'width' => (int)$model_details['settings']['image_width'],
                        'height' => (int)$model_details['settings']['image_height'],
                        'num_inference_steps' => (int)$model_details['settings']['num_inference_steps'],
                        'guidance_scale' => (int)$model_details['settings']['guidance_scale'],
                        'init_strength' => (int)$model_details['settings']['init_strength'],
                        'sd_version' => $model_details['settings']['sd_version'],
                        'modelId' => $model_details['settings']['model_id'],
                        'presetStyle' => $model_details['settings']['preset_style'],
                        'scheduler' => $model_details['settings']['scheduler'],
                        'leonardoMagic' => false,
                        'public' => false,
                        'tiling' => false,
                    ]
                ],
                'query' => 'mutation CreateSDGenerationJob($arg1: SDGenerationInput!) { sdGenerationJob(arg1: $arg1) { generationId __typename }}'
            ];

            $parameters = json_encode($parameters);
        } else {
            $parameters = '{
   "operationName":"CreateSDGenerationJob",
   "variables":{
      "arg1":{
         "prompt":"Sunset mountain",
         "negative_prompt":"Palm tree, beach, Sea,",
         "nsfw":true,
         "num_images":1,
         "width":512,
         "height":512,
         "num_inference_steps":30,
         "guidance_scale":7,
         "init_strength":0.5,
         "sd_version":"v1_5",
         "modelId":"fc42c4b3-1b19-44b7-b9fa-4d3d018af689",
         "presetStyle":"NONE",
         "scheduler":"EULER_DISCRETE",
         "leonardoMagic":false,
         "public":false,
         "tiling":false
      }
   },
   "query":"mutation CreateSDGenerationJob($arg1: SDGenerationInput!) {\n  sdGenerationJob(arg1: $arg1) {\n    generationId\n    __typename\n  }\n}"
}';
        }

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
            ),
            'body' => $parameters,
            'method' => 'POST',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return isset($responceData['data']['sdGenerationJob']['generationId']) ? $responceData['data']['sdGenerationJob']['generationId'] : null;
    }

    function get_user_details($token, $userSub)
    {
        $url = 'https://api.leonardo.ai/v1/graphql';

        $parameters = '{
   "operationName":"GetUserDetails",
   "variables":{
      "userSub":"' . $userSub . '"
   },
   "query":"query GetUserDetails($userSub: String) {\n  users(where: {user_details: {auth0Id: {_eq: $userSub}}}) {\n    id\n    username\n    user_details {\n      auth0Email\n      plan\n      paidTokens\n      subscriptionTokens\n      subscriptionModelTokens\n      subscriptionGptTokens\n      interests\n      showNsfw\n      __typename\n    }\n    __typename\n  }\n}"
}';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
            ),
            'body' => $parameters,
            'method' => 'POST',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $responceData;
    }

    function get_generations($token, $amount = 10, $user_nr = null)
    {
        $url = 'https://api.leonardo.ai/v1/graphql';

        $user_id = $this->get_user_credentials($user_nr)['user_id'];

        $parameters = '{
    "operationName": "GetAIGenerationFeed",
    "variables": {
        "where": {
            "userId": {
                "_eq": "' . $user_id . '"
            },
            "canvasRequest": {
                "_eq": false
            }
        },
        "userId": "' . $user_id . '"
    },
    "query": "query GetAIGenerationFeed($where: generations_bool_exp = {}, $userId: uuid!) {\n  generations(limit: ' . $amount . ', order_by: [{createdAt: desc}], where: $where) {\n    guidanceScale\n    inferenceSteps\n    modelId\n    scheduler\n    coreModel\n    sdVersion\n    prompt\n    negativePrompt\n    id\n    status\n    quantity\n    createdAt\n    imageHeight\n    imageWidth\n    presetStyle\n    sdVersion\n    seed\n    tiling\n    initStrength\n    user {\n      username\n      id\n      __typename\n    }\n    custom_model {\n      id\n      userId\n      name\n      modelHeight\n      modelWidth\n      __typename\n    }\n    init_image {\n      id\n      url\n      __typename\n    }\n    generated_images(order_by: [{url: desc}]) {\n      id\n      url\n      likeCount\n      generated_image_variation_generics(order_by: [{createdAt: desc}]) {\n        url\n        status\n        createdAt\n        id\n        transformType\n        __typename\n      }\n      user_liked_generated_images(limit: 1, where: {userId: {_eq: $userId}}) {\n        generatedImageId\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}"
}';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
            ),
            'body' => $parameters,
            'method' => 'POST',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return isset($responceData['data']['generations']) ? $responceData['data']['generations'] : null;
    }

    function delete_generation($token, $id)
    {
        $url = 'https://api.leonardo.ai/v1/graphql';

        $parameters = '{"operationName":"DeleteGeneration","variables":{"id":"' . $id . '"},"query":"mutation DeleteGeneration($id: uuid!) {\n  delete_generations_by_pk(id: $id) {\n    id\n    __typename\n  }\n}"}';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
            ),
            'body' => $parameters,
            'method' => 'POST',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $responceData;
    }

    /**
     * @param $generations
     * @param $existing_model_id
     * @return void
     */
    function save_generations($generations, $existing_model_id = null)
    {
        /**
         * Group generations by unique key
         */
        $grouped_generations = [];
        foreach ($generations as $generation) {
            $unique_key = implode('-', [
                'grouped',
                $generation['modelId'],
                $generation['guidanceScale'],
                preg_replace('/\s+/', '_', trim(substr($generation['prompt'], 0, 100))),
                $generation['inferenceSteps'],
                $generation['scheduler'],
                $generation['coreModel'],
                $generation['sdVersion'],
                $generation['presetStyle'],
                $generation['tiling'],
            ]);

            $grouped_generations[trim($unique_key)][] = $generation;
        }

        foreach ($grouped_generations as $generations_group) {

            $reference_id = growtype_ai_generate_reference_id();

            if (!empty($existing_model_id)) {
                $model = growtype_ai_get_model_details($existing_model_id);
                $reference_id = $model['reference_id'];
            }

            foreach ($generations_group as $generation) {
                $image_folder = self::PROVIDER . '/' . $reference_id;
                $image_location = get_option('growtype_ai_images_saving_location', 'locally');

                $existing_models = Growtype_Ai_Database::get_records(Growtype_Ai_Database::MODELS_TABLE, [
                    [
                        'key' => 'reference_id',
                        'values' => [$reference_id],
                    ]
                ]);

                if (empty($existing_models)) {
                    $model_id = Growtype_Ai_Database::insert_record(Growtype_Ai_Database::MODELS_TABLE, [
                        'prompt' => $generation['prompt'],
                        'negative_prompt' => !empty($generation['negativePrompt']) ? $generation['negativePrompt'] : 'watermark, watermarked, disfigured, ugly, grain, low resolution, deformed, blurred, bad anatomy, badly drawn face, extra limb, ugly, badly drawn arms, missing limb, floating limbs, detached limbs, deformed arms, out of focus, disgusting, badly drawn, disfigured, tile, badly drawn arms, badly drawn legs, badly drawn face, out of frame, extra limbs, deformed, body out of frame, grainy, clipped, bad proportion, cropped image, blur haze',
                        'reference_id' => $reference_id,
                        'provider' => self::PROVIDER,
                        'image_folder' => $image_folder,
                        'image_location' => $image_location,
                    ]);

                    $model_settings = [
                        'model_id' => $generation['modelId'],
                        'guidance_scale' => $generation['guidanceScale'],
                        'inference_steps' => $generation['inferenceSteps'],
                        'scheduler' => $generation['scheduler'],
                        'core_model' => $generation['coreModel'],
                        'sd_version' => $generation['sdVersion'],
                        'tiling' => $generation['tiling'],
                        'init_strength' => $generation['initStrength'],
                        'image_width' => $generation['imageWidth'],
                        'image_height' => $generation['imageHeight'],
                        'num_inference_steps' => $generation['inferenceSteps'],
                        'preset_style' => $generation['presetStyle'],
                        'leonardo_magic' => isset($generation['leonardoMagic']) ? $generation['leonardoMagic'] : null,
                    ];

                    foreach ($model_settings as $key => $value) {
                        Growtype_Ai_Database::insert_record(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
                            'model_id' => $model_id,
                            'meta_key' => $key,
                            'meta_value' => $value
                        ]);
                    }

                    $openai_crud = new Openai_Crud();
                    $openai_crud->format_models(null, false, $model_id);
                } else {
                    $model_id = $existing_models[0]['id'];
                }

                foreach ($generation['generated_images'] as $image) {
                    $image['imageWidth'] = $generation['imageWidth'];
                    $image['imageHeight'] = $generation['imageHeight'];
                    $image['folder'] = $image_folder;
                    $image['location'] = $image_location;

                    $saved_image = Growtype_Ai_Crud::save_image($image);

                    if (empty($saved_image)) {
                        continue;
                    }

                    /**
                     * Assign image to model
                     */
                    Growtype_Ai_Database::insert_record(Growtype_Ai_Database::MODEL_IMAGE_TABLE, [
                        'model_id' => $model_id,
                        'image_id' => $saved_image['id']
                    ]);

                    /**
                     * Generate image content
                     */
                    $openai_crud = new Openai_Crud();
                    $openai_crud->format_image($saved_image['id']);

                    /**
                     * Update cloudinary image details
                     */
                    if ($image['location'] === 'cloudinary') {
                        $cloudinary_crud = new Cloudinary_Crud();
                        $cloudinary_crud->update_cloudinary_image_details($saved_image['id']);
                    }

                    sleep(2);
                }
            }
        }
    }

    function delete_external_generations($token, $generations)
    {
        foreach ($generations as $generation) {
            $this->delete_generation($token, $generation['id']);
        }
    }
}


