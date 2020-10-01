<?php

use rdx\f95\Source;

require 'inc.bootstrap.php';

if ( isset($_POST['enabled']) ) {
	$ids = $_POST['enabled'];
	Source::updateAll(['active' => 0], "id not in (?)", [$ids]);
	Source::updateAll(['active' => 1], "id in (?)", [$ids]);

	return do_redirect('index');
}

if ( isset($_POST['name'], $_POST['f95_id']) ) {
	Source::insert([
		'name' => trim($_POST['name']),
		'f95_id' => trim($_POST['f95_id']),
	]);

	return do_redirect('index');
}

include 'tpl.header.php';

$sources = Source::all('1=1 ORDER BY active DESC, name ASC');
Source::eager('last_fetch', $sources);

?>
<style>
.recent-release {
	color: green;
	font-weight: bold;
}
.not-release-date {
	color: orange;
}
</style>

<form method="post" action>
	<table border="1">
		<thead>
			<tr>
				<th></th>
				<th>Title</th>
				<th>Latest release</th>
				<!-- <th>Latest URL</th> -->
				<th>Last checked</th>
			</tr>
		</thead>
		<tbody>
			<? foreach ($sources as $source): ?>
				<tr>
					<td><input type="checkbox" name="enabled[]" value="<?= $source->id ?>" <?= $source->active ? 'checked' : '' ?> /></td>
					<td><?= html($source->name) ?></td>
					<td nowrap class="<?= $source->released_recently ? 'recent-release' : '' ?> <?= $source->not_release_date ? 'not-release-date' : '' ?>">
						<?= $source->last_fetch ? ($source->last_fetch->release_date ?? $source->last_fetch->thread_date . ' ?' ?? 'no date?') : '' ?>
					</td>
					<!-- <td><?= $source->last_fetch->url ?? '' ?></td> -->
					<td nowrap>
						<? if ($source->last_fetch): ?>
							<?= date('D j-M', $source->last_fetch->created_on) ?>
							<a href="<?= html($source->last_fetch->url) ?>">&#10132;</a>
						<? endif ?>
					</td>
				</tr>
			<? endforeach ?>
		</tbody>
	</table>
	<p><button>Save</button></p>
</form>

<br>

<form method="post" action>
	<fieldset>
		<legend>Add source</legend>
		<p>Name: <input name="name" required /></p>
		<p>F95 ID: <input name="f95_id" required pattern="^\d+$" /></p>
		<p><button>Save</button></p>
	</fieldset>
</form>

<script>
window.URL_PATTERN = /^<?= strtr(preg_quote(F95_URL, '/'), [
	preg_quote('{name}') => '[^\/\.]+',
	preg_quote('{id}') => '(\d+)',
]) ?>/;
document.querySelector('input[name="f95_id"]').addEventListener('paste', function(e) {
	// console.log(e.clipboardData.getData('text'));
	setTimeout(() => {
		const m = this.value.match(window.URL_PATTERN);
		if (m) {
			this.value = m[1];
		}
	}, 1);
});
</script>
<?php

include 'tpl.footer.php';
