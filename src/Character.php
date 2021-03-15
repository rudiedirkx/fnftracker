<?php

namespace rdx\f95;

class Character extends Model {

	static public $_table = 'characters';

	protected function relate_source() {
		return $this->to_one(Source::class, 'source_id');
	}

}
