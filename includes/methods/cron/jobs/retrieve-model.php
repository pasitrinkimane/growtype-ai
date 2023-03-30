<?php

try {
    $crud = new Leonardo_Ai_Crud();

    $crud->retrieve_single_generation($job_payload['model_id'], $job_payload['user_nr'], $job_payload['generation_id']);

    /**
     * Delete job
     */
    Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::MODEL_JOBS_TABLE, [$job['id']]);
} catch (Exception $e) {
    Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
        'reserved' => 0,
        'exception' => $e->getMessage(),
    ], $job['id']);
}

sleep(5);
