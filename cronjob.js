const puppeteer = require('puppeteer');
const request = require('request');
// const FormData = require('form-data');
const fs = require('fs');
// const {Readable} = require('stream');

// console.log(new Readable.from(['xxxxxxxxxxxxxxxxxxxxxxxxxxxx']));

const base = process.argv[2];
if (!base) {
	console.log("Missing `base url` argument");
	process.exit(1);
}

const wait = (ms, out) => new Promise(resolve => {
	setTimeout(resolve, ms, out);
});

function getUrls() {
	return new Promise(resolve => {
		request(`${base}/scraper-start.php`, {json: true}, (err, rsp, body) => {
			resolve(body.urls);
		});
	});
}

function sendResponse(id, html , url) {
// console.log(id, html.length, url);
	fs.writeFileSync('/tmp/page.html', html);

	return new Promise(resolve => {
		request.post({
			url: `${base}/scraper-save.php`,
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
	console.log(`${base}/`);
	console.log('');

console.time('getUrls');
	const urls = await getUrls();
	console.log('urls', urls.length);
console.timeEnd('getUrls');

	const browser = await puppeteer.launch({
		defaultViewport: {width: 1218, height: 650},
		headless: true,
		// args: [
		// 	'--no-sandbox',
		// 	'--disable-dev-shm-usage',
		// ],
	});

	const page = await browser.newPage();

	async function logIn() {
		await page.goto('https://f95zone.to/login/');

		await page.type('[name="login"]', 'majdaddin');
		await page.type('[name="password"]', 'oeleboele1');
		await page.evaluate(function() {
			document.querySelector('[name="remember"]').closest('label').click();
		});

		await Promise.all([
			page.waitForNavigation(),
			page.click('[type="submit"]', {delay: Math.random() * 400}),
		]);

		const loggedIn = await page.$('[href="/account/"]') != null;
		return loggedIn;
	}

console.time('logIn');
	const loggedIn = await logIn();
console.timeEnd('logIn');
	console.log('loggedIn', loggedIn);
	if (!loggedIn) {
		process.exit(1);
	}

	const total = urls.length;
	var news = 0;
	for ( let i = 0; i < urls.length; i++ ) {
		const [id, url] = urls[i];
console.log(id, url);
		await page.goto(url);
		const body = await page.content();

		const saved = await sendResponse(id, body, page.url());
		if ( saved && saved.new ) news++;
		console.log(`${i+1} / ${total}`);

		await wait(Math.random() * 5000);
	}

	console.log(`^ ${news} new releases`);

	await browser.close();
})();
