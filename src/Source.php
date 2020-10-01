<?php

namespace rdx\f95;

class Source extends Model {

	static public $_table = 'sources';

	protected function relate_last_fetch() {
		return $this->to_first(Fetch::class, 'source_id')
			->where('id in (select max(id) from fetches group by source_id)');
	}

}
