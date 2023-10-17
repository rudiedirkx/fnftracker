const body = document.body;
const tables = document.querySelector('#tables');

const eventIf = function(sel, cb) {
	return function(e) {
		const el = e.target.closest(sel);
		if (el) {
			cb.call(el, e);
		}
	};
};

document.querySelector('input[name="f95_id"]').addEventListener('input', function(e) {
	const m = this.value.match(URL_PATTERN);
	if (m) {
		setTimeout(() => this.value = m[1], 100);
	}
});

const sortHandle = function(e) {
	const sorter = this.dataset.sortable;
	search.value = sorter;
	search.focus();
	searchHandle(search.value); // .then(_ => document.querySelector(`[data-sortable="${sorter}"]`).classList.add('sorted'));
};
tables.addEventListener('click', eventIf('th[data-sortable]', sortHandle));

const priorityHandle = function(e) {
	const tr = this.closest('tr');
	const i = PRIORITIES.indexOf(parseInt(this.textContent.trim()));
	const nxt = PRIORITIES[(i-1+PRIORITIES.length) % PRIORITIES.length];
	this.querySelector('output').value = this.querySelector('input').value = tr.dataset.priority = nxt;
};
tables.addEventListener('click', eventIf('.sources td.priority', priorityHandle));

const bannerOver = function(e) {
	const url = this.closest('tr').dataset.banner;
	if (!url) return;
	body.style.setProperty('--banner', `url('${url}')`);
	body.classList.add('show-banner');
};
const bannerOut = function(e) {
	body.classList.remove('show-banner');
};
tables.addEventListener('mouseover', eventIf('tr[data-banner] span.title-name', bannerOver));
tables.addEventListener('mouseout', eventIf('tr[data-banner] span.title-name', bannerOut));
tables.addEventListener('mouseout', bannerOut);

const search = document.querySelector('input[type="search"]');
let searchAborter = null;
const searchHandle = function(value) {
	if (value.length == 1 && value != '*') return;

	if (searchAborter) searchAborter.abort('search');
	searchAborter = new AbortController();
	return fetch(new Request('?search=' + encodeURIComponent(value.trim()), {
		signal: searchAborter.signal,
		headers: {"Accept": 'html/partial'},
	})).then(x => x.text()).then(html => {
		searchAborter = null;
		tables.innerHTML = html;
		bannerOut();
	}).catch(ex => {
		if (!ex.message.includes(' aborted ')) console.warn(ex);
	});
};
search.addEventListener('input', function(e) {
	searchHandle(this.value);
});
// search.dispatchEvent(new CustomEvent('input'));
document.addEventListener('keyup', function(e) {
	if (e.code == 'Slash' && document.activeElement.matches('body, a, button, td[tabindex]')) {
		search.focus();
		search.select();
	}
});
const searchIconHandle = function(icon) {
	search.value = icon.dataset.query || icon.closest('tr').querySelector('.title-name').textContent.split('(')[0].trim();
	search.focus();
	searchHandle(search.value);
	// search.dispatchEvent(new CustomEvent('input'));
};
document.addEventListener('click', eventIf('.search-icon', function(e) {
	e.preventDefault();
	searchIconHandle(this);
}));

tables.addEventListener('click', eventIf('a.delete', function(e) {
	e.preventDefault();
	if (!confirm('Delete this row forever?')) return;

	fetch(new Request('?', {
		method: 'post',
		body: this.dataset.body,
		headers: {"Content-type": 'application/x-www-form-urlencoded'},
	})).then(x => x.text()).then(txt => {
		if (txt.trim()) alert(txt.trim());
		searchHandle(search.value);
	});
}));

tables.addEventListener('change', eventIf('input.editing-release', function(e) {
	e.preventDefault();

	const inp = this;
	const data = new FormData();
	data.append('edit_release', this.dataset.fetch);
	data.append(this.dataset.name, this.value);
	fetch(new Request('?', {
		method: 'post',
		body: data,
	})).then(x => x.text()).then(txt => {
		if (txt.trim()) {
			alert(txt.trim());
			inp.value = inp.defaultValue;
		}
	});
}));

tables.addEventListener('click', eventIf('#show-untrackeds', function(e) {
	document.querySelector('.hiding-untracked').classList.remove('hiding-untracked');
	this.closest('tr').remove();
}));

document.querySelector('#stats').addEventListener('click', eventIf('[data-pr-search]', e => {
	search.value = e.target.dataset.prSearch;
	search.focus();
	searchHandle(search.value);
}));

Array.from(document.querySelectorAll('.hilited, .hilited *')).some(el => el.focus() || el == document.activeElement);

document.querySelector('table.characters').addEventListener('click', eventIf('button.reupload-character', function(e) {
	this.previousElementSibling.checked = true;
	const nameEl = document.querySelector('input[name="char_name"]');
	nameEl.value = this.dataset.name;
	document.querySelector('input[name="char_role"]').value = this.dataset.role;
	nameEl.focus();
}));

const cf = document.querySelector('input[name="char_file"]');
if (cf) {
	const ci = document.querySelector('#char_image');
	const cc = document.querySelector('input[name="char_cutout"]');
	var co;
	var scale = 1.0;

	var dragging = false;
	var x, y, w, h, s;
	ci.addEventListener('mousedown', function(e) {
		e.preventDefault();
		if (e.button != 0) return;

		[x, y] = [e.offsetX, e.offsetY];
		ci.classList.toggle('dragging', dragging = true);
	});
	ci.addEventListener('mousemove', function(e) {
		e.preventDefault();
		if (!dragging) return;

		[w, h] = [e.offsetX - x, e.offsetY - y];
		s = Math.round((w + h) / 2);
		co.style.left = `${x}px`;
		co.style.top = `${y}px`;
		co.style.width = `${s}px`;
		co.style.height = `${s}px`;
		cc.value = ([Math.round(x / scale), Math.round(y / scale), Math.round(s / scale)]).join(',');
	});
	document.addEventListener('mouseup', function(e) {
		e.preventDefault();
		ci.classList.toggle('dragging', dragging = false);
	});

	function handleDrop() {
		cc.required = Boolean(this.value);
		cc.value = '';

		if (this.value) {
			const f = this.files[0];
			const img = document.createElement('img');
			img.src = URL.createObjectURL(f);
			// this.value = '';
			img.onload = function() {
				scale = this.clientWidth / this.naturalWidth;
				console.log(scale);
			};

			ci.innerHTML = '<div class="cutout"></div>';
			ci.append(img);
			co = ci.querySelector('.cutout');
		}
		else {
			ci.innerHTML = '';
			co = null;
		}
	}
	cf.addEventListener('change', handleDrop);

	window.onresize = function() {
		const img = ci.querySelector('img');
		if (img) {
			scale = img.clientWidth / img.naturalWidth;
			console.log(scale);
		}
	};

	const fs = cf.closest('form');
	var dragging2 = 0;
	fs.ondragover = e => {
		e.preventDefault();
		const file = e.dataTransfer.items[0];
		if (file && file.kind == 'file' && file.type.startsWith('image/')) {
			clearTimeout(dragging2);
			fs.classList.add('droppable');
		}
	};
	fs.ondrop = e => {
		e.preventDefault();
		cf.files = e.dataTransfer.files;
		handleDrop.call(cf);
		fs.classList.remove('droppable');
	};
	fs.ondragleave = e => {
		dragging2 = setTimeout(_ => fs.classList.remove('droppable'), 50);
	};
}
