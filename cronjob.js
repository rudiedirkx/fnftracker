const puppeteer = require('puppeteer');
const request = require('request');
const fs = require('fs');

const wait = (ms, out) => new Promise(resolve => {
	setTimeout(resolve, ms, out);
});

(async () => {
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

	function getUrls() {
		return new Promise(resolve => {
			request('https://fnftracker.homeblox.nl/scraper-start.php', {json: true}, (err, rsp, body) => {
				resolve(body.urls);
			});
		});
	}

console.time('getUrls');
	const urls = await getUrls();
	console.log('urls', urls.length);
console.timeEnd('getUrls');

console.time('logIn');
	const loggedIn = await logIn();
console.timeEnd('logIn');
	console.log('loggedIn', loggedIn);

	const total = urls.length;
	async function fetch() {
		const [id, url] = urls.shift();
		console.log(`${total-urls.length} / ${total}  -  [${id}] ${url}`);

		await page.goto(url);
		const body = await page.content();

		fs.writeFile('rsp.html', body, (err) => {
			console.log('writeFile', err);
		});

		// urls.length && setTimeout(fetch, Math.random() * 5000);
	}
	urls.length && fetch();

	await browser.close();
})();
