<?php

require_once __DIR__ . '/../../event/Emits_events.php';
require_once __DIR__ . '/../../server/health/Health_model.php';

class Services extends Trongate
{

  use Emits_events;

  // ── Service CRUD ──────────────────────────────────────────────

  function index(): void
  {
    $customer = $this->_require_customer();

    $data = [
      'view_module'   => 'environment/services',
      'view_file'     => 'index',
      'page_title'    => 'Services',
      'current_email' => $customer->email,
      'services'      => $this->model->all((int) $customer->id),
      'type_defaults' => $this->model->get_type_defaults(),
    ];

    $this->module('templates');
    $this->templates->customer($data);
  }

  function create(): void
  {
    $customer = $this->_require_customer();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $this->validation->set_rules('environment_id', 'environment', 'required');
      $this->validation->set_rules('name',           'name',        'required|max_length[100]');
      $this->validation->set_rules('type',           'type',        'required');
      $this->validation->set_rules('host',           'host',        'required|max_length[255]');
      $this->validation->set_rules('port',           'port',        'required');

      if ($this->validation->run() === true) {
        $environment_id = (int) post('environment_id');
        $this->module('environment');
        if ($this->environment->model->get($environment_id, (int) $customer->id) === false) {
          $_SESSION['flash_error'] = 'Invalid environment.';
        } else {
          $id = $this->model->create([
            'environment_id' => $environment_id,
            'customer_id'    => (int) $customer->id,
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
      'current_email' => $customer->email,
      'form_location' => 'environment-services/create',
      'environments'  => $this->model->environments_for_customer((int) $customer->id),
      'type_defaults' => $this->model->get_type_defaults(),
      'preselected'   => $preselected,
    ];

    $this->module('templates');
    $this->templates->customer($data);
  }

  function show(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_customer();
    $service = $this->model->get($id, (int) $customer->id);
    if ($service === false) {
      redirect('environment-services');
    }

    $health_model = new Health_model();
    $history = $health_model->history('service', $id, (int) $customer->id, 10);
    $latest  = $health_model->latest('service', $id);

    $data = [
      'view_module'   => 'environment/services',
      'view_file'     => 'show',
      'page_title'    => htmlspecialchars($service->name),
      'current_email' => $customer->email,
      'service'       => $service,
      'history'       => $history,
      'latest_health' => $latest,
      'type_label'    => $this->model->get_type_label($service->type),
    ];

    $this->module('templates');
    $this->templates->customer($data);
  }

  function mark_running(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_customer();
    $this->validation->set_rules('dummy', 'dummy', 'max_length[1]');
    if ($this->validation->run() !== true) {
      redirect('environment-services/show/' . $id);
      return;
    }
    $s = $this->model->get($id, (int) $customer->id);
    if ($s !== false) {
      $old_status = $s->status;
      $this->model->update_status($id, 'running');
      $this->_emit('ServiceStatusChanged', 'service', $id, [
        'from' => $old_status,
        'to'   => 'running',
      ]);
      $_SESSION['flash_success'] = 'Service marked as running.';
    }
    redirect('environment-services/show/' . $id);
  }

  function delete(): void
  {
    $id = (int) segment(3);
    $customer = $this->_require_customer();
    $this->validation->set_rules('dummy', 'dummy', 'max_length[1]');
    if ($this->validation->run() !== true) {
      redirect('environment-services/show/' . $id);
      return;
    }
    $snap = $this->model->get($id, (int) $customer->id);
    $this->model->delete_service($id, (int) $customer->id);
    $this->_emit('ServiceDeleted', 'service', $id, [
      'name' => $snap ? $snap->name : null,
      'type' => $snap ? $snap->type : null,
    ]);
    $_SESSION['flash_success'] = 'Service deleted.';
    redirect('environment-services');
  }

  private function _require_customer(): object
  {
    $this->module('customer');
    $this->customer->_require_onboarded();
    return $this->customer->_require_customer();
  }
}
