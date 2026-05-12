<?php

require_once __DIR__ . '/../../event/Emits_events.php';
require_once __DIR__ . '/../../server/health/Health_model.php';

class Services extends Trongate
{

  use Emits_events;

  // ── Service CRUD ──────────────────────────────────────────────

  function index(): void
  {
    $this->_require_auth();

    $data = [
      'view_module'   => 'environment/services',
      'view_file'     => 'index',
      'page_title'    => 'Services',
      'services'      => $this->model->all(),
      'type_defaults' => $this->model->get_type_defaults(),
    ];

    $this->module('templates');
    $this->templates->customer($data);
  }

  function create(): void
  {
    $this->_require_auth();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $this->validation->set_rules('environment_id', 'environment', 'required');
      $this->validation->set_rules('name',           'name',        'required|max_length[100]');
      $this->validation->set_rules('type',           'type',        'required');
      $this->validation->set_rules('host',           'host',        'required|max_length[255]');
      $this->validation->set_rules('port',           'port',        'required');

      if ($this->validation->run() === true) {
        $environment_id = (int) post('environment_id');
        $this->module('environment');
        if ($this->environment->model->get($environment_id) === false) {
          $_SESSION['flash_error'] = 'Invalid environment.';
        } else {
          $id = $this->model->create([
            'environment_id' => $environment_id,
            'name'           => post('name', true),
            'type'           => post('type', true),
            'host'           => post('host', true),
            'port'           => (int) post('port'),
            'status'         => 'pending',
          ]);

          $this->_emit('ServiceAdded', 'service', (int) $id, [
            'environment_id' => $environment_id,
            'name'           => post('name', true),
            'type'           => post('type', true),
            'host'           => post('host', true),
            'port'           => (int) post('port'),
          ]);
          $_SESSION['flash_success'] = 'Service added.';
          redirect('environment-services/show/' . $id);
          return;
        }
      }
    }

    $preselected = (int) ($_GET['environment'] ?? 0);

    $data = [
      'view_module'   => 'environment/services',
      'view_file'     => 'create',
      'page_title'    => 'New Service',
      'form_location' => 'environment-services/create',
      'environments'  => $this->model->environments_for_select(),
      'type_defaults' => $this->model->get_type_defaults(),
      'preselected'   => $preselected,
    ];

    $this->module('templates');
    $this->templates->customer($data);
  }

  function show(): void
  {
    $id = (int) segment(3);
    $this->_require_auth();
    $service = $this->model->get($id);
    if ($service === false) {
      redirect('environment-services');
    }

    $health_model = new Health_model();
    $history = $health_model->history('service', $id, 0, 10);
    $latest  = $health_model->latest('service', $id);

    $data = [
      'view_module'   => 'environment/services',
      'view_file'     => 'show',
      'page_title'    => htmlspecialchars($service->name),
      'service'       => $service,
      'history'       => $history,
      'latest_health' => $latest,
      'type_label'    => $this->model->get_type_label($service->type),
    ];

    $this->module('templates');
    $this->templates->customer($data);
  }

  function mark_active(): void
  {
    $id = (int) segment(3);
    $this->_require_auth();
    $this->validation->set_rules('dummy', 'dummy', 'max_length[1]');
    if ($this->validation->run() !== true) {
      redirect('environment-services/show/' . $id);
      return;
    }
    $s = $this->model->get($id);
    if ($s !== false) {
      $old_status = $s->status;
      $this->model->update_status($id, 'active');
      $this->_emit('ServiceStatusChanged', 'service', $id, [
        'from' => $old_status,
        'to'   => 'active',
      ]);
      $_SESSION['flash_success'] = 'Service marked as active.';
    }
    redirect('environment-services/show/' . $id);
  }

  function delete(): void
  {
    $id = (int) segment(3);
    $this->_require_auth();
    $this->validation->set_rules('dummy', 'dummy', 'max_length[1]');
    if ($this->validation->run() !== true) {
      redirect('environment-services/show/' . $id);
      return;
    }
    $snap = $this->model->get($id);
    $this->model->delete_service($id);
    $this->_emit('ServiceDeleted', 'service', $id, [
      'name' => $snap ? $snap->name : null,
      'type' => $snap ? $snap->type : null,
    ]);
    $_SESSION['flash_success'] = 'Service deleted.';
    redirect('environment-services');
  }

  private function _require_auth(): void
  {
    $this->module('trongate_tokens');
    if (!$this->trongate_tokens->_attempt_get_valid_token(1)) {
      redirect('login');
    }
  }
}
