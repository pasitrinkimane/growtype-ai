<?php

class Optimize_Database_Job
{
    public function run($job_payload)
    {
        if ($job_payload['action'] === 'sync-local-images') {
            Growtype_Ai_Database_Optimize::sync_local_images();
        }
    }
}

