<h1><?= $headline ?></h1>
<div class="card">
	<div class="card-heading">Task Details</div>
	<div class="card-body">
		<?php
		echo form_open($form_location, array('class' =>  'highlight-errors'));
        
        echo form_label('Task Title');
        echo validation_errors('task_title');
        echo form_input('task_title', $task_title, array('placeholder' => 'Task title...', 'autocomplete' => 'off'));

        echo form_label('Description');
        echo validation_errors('description');
        echo form_textarea('description', $description, array('placeholder' => 'Enter task details here...'));

        echo '<label>';
        echo form_checkbox('complete', 1, $complete);
        echo 'task complete';
        echo '</label>';

		echo '<div class="text-center">';
        echo anchor('tasks/manage', 'Cancel', array('class' => 'button alt'));

        if ($update_id > 0) {
        	echo anchor('tasks/confirm_delete/'.$update_id, 'Delete Task', array('class' => 'button danger'));
        }

        echo form_submit('submit', 'Submit');
		echo '</div>';

		echo form_close();
		?>
	</div>
</div>

