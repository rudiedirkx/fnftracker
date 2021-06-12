<?php

use GuzzleHttp\Exception\TransferException;
use rdx\f95\Release;
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
		'f95_id' => trim($_POST['f95_id']) ?: null,
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
		try {
			$source->sync(2);
		}
		catch (TransferException $ex) {}
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

$sources = Source::all("1=1 ORDER BY (f95_id is null) desc, priority DESC, LOWER(REGEXP_REPLACE('^(the|a) ', '', name)) ASC");
Source::eager('last_release', $sources);
Source::eager('num_releases', $sources);
Source::eager('characters', $sources);
$sourcesGrouped = aro_group($sources, 'draft_or_priority');
$inactiveSources = count($sourcesGrouped[0] ?? []);
$activeSources = count($sources) - $inactiveSources;
$sourcesPriorities = array_map('count', aro_group($sources, 'priority'));

$developers = array_values(array_unique(array_filter(array_column($sources, 'developer'))));
natcasesort($developers);

$notNulls = "coalesce(release_date, thread_date) is not null and version is not null";
$changes = Release::all("
	source_id in (select source_id from releases group by source_id having count(1) > 1)
	order by first_fetch_on desc
");
$changesGrouped = aro_group($changes, 'fetch_recency');
$unrecentChanges = count($changesGrouped[0]);
$recentChanges = count($changes) - $unrecentChanges;

$releaseStatsGroups = array_reduce($sources, function(array $grid, Source $source) {
	if ($source->num_releases) {
		isset($grid[$source->priority][$source->num_releases]) or $grid[$source->priority][$source->num_releases] = 0;
		$grid[$source->priority][$source->num_releases]++;
	}
	return $grid;
}, []);

$mobile = stripos($_SERVER['HTTP_USER_AGENT'], 'mobile') !== false;
$hideUnrecentChanges = $mobile;
$hideInactiveSources = false;

$edit = $sources[$_GET['edit'] ?? 0] ?? null;

?>
<p><input type="search" placeholder="Name &amp; developer..." value="<?= html($_GET['search'] ?? '') ?>" /></p>

<h2>Recent changes (<?= $recentChanges ?> + <?= $unrecentChanges ?>)</h2>

<div class="table-wrapper">
	<table class="changes">
		<thead>
			<tr>
				<th class="title">Title</th>
				<th data-sortable>Released</th>
				<th data-sortable class="sorted">Detected</th>
				<th data-sortable="asc">Version</th>
			</tr>
		</thead>
		<? foreach ($changesGrouped as $group => $objects): ?>
			<tbody>
				<? if ($group == 0): ?>
					<tr class="hidden-rows <?= $hideUnrecentChanges ? 'always' : '' ?>"><td colspan="4">
						... <?= $hideUnrecentChanges ? 'Hiding ' . $unrecentChanges . ' history' : 'Show ' . $unrecentChanges . ' hidden history' ?> ...
					</td></tr>
					<? if ($hideUnrecentChanges) break ?>
				<? endif ?>
				<? foreach ($objects as $fetch): ?>
					<tr
						class="<?= $fetch->status_prefix_class ?> <? if ($fetch->source->description || count($fetch->source->characters)): ?>has-description<? endif ?>"
						data-search="<?= html(mb_strtolower(trim("{$fetch->source->name} {$fetch->source->description} {$fetch->source->developer}"))) ?>"
						data-banner="<?= html($fetch->source->banner_url) ?>"
						data-priority="<?= $fetch->source->priority ?>"
					>
						<td class="with-priority title">
							<span class="title-name"><?= html($fetch->source->name) ?></span>
							<? if ($fetch->source->installed): ?>
								<span class="installed-version">(<?= html($fetch->source->installed) ?>)</span>
							<? endif ?>
							<a class="search-icon" href>&#128270;</a>
							<a class="edit-icon" href="?edit=<?= $fetch->source_id ?>">&#9998;</a>
							<?if ($fetch->source->developer): ?>
								<span class="developer"><?= html($fetch->source->pretty_developer) ?></span>
								<a class="search-icon" href data-query="<?= html($fetch->source->pretty_developer) ?>">&#128270;</a>
							<? endif ?>
							<span class="pstatus"></span>
							<? if ($fetch->software_prefix_label): ?>
								<span class="psoftware"><?= $fetch->software_prefix_label ?></span>
							<? endif ?>
						</td>
						<td nowrap class="recent-<?= $fetch->recent_release ?>"><?= $fetch->release_date ?? $fetch->thread_date ?></td>
						<td nowrap title="<?= date('H:i', $fetch->first_fetch_on) ?>">
							<div class="cols">
								<span><?= date('Y-m-d', $fetch->first_fetch_on) ?></span>
								<a class="goto" target="_blank" href="<?= html($fetch->url) ?>">&#10132;</a>
							</div>
						</td>
						<td nowrap tabindex="0" class="version"><span><?= $fetch->cleaned_version ?></span></td>
					</tr>
				<? endforeach ?>
			</tbody>
		<? endforeach ?>
	</table>
</div>

<h2>Sources (<?= $activeSources ?> + <?= $inactiveSources ?>)</h2>

<form method="post" action class="table-wrapper">
	<table class="sources">
		<thead>
			<tr>
				<th></th>
				<th class="title sorted">Title</th>
				<th data-sortable>Latest release</th>
				<th data-sortable="asc">Version</th>
				<th data-sortable="asc">Last checked</th>
				<th data-sortable>Added</th>
				<th data-sortable class="finished">Finished</th>
			</tr>
		</thead>
		<? foreach ($sourcesGrouped as $group => $objects): ?>
			<tbody>
				<? if ($group == 0): ?>
					<tr class="hidden-rows"><td colspan="7">
						... Show <?= $inactiveSources ?> hidden sources ...
					</td></tr>
				<? endif ?>
				<? foreach ($objects as $source): ?>
					<tr
						class="<?= $hilite == $source->id ? 'hilited' : '' ?> <?= $source->status_prefix_class ?? '' ?> <? if ($source->description || count($source->characters)): ?>has-description<? endif ?>"
						data-id="<?= $source->id ?>"
						data-search="_p<?= $source->priority ?>_r<?= $source->num_releases ?? 0 ?>_ <?= html(mb_strtolower(trim("$source->name $source->description $source->developer"))) ?>"
						data-banner="<?= html($source->banner_url) ?>"
						data-priority="<?= $source->priority ?>"
						data-releases="<?= $source->num_releases ?? 0 ?>"
					>
						<td class="priority">
							<input type="hidden" name="priorities[<?= $source->id ?>]" value="<?= $source->priority ?>" />
							<output><?= $source->priority ?></output>
						</td>
						<td class="title">
							<span class="title-name"><?= html($source->name) ?></span>
							<? if ($source->installed): ?>
								<span class="installed-version">(<?= html($source->installed) ?>)</span>
							<? endif ?>
							<a class="search-icon" href>&#128270;</a>
							<a class="edit-icon" href="?edit=<?= $source->id ?>">&#9998;</a>
							<?if ($source->developer): ?>
								<span class="developer"><?= html($source->pretty_developer) ?></span>
								<a class="search-icon" href data-query="<?= html($source->pretty_developer) ?>">&#128270;</a>
							<? endif ?>
							<span class="pstatus"></span>
							<? if ($source->last_release->software_prefix_label ?? null): ?>
								<span class="psoftware"><?= $source->last_release->software_prefix_label ?></span>
							<? endif ?>
						</td>
						<td nowrap class="recent-<?= $source->last_release->recent_release ?? '' ?> <?= $source->not_release_date ? 'not-release-date' : '' ?> old-last-change-<?= $source->old_last_change ?>">
							<div class="cols">
								<span><?= $source->last_release->release_date ?? $source->last_release->thread_date ?? '' ?></span>
								<a class="sync" href="?sync=<?= $source->id ?>">&#8635;</a>
							</div>
						</td>
						<td nowrap tabindex="0" class="version"><span><?= $source->last_release->cleaned_version ?? '' ?></span></td>
						<td nowrap>
							<? if ($source->last_release): ?>
								<div class="cols">
									<span><?= date('Y-m-d', $source->last_release->last_fetch_on) ?></span>
									<a class="goto" target="_blank" href="<?= html($source->last_release->url) ?>">&#10132;</a>
								</div>
							<? endif ?>
						</td>
						<td nowrap>
							<?= date('Y-m-d', $source->created_on) ?>
						</td>
						<td class="finished" nowrap>
							<?= $source->finished ?>
						</td>
					</tr>
					<? if ($source->description || count($source->characters)): ?>
						<tr class="description">
							<td colspan="11">
								<?= html($source->description) ?>
								<? foreach ($source->characters as $char): ?>
									|
									<!-- <? if ($char->url): ?><a target="_blank" href="<?= html($char->url) ?>"><? endif ?> -->
										<?= html($char->name) ?>
										<? if ($char->role): ?> (<?= html($char->role) ?>)<? endif ?>
									<!-- <? if ($char->url): ?></a><? endif ?> -->
								<? endforeach ?>
							</td>
						</tr>
					<? endif ?>
				<? endforeach ?>
			</tbody>
		<? endforeach ?>
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
		<p>F95 ID: <input name="f95_id" pattern="^\d+$" value="<?= html($edit->f95_id ?? '') ?>" /></p>
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

<br>

<fieldset>
	<legend>Release stats</legend>
	<? $mr = max(array_keys(array_replace(...$releaseStatsGroups))) ?>
	<table class="release-stats">
		<thead>
			<tr>
				<th></th>
				<? foreach (array_reverse(array_keys(Source::PRIORITIES)) as $prio): ?>
					<th><?= $sourcesPriorities[$prio] ?? 0 ?></th>
				<? endforeach ?>
			</tr>
		</thead>
		<tbody>
			<? for ($r = 1; $r <= $mr; $r++): ?>
				<tr data-releases="<?= $r ?>">
					<th><?= $r ?>x</th>
					<? foreach (array_reverse(array_keys(Source::PRIORITIES)) as $prio): ?>
						<td data-priority="<?= $prio ?>" class="priority">
							<?= $releaseStatsGroups[$prio][$r] ?? '' ?>
						</td>
					<? endforeach ?>
				</tr>
			<? endfor ?>
		</tbody>
	</table>
</fieldset>

<script>
window.addEventListener('load', e => setTimeout(() => {
	const body = document.body;

	const debouce = function(delay, callback) {
		var timer = -1;
		return function(...args) {
			clearTimeout(timer);
			timer = setTimeout(() => callback.apply(this, args), delay);
		};
	};

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

	const sortHandle = function(e) {
		const i = this.cellIndex;
		const tbl = this.closest('table');
		let rows = Array.from(tbl.querySelectorAll('tbody > tr:not(.hidden-rows):not(.description)'));
		rows.sort((a, b) => a.cells[i].textContent < b.cells[i].textContent ? 1 : -1);
		this.dataset.sortable === 'asc' && (rows = rows.reverse());
		rows.forEach(tr => (tr.nextElementSibling && tr.nextElementSibling.classList.contains('description') ? [tr, tr.nextElementSibling] : [tr]).forEach(tr => tr.parentNode.append(tr)));
		tbl.querySelectorAll('.sorted').forEach(th => th.classList.remove('sorted'));
		this.classList.add('sorted');
	};
	document.querySelectorAll('th[data-sortable]').forEach(el => el.addEventListener('click', sortHandle));

	const PRIORITIES = <?= json_encode(array_keys(Source::PRIORITIES)) ?>;
	const priorityHandle = function(e) {
		const tr = this.closest('tr');
		const i = PRIORITIES.indexOf(parseInt(this.textContent.trim()));
		const nxt = PRIORITIES[(i-1+PRIORITIES.length) % PRIORITIES.length];
		this.querySelector('output').value = this.querySelector('input').value = tr.dataset.priority = nxt;
	};
	document.querySelectorAll('.sources td.priority').forEach(el => el.addEventListener('click', priorityHandle));

	const hiddenHandle = function(e) {
		this.closest('table').classList.add('showing-hidden-rows');
		this.closest('tr').remove();
	};
	document.querySelectorAll('tr.hidden-rows:not(.always) td').forEach(el => el.addEventListener('click', hiddenHandle));

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

	const search = document.querySelector('input[type="search"]');
	search.addEventListener('input', debouce(200, function(e) {
		const q = this.value.toLowerCase().replace(/(^[\s|]+|[\s|]+$)/g, '');
		const re = new RegExp(q, 'i');
		document.body.classList.toggle('searching', q != '');
		const rows = document.querySelectorAll('tr[data-search]');
		rows.forEach(tr => tr.hidden = q && !re.test(tr.dataset.search));
	}));
	search.dispatchEvent(new CustomEvent('input'));
	document.addEventListener('keyup', function(e) {
		if (e.code == 'Slash' && document.activeElement.matches('body, a, button, td[tabindex]')) {
			search.focus();
			search.select();
		}
	});
	const searchHandle = function(e) {
		e.preventDefault();
		search.value = this.dataset.query || this.closest('tr').querySelector('.title-name').textContent.split('(')[0].trim();
		search.focus();
		search.dispatchEvent(new CustomEvent('input'));
	};
	document.querySelectorAll('.search-icon').forEach(el => el.addEventListener('click', searchHandle));

	document.querySelectorAll('tr.description').forEach(el => {
		const name = el.previousElementSibling.querySelector('.title-name');
		var timer = 0;
		name.addEventListener('mouseover', e => {
			clearTimeout(timer);
			timer = setTimeout(() => {
				el.classList.add('show-description');
			}, 250);
		});
		name.addEventListener('mouseout', e => {
			clearTimeout(timer);
			document.querySelectorAll('tr.show-description').forEach(el => el.classList.remove('show-description'));
		});
	});

	document.querySelector('.release-stats').addEventListener('click', e => {
		const td = e.target.closest('tr[data-releases] td[data-priority]');
		search.value = `_p${td.dataset.priority}_r${td.parentNode.dataset.releases}_`;
		search.focus();
		search.dispatchEvent(new CustomEvent('input'));
	});

	Array.from(document.querySelectorAll('.hilited, .hilited *')).some(el => el.focus() || el == document.activeElement);
}, 200));
</script>
<?php

include 'tpl.footer.php';
