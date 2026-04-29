<?php

/**
 * Tasks Controller
 *
 * Handles task management CRUD operations for the admin area.
 */
class Tasks extends Trongate {

    /**
     * Display a list of tasks.
     *
     * @return void
     */
    public function manage(): void {
        $this->trongate_security->make_sure_allowed();

        $data = [
            'tasks' => $this->model->fetch_tasks(),
            'view_module' => 'tasks',
            'view_file' => 'manage'
        ];

        $this->templates->admin($data);
    }

    /**
     * Display the create or update task form.
     *
     * @return void
     */
    public function create(): void {
        $this->trongate_security->make_sure_allowed();

        $update_id = segment(3, 'int');
        $submit = post('submit');

        if (($update_id === 0) || ($submit === 'Submit')) {
            $data = $this->model->get_data_from_post();
        } else {
            $data = $this->model->get_data_from_db($update_id);
        }

        $data['headline'] = ($update_id === 0) ? 'Create Task' : 'Update Task';
        $data['update_id'] = $update_id;
        $data['form_location'] = str_replace('/create', '/submit', current_url());
        $data['view_module'] = 'tasks';
        $data['view_file'] = 'create';

        $this->templates->admin($data);
    }

    /**
     * Handle form submission for creating or updating a task.
     *
     * @return void
     */
    public function submit(): void {
        $this->trongate_security->make_sure_allowed();
        $this->validation->set_rules('task_title', 'task title', 'required|min_length[5]|max_length[55]|callback_title_check');
        $this->validation->set_rules('description', 'description', 'required|min_length[3]');

        $result = $this->validation->run();

        if ($result === true) {

            $data = $this->model->get_data_from_post();
            $update_id = segment(3, 'int');

            if ($update_id === 0) {
                $this->db->insert($data, 'tasks');
                set_flashdata('The new task was successfully created.');
            } else {
                $this->db->update($update_id, $data, 'tasks');
                set_flashdata('The task was successfully updated.');
            }

            redirect('tasks/manage');
        } else {
            $this->create();
        }
    }

    public function title_check(string $str): string|bool {
        block_url('tasks/title_check');

        if ($str === 'Johnny') {
            // Return the KEY. The framework will look this up in the language file.
            return 'title_check';
        }

        return true;
    }

    /**
     * Display the delete confirmation screen.
     *
     * @return void
     */
    public function confirm_delete(): void {
        $this->trongate_security->make_sure_allowed();

        $update_id = segment(3, 'int');
        $this->model->get_data_from_db($update_id);

        $data = [
            'form_location' => str_replace('/confirm_delete', '/submit_confirm_delete', current_url()),
            'update_id' => $update_id,
            'view_module' => 'tasks',
            'view_file' => 'confirm_delete'
        ];

        $this->templates->admin($data);
    }

    /**
     * Handle confirmed deletion of a task.
     *
     * @return void
     */
    public function submit_confirm_delete(): void {
        $this->trongate_security->make_sure_allowed();

        $update_id = (int) post('update_id', true);
        $this->db->delete($update_id, 'tasks');
        set_flashdata('The task record was successfully deleted.');
        redirect('tasks/manage');
    }
}
