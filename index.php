<?php

use rdx\f95\Source;

require 'inc.bootstrap.php';

if ( isset($_POST['enabled']) ) {
	print_r($_POST);
	exit;
}

include 'tpl.header.php';

$sources = Source::all('1=1 ORDER BY name');
Source::eager('last_fetch', $sources);

?>
<form method="post" action>
	<table border="1">
		<thead>
			<tr>
				<th></th>
				<th>Title</th>
				<th>Latest release</th>
				<th>Latest URL</th>
				<th>Last checked</th>
			</tr>
		</thead>
		<tbody>
			<? foreach ($sources as $source): ?>
				<tr>
					<td><input type="checkbox" name="enabled[]" value="<?= $source->id ?>" <?= $source->active ? 'checked' : '' ?> /></td>
					<td><?= html($source->name) ?></td>
					<td><?= $source->last_fetch->date ?? '-' ?></td>
					<td><?= $source->last_fetch->url ?? '-' ?></td>
					<td><?= date('D j-M', $source->last_fetch->created_on ?? 0) ?></td>
				</tr>
			<? endforeach ?>
		</tbody>
	</table>
</form>
<?php

include 'tpl.footer.php';
