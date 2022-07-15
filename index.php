<?php

use GuzzleHttp\Exception\TransferException;
use Intervention\Image\ImageManagerStatic;
use rdx\f95\Character;
use rdx\f95\IndexContent;
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
// print_r($_POST);
// print_r($source->characters);
// exit;

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
		if (is_numeric($x) && is_numeric($y) && is_numeric($s)) {
			$img = ImageManagerStatic::make($_FILES['char_file']['tmp_name']);
			$img->crop(max(0, $s), max(0, $s), max(0, $x), max(0, $y));
			$img->resize(200, 200);
			// echo $img->response('jpg', 70);
			$img->save($filepath = __DIR__ . '/' . CHARS_DIR . '/' . $id . '.jpg');
			@chmod($filepath, 0666);
		}
	}

	foreach ($_POST['char'] ?? [] as $id => $char) {
		if (strlen(trim($char['name'])) && isset($source->characters[$id])) {
			$source->characters[$id]->update($char);
		}
	}

	$delete = (array) ($_POST['char_delete'] ?? []);
	foreach ($source->characters as $char) {
		if (in_array($char->id, $delete)) {
			$char->deleteImage();
			$char->delete();
		}
	}

	return do_redirect('index#characters-form-add', ['edit' => $source->id]);
}

if ( isset($_GET['sync']) ) {
	$source = Source::find($_GET['sync']);
	$source->sync();

	setcookie('hilite_source', $source->id);

	return do_redirect('index');
}

$hilite = $_COOKIE['hilite_source'] ?? 0;
if ($hilite) setcookie('hilite_source', 0, 1);

$index = IndexContent::fromSearch(trim($_GET['search'] ?? ''));
$index->hiliteSource = $hilite;
$index->eagerLoad();
$index->setTotals(
	Source::count('1=1'),
	Release::count('source_id in (select source_id from releases group by source_id having count(1) > 1)'),
);

if ( ($_SERVER['HTTP_ACCEPT'] ?? '') == 'html/partial' ) {
	include 'tpl.tables.php';
	exit;
}

include 'tpl.header.php';

$patreons = array_values(Source::fields('patreon', 'patreon IS NOT NULL ORDER BY patreon'));

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

$sourceRatings = $db->fetch("
	select priority, round((cast(f95_rating as float)) / 10) rating, count(1) num_sources
	from sources
	where f95_rating is not null
	group by priority, rating
	order by rating desc, priority desc
")->all();
// print_r($sourceRatings);
$sourceRatingsGroups = array_reduce($sourceRatings, function(array $grid, $source) {
	$grid[$source->priority][(int) $source->rating] = $source->num_sources;
	return $grid;
}, []);
// print_r($sourceRatingsGroups);

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
				<a class="search-icon" href data-query="<?= html($edit->name) ?>">&#128270;</a>
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
			<legend>Characters (<?= count($edit->characters) ?>)</legend>

			<table class="characters">
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
							<td><input class="text" name="char[<?= $char->id ?>][name]" value="<?= html($char->name) ?>" /></td>
							<td><input class="text" name="char[<?= $char->id ?>][role]" value="<?= html($char->role) ?>" /></td>
							<td><input type="checkbox" name="char_delete[]" value="<?= $char->id ?>" /></td>
						</tr>
					<? endforeach ?>
				</tbody>
			</table>

			<br>

			<fieldset>
				<a id="characters-form-add" style="position: relative; top: -200px"></a>
				<legend>Add</legend>

				<p>Name: <input name="char_name" /></p>
				<p>Role: <input name="char_role" list="dl-char-roles" /></p>
				<p>
					<input name="char_file" type="file" />
					<input name="char_cutout" tabindex="-1" placeholder="cutout coords..." style="opacity: 0.5" />
				</p>
				<p><button>Save</button></p>
				<div id="char_image"></div>
			</fieldset>

			<datalist id="dl-char-roles">
				<? $roles = array_filter(array_unique(array_column($edit->characters, 'role'))) ?>
				<? natcasesort($roles) ?>
				<? foreach ($roles as $role): ?>
					<option value="<?= html($role) ?>">
				<? endforeach ?>
			</datalist>
		</fieldset>
	</form>
<? endif ?>

<br>

<div id="stats" style="display: flex; overflow-x: auto">
	<?php include 'tpl.stats.php' ?>
</div>

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
