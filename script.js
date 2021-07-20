// window.addEventListener('load', e => setTimeout(() => {

const body = document.body;

const debouce = function(delay, callback) {
	var timer = -1;
	return function(...args) {
		clearTimeout(timer);
		timer = setTimeout(() => callback.apply(this, args), delay);
	};
};

document.querySelector('input[name="f95_id"]').addEventListener('input', function(e) {
	const m = this.value.match(URL_PATTERN);
	if (m) {
		this.value = m[1];
	}
});

const sortHandle = function(e) {
	const i = this.cellIndex;
	const tbl = this.closest('table');
	let rows = Array.from(tbl.querySelectorAll('tbody > tr:not(.hidden-rows):not(.description)'));
	rows.sort((a, b) => a.cells[i].textContent < b.cells[i].textContent ? 1 : -1);
	this.dataset.sortable === 'asc' && (rows = rows.reverse());
	const tb = tbl.createTBody();
	rows.forEach(tr => (tr.nextElementSibling && tr.nextElementSibling.classList.contains('description') ? [tr, tr.nextElementSibling] : [tr]).forEach(tr => tb.append(tr)));
	tbl.querySelectorAll('.sorted').forEach(th => th.classList.remove('sorted'));
	this.classList.add('sorted');
	tbl.classList.add('sorting');
};
document.querySelectorAll('th[data-sortable]').forEach(el => el.addEventListener('click', sortHandle));

const priorityHandle = function(e) {
	const tr = this.closest('tr');
	const i = PRIORITIES.indexOf(parseInt(this.textContent.trim()));
	const nxt = PRIORITIES[(i-1+PRIORITIES.length) % PRIORITIES.length];
	this.querySelector('output').value = this.querySelector('input').value = tr.dataset.priority = nxt;
};
document.querySelectorAll('.sources td.priority').forEach(el => el.addEventListener('click', priorityHandle));

const hiddenHandle = function(e) {
	this.closest('table').classList.add('showing-hidden-rows');
	this.closest('tr').remove();
};
document.querySelectorAll('tr.hidden-rows:not(.always) td').forEach(el => el.addEventListener('click', hiddenHandle));

const over = function(e) {
	const url = this.closest('tr').dataset.banner;
	if (!url) return;
	body.style.setProperty('--banner', `url('${url}')`);
	body.classList.add('show-banner');
};
const out = function(e) {
	body.classList.remove('show-banner');
};
document.querySelectorAll('tr[data-banner] span.title-name').forEach(el => {
	el.addEventListener('mouseover', over);
	el.addEventListener('mouseout', out);
});

const search = document.querySelector('input[type="search"]');
search.addEventListener('input', debouce(200, function(e) {
	const q = this.value.toLowerCase().replace(/(^[\s|]+|[\s|]+$)/g, '');
	const re = new RegExp(q, 'i');
	document.body.classList.toggle('searching', q != '');
	const rows = document.querySelectorAll('tr[data-search]');
	rows.forEach(tr => tr.hidden = q && !re.test(tr.dataset.search));
}));
search.dispatchEvent(new CustomEvent('input'));
document.addEventListener('keyup', function(e) {
	if (e.code == 'Slash' && document.activeElement.matches('body, a, button, td[tabindex]')) {
		search.focus();
		search.select();
	}
});
const searchHandle = function(e) {
	e.preventDefault();
	search.value = this.dataset.query || this.closest('tr').querySelector('.title-name').textContent.split('(')[0].trim();
	search.focus();
	search.dispatchEvent(new CustomEvent('input'));
};
document.querySelectorAll('.search-icon').forEach(el => el.addEventListener('click', searchHandle));

document.querySelectorAll('tr.description').forEach(el => {
	const name = el.previousElementSibling.querySelector('.title-name');
	var timer = 0;
	name.addEventListener('mouseover', e => {
		clearTimeout(timer);
		timer = setTimeout(() => {
			el.classList.add('show-description');
		}, 250);
	});
	name.addEventListener('mouseout', e => {
		clearTimeout(timer);
		document.querySelectorAll('tr.show-description').forEach(el => el.classList.remove('show-description'));
	});
});

document.querySelector('.release-stats').addEventListener('click', e => {
	const td = e.target.closest('tr[data-releases] td[data-priority]');
	search.value = `_p${td.dataset.priority}_r${td.parentNode.dataset.releases}_`;
	search.focus();
	search.dispatchEvent(new CustomEvent('input'));
});

Array.from(document.querySelectorAll('.hilited, .hilited *')).some(el => el.focus() || el == document.activeElement);

// }, 200));
