const puppeteer = require('puppeteer');
const request = require('request');
const commandLineArgs = require('command-line-args')
// const FormData = require('form-data');
const fs = require('fs');
// const {Readable} = require('stream');
// const cfg = require('./env.js');
let cfg = {};

const options = commandLineArgs([
	{name: 'test', type: Number, defaultValue: 0},
]);
options.test ??= 1;

const wait = (ms, out) => new Promise(resolve => {
	setTimeout(resolve, ms, out);
});

function getUrls() {
	return new Promise(resolve => {
		request(`${cfg.baseUrl}/scraper-start.php`, {json: true}, (err, rsp, body) => {
			resolve(body.urls);
		});
	});
}

function sendResponse(id, html , url) {
// console.log(id, html.length, url);
	fs.writeFileSync('/tmp/page.html', html);

	return new Promise(resolve => {
		request.post({
			url: `${cfg.baseUrl}/scraper-save.php`,
			formData: {
				id,
				url,
				html: fs.createReadStream('/tmp/page.html'),
				// html: {
				// 	value: new Readable.from([html]),
				// 	options: {
				// 		filename: 'page.html',
				// 		contentType: 'text/html',
				// 	},
				// },
			},
			json: true,
		}, (err, rsp, body) => {
// console.log(body);
			resolve(body);
		});
	});
}

(async () => {
	try {
		cfg = await import('../env.js');
	}
	catch (ex) {
		cfg = await import('./env.js');
	}

	console.log(`${cfg.baseUrl}/ - ${cfg.f95Url}/`);
	console.log('');

	console.log('test', options.test);
console.time('getUrls');
	let urls = await getUrls();
console.timeEnd('getUrls');
	console.log('urls', urls.length);

	const browser = await puppeteer.launch({
		defaultViewport: {width: 1218, height: 650},
		headless: 'new',
		// args: [
		// 	'--no-sandbox',
		// 	'--disable-dev-shm-usage',
		// ],
	});

	const page = await browser.newPage();

	async function logIn() {
		await page.goto(`${cfg.f95Url}/login/`);

		await page.type('[name="login"]', cfg.username);
		await page.type('[name="password"]', cfg.password);
		await page.evaluate(function() {
			document.querySelector('[name="remember"]').closest('label').click();
		});

		// await page.screenshot({path: 'screenshot-1.jpg'});

		await Promise.all([
			page.waitForNavigation(),
			page.click('[type="submit"]', {delay: Math.random() * 400}),
		]);

		// await page.screenshot({path: 'screenshot-2.jpg'});

		const loggedIn = await page.$('[href="/account/"]') != null;
		return loggedIn;
	}

console.time('logIn');
	const loggedIn = await logIn();
console.timeEnd('logIn');
	console.log('loggedIn', loggedIn);
	if (!loggedIn || options.test == 1) {
		process.exit(1);
	}
	console.log('');

	if (options.test > 1) {
		urls.splice(2, 9999);
		console.log('OVERRIDE urls', urls.length);
		console.log('');
	}

	const total = urls.length;
	var news = 0;
	for ( let i = 0; i < urls.length; i++ ) {
		if (i > 0) {
			await wait(Math.random() * 5000);
		}

		const [id, url] = urls[i];
console.log(id, url);
		try {
			const rsp = await page.goto(url);
			if (rsp && rsp.status() == 200) {
				const body = await page.content();

				const saved = await sendResponse(id, body, page.url());
console.log('saved', saved);
				if ( saved && saved.new ) news++;
			}
			else {
				console.log('ERROR', 'http code', rsp.status());
			}
		}
		catch (ex) {
			console.log('ERROR', ex);
		}
		console.log(`${i+1} / ${total}`);
	}

	console.log(`^ ${news} new releases`);

	await browser.close();
})();
