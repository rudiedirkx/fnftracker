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
const searchHandle = function(value) {
	if (value.length == 1 && value != '*') return;

	return fetch(new Request('?search=' + encodeURIComponent(value.trim()), {
		headers: {"Accept": 'html/partial'},
	})).then(x => x.text()).then(html => {
		tables.innerHTML = html;
		bannerOut();
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
tables.addEventListener('click', eventIf('.search-icon', function(e) {
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
		alert(txt.trim());
		searchHandle(search.value);
	});
}));

(function(btn) {
	if (!btn) return;
	btn.addEventListener('click', e => {
		document.querySelector('.hiding-untracked').classList.remove('hiding-untracked');
		btn.closest('tr').remove();
	});
})(document.querySelector('#show-untrackeds'));

document.querySelector('#stats').addEventListener('click', eventIf('[data-pr-search]', e => {
	search.value = e.target.dataset.prSearch;
	search.focus();
	searchHandle(search.value);
}));

Array.from(document.querySelectorAll('.hilited, .hilited *')).some(el => el.focus() || el == document.activeElement);

const cf = document.querySelector('input[name="char_file"]');
if (cf) {
	const ci = document.querySelector('#char_image');
	const cc = document.querySelector('input[name="char_cutout"]');
	var co;

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
		cc.value = ([x, y, s]).join(',');
	});
	document.addEventListener('mouseup', function(e) {
		e.preventDefault();
		ci.classList.toggle('dragging', dragging = false);
	});

	cf.addEventListener('change', function(e) {
		cc.required = Boolean(this.value);
		cc.value = '';

		if (this.value) {
			const f = this.files[0];
			const img = document.createElement('img');
			img.src = URL.createObjectURL(f);
			// this.value = '';

			ci.innerHTML = '<div class="cutout"></div>';
			ci.append(img);
			co = ci.querySelector('.cutout');
		}
		else {
			ci.innerHTML = '';
			co = null;
		}
	});
}
