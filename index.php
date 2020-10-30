<?php

use rdx\f95\Fetch;
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

if ( isset($_POST['name'], $_POST['f95_id'], $_POST['developer'], $_POST['installed'], $_POST['finished'], $_POST['description']) ) {
	$data = [
		'name' => trim($_POST['name']),
		'f95_id' => trim($_POST['f95_id']),
		'developer' => trim($_POST['developer']),
		'installed' => trim($_POST['installed']) ?: null,
		'description' => trim($_POST['description']) ?: null,
		'finished' => trim($_POST['finished']) ?: null,
	];

	if ( isset($_POST['id']) ) {
		$id = $_POST['id'];
		$source = Source::find($id);
		$source->update($data);
	}
	else {
		$id = Source::insert($data + [
			'created_on' => time(),
			'priority' => max(array_keys(Source::PRIORITIES)),
		]);
		$source = Source::find($id);
		$source->sync(2);
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

$sources = Source::all("1=1 ORDER BY priority DESC, (CASE WHEN LOWER(SUBSTR(name, 1, 4)) = 'the ' THEN SUBSTR(name, 5) ELSE name END) ASC");
Source::eager('last_fetch', $sources);
$activeSources = count(array_filter($sources, function($source) {
	return $source->priority > 0;
}));

$developers = array_values(array_unique(array_filter(array_column($sources, 'developer'))));

$changes = Fetch::query("
	select
		f.*,
		(select version from fetches where source_id = f.source_id and release_date = f.release_date order by id desc limit 1) version,
		cast(min(created_on) as int) change_on
	from fetches f
	where release_date is not null and source_id in (select source_id from fetches group by source_id having count(distinct release_date) > 1)
	group by source_id, release_date
	order by change_on desc
");
$recentChanges = count(array_filter($changes, function($fetch) {
	return $fetch->is_recent_fetch;
}));

$edit = $sources[$_GET['edit'] ?? 0] ?? null;

?>
<p><input type="search" placeholder="Name &amp; developer..." value="<?= html($_GET['search'] ?? '') ?>" /></p>

<h2>Recent changes (<?= $recentChanges ?>)</h2>

<div class="table-wrapper">
	<table class="changes">
		<thead>
			<tr>
				<th class="title">Title</th>
				<th data-sortable>Released</th>
				<th data-sortable>Detected</th>
				<th data-sortable="asc">Version</th>
			</tr>
		</thead>
		<tbody>
			<? $lastNew = null ?>
			<? foreach ($changes as $fetch): ?>
				<? $new = $fetch->is_recent_fetch ?>
				<? if ($lastNew != null && $lastNew != $new && !$new): ?>
					</tbody>
					<tbody>
					<tr class="hidden-rows"><td colspan="4">
						... Show all history ...
					</td></tr>
				<? endif?>
				<tr
					class="<?= $fetch->prefix ?>"
					data-search="<?= html(mb_strtolower(trim("{$fetch->source->name} {$fetch->source->developer}"))) ?>"
					data-banner="<?= html($fetch->source->banner_url) ?>"
					data-priority="<?= $fetch->source->priority ?>"
				>
					<td class="with-priority title">
						<span class="title-name" title="<?= html($fetch->source->developer) ?>: <?= html($fetch->source->description) ?>"><?= html($fetch->source->name) ?></span>
						<? if ($fetch->source->installed): ?>
							(<?= html($fetch->source->installed) ?>)
						<? endif ?>
						<a class="edit-icon" href="?edit=<?= $fetch->source_id ?>">&#9998;</a>
						<a class="search-icon" href>&#128270;</a>
					</td>
					<td nowrap class="recent-<?= $fetch->recent_release ?>"><?= $fetch->release_date ?></td>
					<td nowrap>
						<div class="cols">
							<span><?= date('Y-m-d', $fetch->change_on) ?></span>
							<a class="goto" target="_blank" href="<?= html($fetch->url) ?>">&#10132;</a>
						</div>
					</td>
					<td nowrap class="version" tabindex="0">
						<span><?= $fetch->cleaned_version ?></span>
					</td>
				</tr>
				<? $lastNew = $new ?>
			<? endforeach ?>
		</tbody>
	</table>
</div>

<h2>Sources (<?= $activeSources ?>)</h2>

<form method="post" action class="table-wrapper">
	<table class="sources">
		<thead>
			<tr>
				<th></th>
				<th class="title">Title</th>
				<th data-sortable>Latest release</th>
				<th data-sortable="asc">Version</th>
				<th data-sortable="asc">Last checked</th>
				<th data-sortable>Added</th>
				<th data-sortable class="finished">Finished</th>
			</tr>
		</thead>
		<tbody>
			<? $prevprio = null ?>
			<? foreach (array_values($sources) as $i => $source): ?>
				<? if ($prevprio && $source->priority != $prevprio && $source->priority == 0): ?>
					</tbody>
					<tbody>
					<tr class="hidden-rows"><td colspan="7">
						... Show <?= count($sources) - $i ?> hidden sources ...
					</td></tr>
				<? endif ?>
				<tr
					class="<?= $hilite == $source->id ? 'hilited' : '' ?> <?= $source->prefix_class ?>"
					data-id="<?= $source->id ?>"
					data-search="<?= html(mb_strtolower(trim("$source->name $source->developer"))) ?>"
					data-banner="<?= html($source->banner_url) ?>"
					data-priority="<?= $source->priority ?>"
				>
					<td class="priority">
						<input type="hidden" name="priorities[<?= $source->id ?>]" value="<?= $source->priority ?>" />
						<output><?= $source->priority ?></output>
					</td>
					<td class="title">
						<span class="title-name" title="<?= html($source->developer) ?>: <?= html($source->description) ?>"><?= html($source->name) ?></span>
						<? if ($source->installed): ?>
							(<?= html($source->installed) ?>)
						<? endif ?>
						<a class="edit-icon" href="?edit=<?= $source->id ?>">&#9998;</a>
						<a class="search-icon" href>&#128270;</a>
					</td>
					<td nowrap class="recent-<?= $source->last_fetch->recent_release ?> <?= $source->not_release_date ? 'not-release-date' : '' ?>">
						<div class="cols">
							<span><?= $source->last_fetch ? ($source->last_fetch->release_date ?? $source->last_fetch->thread_date ?? '') : '' ?></span>
							<a class="sync" href="?sync=<?= $source->id ?>">&#8635;</a>
						</div>
					</td>
					<td nowrap class="version" tabindex="0">
						<span><?= $source->last_fetch->cleaned_version ?? '' ?></span>
					</td>
					<td nowrap>
						<div class="cols">
							<span><?= date('Y-m-d', $source->last_fetch->created_on) ?></span>
							<a class="goto" target="_blank" href="<?= html($source->last_fetch->url) ?>">&#10132;</a>
						</div>
					</td>
					<td nowrap>
						<?= date('Y-m-d', $source->created_on) ?>
					</td>
					<td class="finished" nowrap>
						<?= $source->finished ?>
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
			<p>Created: <?= date('Y-m-d H:i', $edit->created_on) ?></p>
		<? else: ?>
			<legend>Add source</legend>
		<? endif ?>
		<p>Name: <input name="name" required value="<?= html($edit->name ?? '') ?>" <?= $edit ? 'autofocus' : '' ?> /></p>
		<p>F95 ID: <input name="f95_id" required pattern="^\d+$" value="<?= html($edit->f95_id ?? '') ?>" /></p>
		<p>Developer: <input name="developer" value="<?= html($edit->developer ?? '') ?>" list="dl-developers" /></p>
		<p>Installed version: <input name="installed" value="<?= html($edit->installed ?? '') ?>" autocomplete="off" list="dl-versions" /></p>
		<p>Finished: <input name="finished" type="date" value="<?= html($edit->finished ?? '') ?>" /></p>
		<p><textarea name="description" cols="35" rows="3" placeholder="Description..."><?= html($edit->description ?? '') ?></textarea></p>
		<p><button>Save</button></p>
	</fieldset>

	<datalist id="dl-developers">
		<? foreach ($developers as $name): ?>
			<option value="<?= html($name) ?>">
		<? endforeach ?>
	</datalist>

	<? if ($edit): ?>
		<datalist id="dl-versions">
			<? foreach ($edit->versions as $version): ?>
				<option value="<?= html($version) ?>">
			<? endforeach ?>
		</datalist>
	<? endif ?>
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
		let rows = Array.from(this.closest('table').querySelectorAll('tbody > tr:not(.hidden-rows)'));
		rows.sort((a, b) => a.cells[i].textContent < b.cells[i].textContent ? 1 : -1);
		this.dataset.sortable === 'asc' && (rows = rows.reverse());
		rows.forEach(tr => tr.parentNode.append(tr));
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
		this.closest('table').classList.add('showing-hidden-rows');
		this.closest('tr').remove();
	};
	document.querySelectorAll('tr.hidden-rows td').forEach(el => el.addEventListener('click', handle));
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
	const search = document.querySelector('input[type="search"]');
	search.addEventListener('input', function(e) {
		const q = this.value.toLowerCase().replace(/(^[\s|]+|[\s|]+$)/g, '');
		const re = new RegExp(q, 'i');
		document.body.classList.toggle('searching', q != '');
		const rows = document.querySelectorAll('tr[data-search]');
		rows.forEach(tr => tr.hidden = q && !re.test(tr.dataset.search));
	});
	search.dispatchEvent(new CustomEvent('input'));
	document.addEventListener('keyup', function(e) {
		if (e.code == 'Slash' && document.activeElement.matches('body, a, button')) {
			search.focus();
			search.select();
		}
	});
	const handle = function(e) {
		e.preventDefault();
		search.value = this.closest('tr').querySelector('.title-name').textContent.trim();
		search.focus();
		search.dispatchEvent(new CustomEvent('input'));
	};
	document.querySelectorAll('.search-icon').forEach(el => el.addEventListener('click', handle));
});
window.addEventListener('load', function() {
	const el = document.querySelector('.hilited');
	el && el.scrollIntoViewIfNeeded();
});
</script>
<?php

include 'tpl.footer.php';
