<?php

use rdx\f95\Source;

require 'inc.bootstrap.php';

if ( isset($_POST['priorities']) ) {
	$groups = [];
	foreach ( $_POST['priorities'] as $id => $priority ) {
		$groups[$priority][] = $id;
	}

	foreach ( $groups as $priority => $ids ) {
		Source::updateAll(['priority' => $priority], ['id' => $ids]);
	}

	return do_redirect('index');
}

if ( isset($_POST['name'], $_POST['f95_id']) ) {
	$data = [
		'name' => trim($_POST['name']),
		'f95_id' => trim($_POST['f95_id']),
	];

	if ( isset($_POST['id']) ) {
		$id = $_POST['id'];
		$source = Source::find($id);
		$source->update($data);
	}
	else {
		$id = Source::insert($data + ['priority' => max(array_keys(Source::PRIORITIES))]);
		$source = Source::find($id);
		$source->sync();
	}

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

$sources = Source::all("1=1 ORDER BY priority DESC, name ASC");
Source::eager('last_fetch', $sources);

$edit = $sources[$_GET['edit'] ?? 0] ?? null;

?>
<style>
body {
	font-family: sans-serif;
}
a, a:visited {
	color: deeppink;
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
	padding: 3px 4px;
	text-align: left;
}
th:first-child:last-child {
	text-align: center;
}
tr.hilited,
legend.hilited {
	background: #c8e5ee;
}
tr.completed td.title:before,
tr.onhold td.title:before,
tr.abandoned td.title:before {
	content: "C";
	float: right;
	color: royalblue;
	font-weight: bold;
	margin-left: .5em;
}
tr.onhold td.title:before {
	content: "H";
}
tr.abandoned td.title:before {
	content: "A";
}
td > .cols {
	display: flex;
	justify-content: space-between;
}
td > .cols > :last-child {
	margin-left: .5em;
}
tr.new-priority > td {
	border-top-width: 3px;
}
td.priority {
	user-select: none;
	cursor: pointer;
	background-color: pink;
}
tr[data-priority="1"] td.priority {
	background-color: salmon;
}
tr[data-priority="2"] td.priority {
	background-color: red;
}
tr[data-priority="3"] td.priority {
	background-color: darkred;
	color: white;
}
.recent-release {
	color: green;
	font-weight: bold;
}
.not-release-date {
	color: orange;
}
.prefixes {
	font-weight: bold;
	color: royalblue
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
a.goto {
	line-height: 1;
}
@media (max-width: 400px) {
	.hide-on-mobile {
		display: none;
	}
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
				<th class="title">Title</th>
				<th data-sortable>Latest release</th>
				<th data-sortable>Version</th>
				<th data-sortable="asc" class="hide-on-mobile">Last checked</th>
			</tr>
		</thead>
		<tbody>
			<? $prevprio = null ?>
			<? foreach ($sources as $source): ?>
				<tr class="<?= $prevprio && $source->priority != $prevprio ? 'new-priority' : '' ?> <?= $hilite == $source->id ? 'hilited' : '' ?> <?= $source->last_prefix ?>" data-banner="<?= html($source->banner_url) ?>" data-id="<?= $source->id ?>" data-priority="<?= $source->priority ?>">
					<td class="priority">
						<input type="hidden" name="priorities[<?= $source->id ?>]" value="<?= $source->priority ?>" />
						<output><?= $source->priority ?></output>
					</td>
					<td class="title">
						<span class="title-name"><?= html($source->name) ?></span>
						<? if (0 && $source->last_fetch->prefixes ?? null): ?>
							<span class="prefixes">(<?= strtoupper($source->last_fetch->prefixes) ?>)</span>
						<? endif ?>
					</td>
					<td nowrap class="<?= $source->released_recently ? 'recent-release' : '' ?> <?= $source->not_release_date ? 'not-release-date' : '' ?>">
						<div class="cols">
							<span><?= $source->last_fetch ? ($source->last_fetch->release_date ?? $source->last_fetch->thread_date ?? '?') : '' ?></span>
							<a class="sync" href="?sync=<?= $source->id ?>">&#8635;</a>
						</div>
					</td>
					<td nowrap class="version" tabindex="-1">
						<span><?= $source->last_fetch->version ?? '' ?></span>
					</td>
					<td nowrap class="hide-on-mobile">
						<? if ($source->last_fetch): ?>
							<div class="cols">
								<span><?= date('Y-m-d', $source->last_fetch->created_on) ?></span>
								<a class="goto" target="_blank" href="<?= html($source->last_fetch->url) ?>">&#10132;</a>
							</div>
						<? endif ?>
					</td>
				</tr>
				<? $prevprio = $source->priority ?>
			<? endforeach ?>
		</tbody>
	</table>
	<p><button>Save</button></p>
</form>

<br>

<form method="post" action>
	<fieldset>
		<? if ($edit): ?>
			<legend class="hilited">Edit source</legend>
			<input type="hidden" name="id" value="<?= $edit->id ?>" />
		<? else: ?>
			<legend>Add source</legend>
		<? endif ?>
		<p>Name: <input name="name" required value="<?= html($edit->name ?? '') ?>" /></p>
		<p>F95 ID: <input name="f95_id" required pattern="^\d+$" value="<?= html($edit->f95_id ?? '') ?>" /></p>
		<p><button>Save</button></p>
	</fieldset>
</form>

<script>
window.addEventListener('load', function() {
	const URL_PATTERN = /^<?= strtr(preg_quote(F95_URL, '/'), [
		preg_quote('{name}') => '[^\/\.]+',
		preg_quote('{id}') => '(\d+)',
	]) ?>/;
	document.querySelector('input[name="f95_id"]').addEventListener('input', function(e) {
		const m = this.value.match(URL_PATTERN);
		if (m) {
			this.value = m[1];
		}
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
	const PRIORITIES = <?= json_encode(array_keys(Source::PRIORITIES)) ?>;
	const handle = function(e) {
		const tr = this.closest('tr');
		const i = PRIORITIES.indexOf(parseInt(this.textContent.trim()));
		const nxt = PRIORITIES[(i-1+PRIORITIES.length) % PRIORITIES.length];
		this.querySelector('output').value = this.querySelector('input').value = tr.dataset.priority = nxt;
	};
	document.querySelectorAll('td.priority').forEach(el => el.addEventListener('click', handle));
});
window.addEventListener('load', function() {
	const handle = function(e) {
		location.href = '?edit=' + this.closest('tr').dataset.id;
	};
	document.querySelectorAll('.title-name').forEach(el => el.addEventListener('dblclick', handle));
});
window.addEventListener('load', function() {
	const body = document.body;
	const over = function(e) {
		const url = this.closest('tr').dataset.banner;
		if (!url) return;
		body.style.setProperty('--banner', `url('${url}')`);
		body.classList.add('show-banner');
	};
	const out = function(e) {
		body.classList.remove('show-banner');
	};
	document.querySelectorAll('tr[data-banner] span.title-name').forEach(el => {
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
