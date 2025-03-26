<!-- sources: <?= str_replace('-->', '', $index->sourcesSql) ?> -->

<h2>Recent releases (<?= $index->getReleasesCountLabel() ?>)</h2>

<div class="table-wrapper">
	<table class="changes">
		<thead>
			<tr>
				<th class="title">Title</th>
				<th>Released</th>
				<th class="<?= $index->releasesSorted == 'first_fetch_on' ? 'sorted' : '' ?>">Detected</th>
				<th>Version</th>
				<? if ($index->deleting): ?>
					<th></th>
				<? endif ?>
			</tr>
		</thead>
		<tbody>
			<? foreach ($index->releases as $fetch): ?>
				<tr
					class="
						<?= $index->hiliteSource == $fetch->source_id ? 'hilited' : '' ?>
						<?= $fetch->status_prefix_class ?>
						recency-<?= $fetch->fetch_recency ?>
						<?= $fetch->source->title_class ?>
					"
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
						<? if ($fetch->source->f95_rating): ?>
							<span class="rating">&#9733;<?= round($fetch->source->f95_rating/10) ?></span>
						<? endif ?>
						<?if ($fetch->source->developer): ?>
							<span class="developer" title="Patreon: <?= html($fetch->source->pretty_patreon ?: '?') ?>"><?= html($fetch->source->pretty_developer) ?></span>
							<a class="search-icon" href data-query="<?= html($fetch->source->pretty_developer) ?>">&#128270;</a>
						<? endif ?>
						<span class="pstatus"></span>
						<? if ($fetch->software_prefix_label): ?>
							<span class="psoftware"><?= $fetch->software_prefix_label ?></span>
						<? endif ?>
					</td>
					<td nowrap class="<?= $fetch->not_release_date ? 'not-release-date' : '' ?> recent-<?= $fetch->recent_release ?> old-last-change-<?= $fetch->old_last_change ?>" title="<?= html($fetch->thread_date) ?>">
						<?php if ($index->editing && $fetch->release_date): ?>
							<input class="editing-release" data-name="release_date" data-fetch="<?= $fetch->id ?>" value="<?= html($fetch->release_date) ?>">
						<?php else: ?>
							<?= $fetch->release_date ?? $fetch->thread_date ?>
						<?php endif ?>
					</td>
					<td nowrap title="<?= date('H:i', $fetch->first_fetch_on) ?>">
						<div class="cols">
							<span><?= date('Y-m-d', $fetch->first_fetch_on) ?></span>
							<a class="goto" target="_blank" href="<?= html($fetch->url) ?>">&#10132;</a>
						</div>
					</td>
					<td nowrap tabindex="0" class="version" title="<?= html($fetch->version) ?>"><span><?= html($fetch->cleaned_version) ?></span></td>
					<? if ($index->deleting): ?>
						<td><a href data-body="delete_release=<?= $fetch->id ?>" class="delete">x</a></td>
					<? endif ?>
				</tr>
			<? endforeach ?>
		</tbody>
	</table>
</div>

<h2>Sources (<?= $index->getSourcesCountLabel() ?>)</h2>

<form method="post" action class="table-wrapper">
	<table class="sources">
		<thead>
			<tr>
				<th></th>
				<th class="title <?= $index->sourcesSorted == 'name' ? 'sorted' : '' ?>">Title</th>
				<th></th>
				<th data-sortable="-last_release" class="<?= $index->sourcesSorted == 'last_release' ? 'sorted' : '' ?>">Latest release</th>
				<th>Version</th>
				<? if ($index->showSourceDetectedInsteadOfChecked): ?>
					<th class="sorted">Detected</th>
				<? else: ?>
					<th data-sortable="-last_checked" class="<?= $index->sourcesSorted == 'last_checked' ? 'sorted' : '' ?>">Last checked</th>
				<? endif ?>
				<th data-sortable="-created_on" class="<?= $index->sourcesSorted == 'created_on' ? 'sorted' : '' ?>">Added</th>
				<th data-sortable="-finished" class="<?= $index->sourcesSorted == 'finished' ? 'sorted' : '' ?>">Finished</th>
				<? if ($index->deleting): ?>
					<th></th>
				<? endif ?>
			</tr>
		</thead>
		<tbody class="<?= $index->collapseUntracked ? 'hiding-untracked' : '' ?>">
			<? if (!count($index->sources)): ?>
				<tr class="empty">
					<td colspan="99"><?= $index->getNoSourcesMessage() ?></td>
				</tr>
			<? endif ?>
			<? $untrackeds = 0 ?>
			<? foreach ($index->sources as $source): ?>
				<? $untrackeds += $source->f95_id ? 0 : 1 ?>
				<? if ($index->collapseUntracked && $source->f95_id): ?>
					<? $index->collapseUntracked = false ?>
					<tr>
						<td colspan="8" id="show-untrackeds"><?= $untrackeds ?> untrackeds</td>
					</tr>
				<? endif ?>
				<tr
					class="
						<?= $index->collapseUntracked && !$source->f95_id ? 'untracked' : '' ?>
						<?= $index->hiliteSource == $source->id ? 'hilited' : '' ?>
						recency-<?= $source->last_release->fetch_recency ?? '' ?>
						<?= $source->status_prefix_class ?? '' ?>
						<?= $source->title_class ?>
					"
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
							<span class="installed-version <?= $source->installed_class ?>">(<?= html($source->installed) ?>)</span>
						<? endif ?>
						<a class="search-icon" href>&#128270;</a>
						<a class="edit-icon" href="?edit=<?= $source->id ?>">&#9998;</a>
						<? if ($source->f95_rating): ?>
							<span class="rating">&#9733;<?= round($source->f95_rating/10) ?></span>
						<? endif ?>
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
							<a href="https://www.patreon.com/<?= html($source->patreon_path) ?>" target="_blank"><img src="patreon.png" alt="Patreon" /></a>
						<? endif ?>
					</td>
					<td nowrap class="recent-<?= $source->last_release->recent_release ?? '' ?> <?= $source->not_release_date ? 'not-release-date' : '' ?> old-last-change-<?= $source->old_last_change ?>">
						<div class="cols">
							<span><?= $source->last_release->release_date ?? $source->last_release->thread_date ?? '' ?></span>
							<a class="sync" href="?sync=<?= $source->id ?>">&#8635;</a>
						</div>
					</td>
					<td nowrap tabindex="0" class="version" title="<?= html($source->last_release->version ?? '') ?>"><span><?= $source->last_release->cleaned_version ?? '' ?></span></td>
					<td nowrap>
						<? if ($source->last_release): ?>
							<div class="cols">
								<? if ($index->showSourceDetectedInsteadOfChecked): ?>
									<?= date('Y-m-d', $source->last_release->first_fetch_on) ?>
								<? else: ?>
									<span><?= date('Y-m-d', $source->last_release->last_fetch_on) ?></span>
								<? endif ?>
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
					<? if ($index->deleting): ?>
						<td><a href data-body="delete_source=<?= $source->id ?>" class="delete">x</a></td>
					<? endif ?>
				</tr>
			<? endforeach ?>
		</tbody>
	</table>
	<p><button>Save</button></p>
</form>
