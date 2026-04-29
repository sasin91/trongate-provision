<h1>Are You Sure?</h1>
<div class="card">
	<div class="card-heading">Confirm Delete</div>
	<div class="card-body">
		<p>You are about to delete a task record.  This cannot be undone.</p>
		<?php
		echo form_open($form_location);
        echo form_hidden('update_id', $update_id);
        echo anchor('tasks/create/'.$update_id, 'Cancel', array('class' => 'button alt'));
        echo form_submit('submit', 'Delete Now', array('class' => 'danger'));
		echo form_close();
		?>
	</div>
</div>