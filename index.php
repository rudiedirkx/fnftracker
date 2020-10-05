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
	$id = Source::insert([
		'name' => trim($_POST['name']),
		'f95_id' => trim($_POST['f95_id']),
	]);

	$source = Source::find($id);
	$source->sync();

	setcookie('hilite_source', $source->id);

	return do_redirect('index');
}

if ( isset($_GET['sync']) ) {
	$source = Source::find($_GET['sync']);
	$source->sync();

	setcookie('hilite_source', $source->id);

	return do_redirect('index');
}

$hilite = $_COOKIE['hilite_source'] ?? 0;
setcookie('hilite_source', 0, 1);

include 'tpl.header.php';

$sources = Source::all("active = '1' ORDER BY active DESC, name ASC");
Source::eager('last_fetch', $sources);

$inactive = Source::count("active = '0'");

?>
<style>
body {
	font-family: sans-serif;
}
body.show-banner {
	/*--transparency: 0.75;*/
	background: none center 0 no-repeat;
	background-image: /*linear-gradient(rgba(255, 255, 255, var(--transparency)), rgba(255, 255, 255, var(--transparency))),*/ var(--banner);
	background-size: contain;
	background-attachment: fixed;
}
table {
	background-color: rgba(255, 255, 255, 0.75);
	border-collapse: collapse;
}
th, td {
	border: solid 1px #999;
	border-width: 1px 0;
	padding: 4px 6px;
	text-align: left;
}
th:first-child:last-child {
	text-align: center;
}
tr.hilited {
	background: #c8e5ee;
}
.recent-release {
	color: green;
	font-weight: bold;
}
.not-release-date {
	color: orange;
}
.version {
	font-family: monospace;
}
.version:not(:focus) {
	/*display: inline-block;*/
	max-width: 6em;
	overflow: hidden;
	text-overflow: ellipsis;
}
.inactives {
	text-align: center;
	background-color: #eee;
}
</style>

<form method="post" action>
	<table border="1">
		<thead>
			<tr>
				<th colspan="5">Sources (<?= count($sources) ?>)</th>
			</tr>
			<tr>
				<th></th>
				<th>Title</th>
				<th data-sortable>Latest release</th>
				<th data-sortable>Version</th>
				<th data-sortable="asc">Last checked</th>
			</tr>
		</thead>
		<tbody>
			<? foreach ($sources as $source): ?>
				<tr class="<?= $hilite == $source->id ? 'hilited' : '' ?>" data-banner="<?= html($source->banner_url) ?>">
					<td><input type="checkbox" name="enabled[]" value="<?= $source->id ?>" <?= $source->active ? 'checked' : '' ?> /></td>
					<td><?= html($source->name) ?></td>
					<td nowrap class="<?= $source->released_recently ? 'recent-release' : '' ?> <?= $source->not_release_date ? 'not-release-date' : '' ?>">
						<?= $source->last_fetch ? ($source->last_fetch->release_date ?? $source->last_fetch->thread_date ?? '?') : '' ?>
						<a href="?sync=<?= $source->id ?>">&#8635;</a>
					</td>
					<td nowrap class="version" tabindex="-1">
						<span><?= $source->last_fetch->version ?? '' ?></span>
					</td>
					<td nowrap>
						<? if ($source->last_fetch): ?>
							<?= date('Y-m-d', $source->last_fetch->created_on) ?>
							<a target="_blank" href="<?= html($source->last_fetch->url) ?>">&#10132;</a>
						<? endif ?>
					</td>
				</tr>
			<? endforeach ?>
		</tbody>
		<? if ($inactive): ?>
			<tbody>
				<tr>
					<td colspan="5" class="inactives">
						... <?= $inactive ?> inactive titles ...
					</td>
				</tr>
			</tbody>
		<? endif ?>
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
window.addEventListener('load', function() {
	const URL_PATTERN = /^<?= strtr(preg_quote(F95_URL, '/'), [
		preg_quote('{name}') => '[^\/\.]+',
		preg_quote('{id}') => '(\d+)',
	]) ?>/;
	document.querySelector('input[name="f95_id"]').addEventListener('paste', function(e) {
		// console.log(e.clipboardData.getData('text'));
		setTimeout(() => {
			const m = this.value.match(URL_PATTERN);
			if (m) {
				this.value = m[1];
			}
		}, 1);
	});
});
window.addEventListener('load', function() {
	const handle = function(e) {
		const i = this.cellIndex;
		const tbody = this.closest('table').tBodies[0];
		let rows = Array.from(tbody.rows);
		rows.sort((a, b) => a.cells[i].textContent < b.cells[i].textContent ? 1 : -1);
		this.dataset.sortable === 'asc' && (rows = rows.reverse());
		rows.forEach(row => tbody.append(row));
	};
	document.querySelectorAll('th[data-sortable]').forEach(el => el.addEventListener('click', handle));
});
window.addEventListener('load', function() {
	const body = document.body;
	const over = function(e) {
		const url = this.dataset.banner;
		if (!url) return;
		body.style.setProperty('--banner', `url('${url}')`);
		body.classList.add('show-banner');
	};
	const out = function(e) {
		body.classList.remove('show-banner');
	};
	document.querySelectorAll('tr[data-banner]').forEach(el => {
		el.addEventListener('mouseover', over);
		el.addEventListener('mouseout', out);
	});
});
window.addEventListener('load', function() {
	const el = document.querySelector('.hilited');
	el && el.scrollIntoViewIfNeeded();
});
</script>
<?php

include 'tpl.footer.php';
