<?php

namespace rdx\f95;

class Character extends Model {

	static public $_table = 'characters';

	public function deleteImage() : void {
		if ($this->filepath) {
			@unlink($this->filepath);
		}
	}

	protected function relate_source() {
		return $this->to_one(Source::class, 'source_id');
	}

	protected function get_filepath() : ?string {
		if (CHARS_DIR && file_exists($filepath = __DIR__ . '/../' . CHARS_DIR . '/' . $this->id . '.jpg')) {
			return realpath($filepath);
		}
		return null;
	}

	protected function get_public_path() : ?string {
		if ($this->filepath) {
			return CHARS_DIR . '/' . $this->id . '.jpg';
		}
		return null;
	}

	public function __toString() {
		return (string) $this->name;
	}

}
