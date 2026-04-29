<h1>Manage Tasks</h1>
<?= flashdata() ?>
<p><?= anchor('tasks/create', 'Create New Task', array('class' => 'button alt')) ?></p>

<?php
if (empty($tasks)) {
	return;
}
?>

<table>
	<thead>
		<tr>
			<th>ID</th>
			<th>Task Title</th>
			<th>Status</th>
			<th>Action</th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach($tasks as $task) { ?>
		<tr>
			<td><?= $task->id ?></td>
			<td><?= out($task->task_title) ?></td>
			<td><?= $task->status ?></td>
			<td class="text-center"><?= anchor('tasks/create/'.$task->id, 'Edit') ?></td>
		</tr>
		<?php
		}
		?>
	</tbody>
</table>






