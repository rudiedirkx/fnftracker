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

if ( isset($_POST['name'], $_POST['f95_id'], $_POST['developer'], $_POST['patreon'], $_POST['installed'], $_POST['finished'], $_POST['description']) ) {
	$data = [
		'name' => trim($_POST['name']),
		'f95_id' => trim($_POST['f95_id']) ?: null,
		'developer' => trim($_POST['developer']),
		'patreon' => trim($_POST['patreon']) ?: null,
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

if ( isset($_POST['delete_release']) ) {
	Release::find($_POST['delete_release'])->delete();
	echo "Release deleted\n";
	exit;
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

$changes = $sources = [];

$delete = false;
$search = trim($_GET['search'] ?? '');
if ( $search === '*' ) {
	$sql = '1=1';
	$sorted = 'name';
	$sources = Source::all("$sql ORDER BY (f95_id is null) desc, priority DESC, LOWER(REGEXP_REPLACE('^(the|a) ', '', name)) ASC");

	$changesLimit = 0;
	$changes = [];
}
elseif ( strlen($search) ) {
	$query = Source::makeSearchSql($search);
	$delete = $query->delete;
	$sql = $query->source_where;
	$sorted = $query->source_sorted;
	$sources = Source::all("$sql ORDER BY $query->source_order, (f95_id is null) desc, priority DESC, LOWER(REGEXP_REPLACE('^(the|a) ', '', name)) ASC");
	$ids = array_column($sources, 'id');

	$changesLimit = count($sources) <= 3 ? 101 : 11;
	$changes = Release::all("
		source_id in (?) AND source_id in (select source_id from releases group by source_id having count(1) > 1)
		order by first_fetch_on desc
		limit $changesLimit
	", [count($ids) ? $ids : 0]);
}
else {
	$changesLimit = 0;
	$changes = Release::all("
		first_fetch_on > ? AND source_id in (select source_id from releases group by source_id having count(1) > 1)
		order by first_fetch_on desc
	", [strtotime('-' . RECENT0 . ' days')]);
	$_sources = Release::eager('source', $changes);
	Source::eager('characters', $_sources);

	$sql = '(created_on > ? OR f95_id IS NULL)';
	$sorted = 'created_on';
	$sources = Source::all("$sql ORDER BY (f95_id is null) desc, created_on desc", [CREATED_RECENTLY_ENOUGH]);
}

Source::eager('last_release', $sources);
Source::eager('num_releases', $sources);
Source::eager('characters', $sources);

$patreons = array_values(Source::fields('patreon', 'patreon IS NOT NULL ORDER BY patreon'));

$totalChanges = Release::count('source_id in (select source_id from releases group by source_id having count(1) > 1)');
$totalSources = Source::count('1=1');

if ( ($_SERVER['HTTP_ACCEPT'] ?? '') == 'html/partial' ) {
	include 'tpl.tables.php';
	exit;
}

include 'tpl.header.php';

$releaseStats = Source::query("
	select priority, num_releases, count(1) num
	from (
		select s.id source_id, s.priority, count(r.id) num_releases
		from sources s
		left join releases r on r.source_id = s.id
		group by s.id, s.priority
	) x
	group by priority, num_releases
");
// print_r($releaseStats);
$releaseStatsGroups = array_reduce($releaseStats, function(array $grid, Source $source) {
	$grid[$source->priority][$source->num_releases] = $source->num;
	return $grid;
}, []);
// print_r($releaseStatsGroups);

$edit = Source::find($_GET['edit'] ?? 0);

?>
<p>
	<input <?= $edit ? '' : 'autofocus' ?> type="search" placeholder="Name &amp; developer..." value="<?= html($_GET['search'] ?? '') ?>" />
	<button onclick="return window.open('https://google.com/search?q=site:<?= F95_HOST ?>+' + encodeURIComponent(this.previousElementSibling.value)), false">&#128269;</button>
</p>

<div id="tables">
	<? include 'tpl.tables.php' ?>
</div>

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
		<p>Developer: <input name="developer" value="<?= html($edit->developer ?? '') ?>" /></p>
		<p>Patreon: <input name="patreon" value="<?= html($edit->patreon ?? '') ?>" list="dl-patreons" /></p>
		<p>Installed version: <input name="installed" value="<?= html($edit->installed ?? '') ?>" autocomplete="off" list="dl-versions" /></p>
		<p>Finished: <input name="finished" type="date" value="<?= html($edit->finished ?? '') ?>" /></p>
		<p><textarea name="description" cols="35" rows="3" placeholder="Description..."><?= html($edit->description ?? '') ?></textarea></p>
		<p><button>Save</button></p>
	</fieldset>

	<datalist id="dl-patreons">
		<? foreach ($patreons as $patreon): ?>
			<option value="<?= html($patreon) ?>">
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
					<th data-pr-search="p=<?= $prio ?>"><?= array_sum($releaseStatsGroups[$prio] ?? []) ?></th>
				<? endforeach ?>
			</tr>
		</thead>
		<tbody>
			<? for ($r = 1; $r <= $mr; $r++): ?>
				<tr data-releases="<?= $r ?>">
					<th data-pr-search="r=<?= $r ?>"><?= $r ?>x</th>
					<? foreach (array_reverse(array_keys(Source::PRIORITIES)) as $prio): ?>
						<td data-pr-search="p=<?= $prio ?> r=<?= $r ?>" data-priority="<?= $prio ?>" class="priority"><?= $releaseStatsGroups[$prio][$r] ?? '' ?></td>
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
<script defer async src="<?= html_asset('script.js') ?>"></script>
<?php

include 'tpl.footer.php';
