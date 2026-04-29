<?php
/**
 * Tasks Model
 *
 * Handles data operations for task records.
 */
class Tasks_model extends Model {

    /**
     * Retrieve and sanitise task data from POST.
     *
     * @return array<string, mixed>
     */
    public function get_data_from_post(): array {

        $data = [
            'task_title' => post('task_title', true),
            'description' => post('description', true),
            'complete' => (int) (bool) post('complete', true)
        ];

        return $data;
    }

    /**
     * Retrieve a single task record from the database.
     *
     * @param int $update_id
     * @return array<string, mixed>
     */
    public function get_data_from_db(int $update_id): array {
        $record_obj = $this->db->get_where($update_id, 'tasks');

        if ($record_obj === false) {
            http_response_code(404);
            echo 'Task not found';
            die();
        }

        $task = (array) $record_obj;
        return $task;
    }

    /**
     * Fetch all tasks and append a human-readable status.
     *
     * @return array<int, object>
     */
    public function fetch_tasks(): array {
        $tasks = $this->db->get('id', 'tasks');

        foreach ($tasks as $key => $task) {
            $complete = (int) $task->complete;
            $tasks[$key]->status = ($complete === 1) ? 'complete' : 'not complete';
        }

        return $tasks;
    }
}