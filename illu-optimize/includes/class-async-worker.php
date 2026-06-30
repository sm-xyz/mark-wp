<?php
/**
 * Illu Async Engine (Background Worker/Queue)
 * Handles heavy tasks asynchronously to speed up HTTP response.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Illu_Optimize_Async_Worker {
    public function __construct() {
        add_action('illu_optimize_process_queue', [$this, 'process_queue']);
    }

    public static function queue_job($action, $payload) {
        $is_enabled = get_option('illu_async_worker_enabled', '1');
        if (!$is_enabled) {
            // Fallback to synchronous if disabled
            do_action('illu_async_' . $action, $payload);
            return;
        }

        $jobs = get_option('illu_optimize_queue', []);
        $jobs[] = [
            'action' => $action,
            'payload' => $payload,
            'time' => time()
        ];
        update_option('illu_optimize_queue', $jobs);
        
        if (!wp_next_scheduled('illu_optimize_process_queue')) {
            wp_schedule_single_event(time(), 'illu_optimize_process_queue');
        }
    }

    public function process_queue() {
        $jobs = get_option('illu_optimize_queue', []);
        if (empty($jobs)) return;

        $batch = array_slice($jobs, 0, 10); // Process 10 items per run
        $remaining = array_slice($jobs, 10);
        
        update_option('illu_optimize_queue', $remaining);

        foreach ($batch as $job) {
            do_action('illu_async_' . $job['action'], $job['payload']);
        }
        
        if (!empty($remaining) && !wp_next_scheduled('illu_optimize_process_queue')) {
            wp_schedule_single_event(time(), 'illu_optimize_process_queue');
        }
    }
}
