<?php

use GuzzleHttp\Exception\TransferException;
use Intervention\Image\ImageManagerStatic;
use rdx\f95\Character;
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

if ( isset($_GET['edit'], $_POST['char_name'], $_POST['char_role'], $_FILES['char_file'], $_POST['char_cutout']) ) {
	$source = Source::find($_GET['edit']);

	$id = null;
	if (strlen(trim($_POST['char_name']))) {
		$id = Character::insert([
			'source_id' => $source->id,
			'name' => $_POST['char_name'],
			'role' => $_POST['char_role'],
		]);
	}

	if ($id && $_FILES['char_file']['tmp_name'] && !$_FILES['char_file']['error']) {
		[$x, $y, $s] = explode(',', $_POST['char_cutout'] . ',,');
		if ($x && $y && $s) {
			$img = ImageManagerStatic::make($_FILES['char_file']['tmp_name']);
			$img->crop($s, $s, $x, $y);
			$img->resize(200, 200);
			// echo $img->response('jpg', 70);
			$img->save($filepath = __DIR__ . '/' . CHARS_DIR . '/' . $id . '.jpg');
			@chmod($filepath, 0666);
		}
	}

	$delete = (array) ($_POST['char_delete'] ?? []);
	foreach ($source->characters as $char) {
		if (in_array($char->id, $delete)) {
			$char->deleteImage();
			$char->delete();
		}
	}

	return do_redirect('index', ['edit' => $source->id]);
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
$unrecentChanges = count($changesGrouped[0] ?? []);
$recentChanges = count($changes) - $unrecentChanges;

$releaseStatsGroups = array_reduce($sources, function(array $grid, Source $source) {
	if ($source->num_releases) {
		isset($grid[$source->priority][$source->num_releases]) or $grid[$source->priority][$source->num_releases] = 0;
		$grid[$source->priority][$source->num_releases]++;
	}
	return $grid;
}, []);

$edit = $sources[$_GET['edit'] ?? 0] ?? null;

$mobile = stripos($_SERVER['HTTP_USER_AGENT'], 'mobile') !== false;
$hideUnrecentChanges = $mobile || $edit;
$hideInactiveSources = false || $edit;

?>
<p><input <?= $edit ? '' : 'autofocus' ?> type="search" placeholder="Name &amp; developer..." value="<?= html($_GET['search'] ?? '') ?>" /></p>

<h2>Recent changes (<?= $recentChanges ?> + <?= $unrecentChanges ?>)</h2>

<div class="table-wrapper">
	<table class="changes">
		<thead>
			<tr>
				<th class="title">Title</th>
				<th>Released</th>
				<th class="sorted">Detected</th>
				<th>Version</th>
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
					<tr class="hidden-rows <?= $hideInactiveSources ? 'always' : '' ?>"><td colspan="7">
						... <?= $hideInactiveSources ? "Hiding $inactiveSources sources" : "Show $inactiveSources hidden sources" ?> ...
					</td></tr>
					<? if ($hideInactiveSources) break ?>
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
									<span class="char <? if ($char->public_path): ?>img<? endif ?>">
										<?= html($char->name) ?>
										<? if ($char->role): ?> (<?= html($char->role) ?>)<? endif ?>
									</span>
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
			<p>
				Created: <?= date('Y-m-d H:i', $edit->created_on) ?>
				<a class="goto" target="_blank" href="<?= html($edit->last_release->url) ?>">&#10132;</a>
			</p>
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

<? if ($edit): ?>
	<br>

	<form method="post" action enctype="multipart/form-data">
		<fieldset>
			<legend>Characters</legend>

			<table>
				<thead>
					<tr>
						<th></th>
						<th>Name</th>
						<th>Role</th>
						<th>Delete?</th>
					</tr>
				</thead>
				<tbody>
					<? foreach ($edit->characters as $char): ?>
						<tr>
							<td>
								<? if ($char->public_path): ?>
									<img src="<?= html($char->public_path) ?>" class="char" />
								<? endif ?>
							</td>
							<td><?= html($char) ?></td>
							<td><?= html($char->role) ?></td>
							<td><input type="checkbox" name="char_delete[]" value="<?= $char->id ?>" /></td>
						</tr>
					<? endforeach ?>
				</tbody>
			</table>

			<br>

			<fieldset>
				<legend>Add</legend>

				<p>Name: <input name="char_name" /></p>
				<p>Role: <input name="char_role" /></p>
				<p>
					<input name="char_file" type="file" />
					<input name="char_cutout" type="hidden" />
				</p>
				<p><button>Save</button></p>
				<div id="char_image"></div>
			</fieldset>
		</fieldset>
	</form>
<? endif ?>

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
window.URL_PATTERN = /^<?= strtr(preg_quote(F95_URL, '/'), [
	preg_quote('{name}') => '[^\/\.]+',
	preg_quote('{id}') => '(\d+)',
]) ?>/;
window.PRIORITIES = <?= json_encode(array_keys(Source::PRIORITIES)) ?>;
</script>
<script defer async src="script.js"></script>
<?php

include 'tpl.footer.php';
