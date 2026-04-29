<?php

require_once __DIR__ . '/Script_model.php';
require_once __DIR__ . '/../event/Emits_events.php';

class Script extends Trongate {

    use Emits_events;

    function index(): void {
        $customer = $this->_require_customer();

        $data = [
            'view_module'   => 'script',
            'view_file'     => 'index',
            'page_title'    => 'Scripts',
            'current_email' => $customer->email,
            'scripts'       => $this->model->all((int) $customer->id),
        ];

        $this->module('templates');
        $this->templates->customer($data);
    }

    function create(): void {
        $customer = $this->_require_customer();
        $type = in_array($_GET['type'] ?? '', ['lamp', 'deploy']) ? $_GET['type'] : 'deploy';

        $data = [
            'view_module'   => 'script',
            'view_file'     => 'create',
            'page_title'    => 'New Script',
            'current_email' => $customer->email,
            'form_location' => 'script/store',
            'default_type'  => $type,
            'deploy_vars'   => Script_model::DEPLOY_VARS,
            'lamp_vars'     => Script_model::LAMP_VARS,
        ];

        $this->module('templates');
        $this->templates->customer($data);
    }

    function store(): void {
        $customer = $this->_require_customer();

        $this->validation->set_rules('name', 'name', 'required|max_length[150]');
        $this->validation->set_rules('type', 'type', 'required');
        $this->validation->set_rules('body', 'script body', 'required');

        if ($this->validation->run() !== true) {
            $this->create();
            return;
        }

        $id = $this->model->create([
            'customer_id' => (int) $customer->id,
            'name'        => post('name', true),
            'description' => post('description', true),
            'type'        => post('type', true),
            'body'        => post('body'),
        ]);

        $this->_emit('ScriptCreated', 'script', (int) $id, [
            'name' => post('name', true),
            'type' => post('type', true),
        ]);
        $_SESSION['flash_success'] = 'Script saved.';
        redirect('script/show/' . $id);
    }

    function show(): void {
        $id = (int) segment(3);
        $customer = $this->_require_customer();
        $script = $this->model->get($id, (int) $customer->id);
        if ($script === false) { redirect('script'); }

        $vars = $script->type === 'lamp' ? Script_model::LAMP_VARS : Script_model::DEPLOY_VARS;

        $data = [
            'view_module'   => 'script',
            'view_file'     => 'show',
            'page_title'    => htmlspecialchars($script->name),
            'current_email' => $customer->email,
            'script'        => $script,
            'available_vars'=> $vars,
        ];

        $this->module('templates');
        $this->templates->customer($data);
    }

    function edit(): void {
        $id = (int) segment(3);
        $customer = $this->_require_customer();
        $script = $this->model->get($id, (int) $customer->id);
        if ($script === false) { redirect('script'); }

        $data = [
            'view_module'   => 'script',
            'view_file'     => 'edit',
            'page_title'    => 'Edit: ' . htmlspecialchars($script->name),
            'current_email' => $customer->email,
            'script'        => $script,
            'form_location' => 'script/update/' . $id,
            'deploy_vars'   => Script_model::DEPLOY_VARS,
            'lamp_vars'     => Script_model::LAMP_VARS,
        ];

        $this->module('templates');
        $this->templates->customer($data);
    }

    function update(): void {
        $id = (int) segment(3);
        $customer = $this->_require_customer();
        $script = $this->model->get($id, (int) $customer->id);
        if ($script === false) { redirect('script'); }

        $this->validation->set_rules('name', 'name', 'required|max_length[150]');
        $this->validation->set_rules('body', 'script body', 'required');

        if ($this->validation->run() !== true) {
            $this->edit();
            return;
        }

        $this->model->update($id, [
            'name'        => post('name', true),
            'description' => post('description', true),
            'body'        => post('body'),
        ]);

        $this->_emit('ScriptUpdated', 'script', $id, [
            'name' => post('name', true),
        ]);
        $_SESSION['flash_success'] = 'Script updated.';
        redirect('script/show/' . $id);
    }

    function delete(): void {
        $id = (int) segment(3);
        $customer = $this->_require_customer();
        $snap = $this->model->get($id, (int) $customer->id);
        $this->model->delete($id, (int) $customer->id);
        $this->_emit('ScriptDeleted', 'script', $id, [
            'name' => $snap ? $snap->name : null,
            'type' => $snap ? $snap->type : null,
        ]);
        $_SESSION['flash_success'] = 'Script deleted.';
        redirect('script');
    }

    private function _require_customer(): object {
        $this->module('customer');
        $this->customer->_require_onboarded();
        return $this->customer->_require_customer();
    }
}
