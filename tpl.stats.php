<?php

use rdx\f95\Source;

?>
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

<fieldset>
	<legend>Source ratings</legend>
	<table class="source-ratings">
		<thead>
			<tr>
				<th></th>
				<? foreach (array_reverse(array_keys(Source::PRIORITIES)) as $prio): ?>
					<th data-pr-search="p=<?= $prio ?>"><?= array_sum($sourceRatingsGroups[$prio] ?? []) ?></th>
				<? endforeach ?>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<? for ($r = 10; $r >= 1; $r--):
				$row = 0;
				?>
				<tr>
					<th data-pr-search="rating=<?= $r ?>"><?= number_format($r, 0) ?></th>
					<? foreach (array_reverse(array_keys(Source::PRIORITIES)) as $prio):
						$row += $sourceRatingsGroups[$prio][$r] ?? 0;
						?>
						<td data-pr-search="p=<?= $prio ?> rating=<?= $r ?>" data-priority="<?= $prio ?>" class="priority"><?= $sourceRatingsGroups[$prio][$r] ?? '' ?></td>
					<? endforeach ?>
					<th><?= $row ?></th>
				</tr>
			<? endfor ?>
		</tbody>
	</table>
	<table class="source-ratings">
		<? foreach ([] ?? $sourceRatings as $row): ?>
			<tr>
				<th><?= round($row->rating) ?></th>
				<td><?= $row->num_sources ?>x</td>
			</tr>
		<? endforeach ?>
	</table>
</fieldset>
