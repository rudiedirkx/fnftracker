<!-- <?= str_replace('-->', '', $sql) ?> -->

<h2>Recent changes (<?= $changesLimit && $changesLimit == count($changes) ? ($changesLimit-1) . '+' : count($changes) ?> / <?= $totalChanges ?>)</h2>

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
		<tbody>
			<? foreach ($changes as $fetch): ?>
				<tr
					class="<?= $hilite == $fetch->source_id ? 'hilited' : '' ?> <?= $fetch->status_prefix_class ?> recency-<?= $fetch->fetch_recency ?> <? if ($fetch->source->title_title): ?>has-description<? endif ?>"
					data-banner="<?= html($fetch->source->banner_url) ?>"
					data-priority="<?= $fetch->source->priority ?>"
				>
					<td class="with-priority title">
						<span class="title-name" title="<?= html($fetch->source->title_title) ?>"><?= html($fetch->source->name) ?></span>
						<? if ($fetch->source->installed): ?>
							<span class="installed-version">(<?= html($fetch->source->installed) ?>)</span>
						<? endif ?>
						<a class="search-icon" href>&#128270;</a>
						<a class="edit-icon" href="?edit=<?= $fetch->source_id ?>">&#9998;</a>
						<?if ($fetch->source->developer): ?>
							<span class="developer" title="Patreon: <?= html($fetch->source->pretty_patreon ?: '?') ?>"><?= html($fetch->source->pretty_developer) ?></span>
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
					<td nowrap tabindex="0" class="version" title="<?= html($fetch->version) ?>"><span><?= html($fetch->cleaned_version) ?></span></td>
				</tr>
			<? endforeach ?>
		</tbody>
	</table>
</div>

<h2>Sources (<?= count($sources) ?> / <?= $totalSources ?>)</h2>

<form method="post" action class="table-wrapper">
	<table class="sources">
		<thead>
			<tr>
				<th></th>
				<th class="title sorted">Title</th>
				<th></th>
				<th data-sortable>Latest release</th>
				<th data-sortable="asc">Version</th>
				<th data-sortable="asc">Last checked</th>
				<th data-sortable>Added</th>
				<th data-sortable class="finished">Finished</th>
			</tr>
		</thead>
		<tbody>
			<? foreach ($sources as $source): ?>
				<tr
					class="<?= $hilite == $source->id ? 'hilited' : '' ?> recency-<?= $source->last_release->fetch_recency ?? '' ?> <?= $source->status_prefix_class ?? '' ?> <? if ($source->title_title): ?>has-description<? endif ?>"
					data-id="<?= $source->id ?>"
					data-banner="<?= html($source->banner_url) ?>"
					data-priority="<?= $source->priority ?>"
					data-releases="<?= $source->num_releases ?? 0 ?>"
				>
					<td class="priority">
						<input type="hidden" name="priorities[<?= $source->id ?>]" value="<?= $source->priority ?>" />
						<output><?= $source->priority ?></output>
					</td>
					<td class="title">
						<span class="title-name" title="<?= html($source->title_title) ?>"><?= html($source->name) ?></span>
						<? if ($source->installed): ?>
							<span class="installed-version">(<?= html($source->installed) ?>)</span>
						<? endif ?>
						<a class="search-icon" href>&#128270;</a>
						<a class="edit-icon" href="?edit=<?= $source->id ?>">&#9998;</a>
						<?if ($source->developer): ?>
							<span class="developer" title="Patreon: <?= html($source->pretty_patreon ?: '?') ?>"><?= html($source->pretty_developer) ?></span>
							<a class="search-icon" href data-query="<?= html($source->pretty_developer) ?>">&#128270;</a>
						<? endif ?>
						<span class="pstatus"></span>
						<? if ($source->last_release->software_prefix_label ?? null): ?>
							<span class="psoftware"><?= $source->last_release->software_prefix_label ?></span>
						<? endif ?>
					</td>
					<td>
						<?if ($source->developer && $source->patreon): ?>
							<a href="https://www.patreon.com/<?= html($source->pretty_patreon) ?>" target="_blank"><img src="patreon.png" alt="Patreon" /></a>
						<? endif ?>
					</td>
					<td nowrap class="recent-<?= $source->last_release->recent_release ?? '' ?> <?= $source->not_release_date ? 'not-release-date' : '' ?> old-last-change-<?= $source->old_last_change ?>">
						<div class="cols">
							<span><?= $source->last_release->release_date ?? $source->last_release->thread_date ?? '' ?></span>
							<a class="sync" href="?sync=<?= $source->id ?>">&#8635;</a>
						</div>
					</td>
					<td nowrap tabindex="0" class="version" title="<?= html($source->last_release->version) ?>"><span><?= $source->last_release->cleaned_version ?? '' ?></span></td>
					<td nowrap>
						<? if ($source->last_release): ?>
							<div class="cols">
								<span><?= date('Y-m-d', $source->last_release->last_fetch_on) ?></span>
								<a class="goto" target="_blank" href="<?= html($source->last_release->url) ?>">&#10132;</a>
							</div>
						<? endif ?>
					</td>
					<td nowrap class="created-<?= $source->created_recency ?>">
						<?= date('Y-m-d', $source->created_on) ?>
					</td>
					<td class="finished" nowrap>
						<?= $source->finished ?>
					</td>
				</tr>
			<? endforeach ?>
		</tbody>
	</table>
	<p><button>Save</button></p>
</form>
