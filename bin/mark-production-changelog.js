#! /bin/node

// This script is run by GitHub Actions CI

import fetch from 'node-fetch';

const CHANGELOG_BEARER_TOKEN = process.env.CHANGELOG_BEARER_TOKEN;
const RELEASE_ID = process.env.RELEASE_ID;
const CHANGELOG_ENDPOINT = 'https://public-api.wordpress.com/wp/v2/sites/wpvipchangelog.wordpress.com/posts';

const REST_END_PAGE_ERROR = 'rest_post_invalid_page_number';
const RELEASE_CHANNEL_STAGING = 267076;
const RELEASE_CHANNEL_PRODUCTION = 5905;
const MU_PLUGINS_TAG = 1784989;

const fetchPage = async page => {
	const headers = {
		Authorization: `Bearer ${CHANGELOG_BEARER_TOKEN}`,
	};
	const queryArgs = {
		'release-channel': RELEASE_CHANNEL_STAGING,
		tags: MU_PLUGINS_TAG,
		per_page: 100,
		status: 'draft,publish',
		page,
	}

	const argString = new URLSearchParams(queryArgs).toString();

	const result = await fetch(`${CHANGELOG_ENDPOINT}?${argString}`, { headers });
	if (!result.ok) {
		const errMessage = 'Failed to fetch posts';
		try {
			const body = await result.json();
			if (body && body.code === REST_END_PAGE_ERROR) {
				return [];
			}
		} catch (e) {
			console.error('Failed to parse response', e)
		}

		console.error(errMessage, result);
		throw new Error(errMessage)
	}

	return result.json();
};

const updateReleaseChannels = async (id, releaseChannels) => {
	const headers = {
		Authorization: `Bearer ${CHANGELOG_BEARER_TOKEN}`,
		'Content-Type': 'application/json',
	};
	const bodyObj = {
		'release-channel': releaseChannels,
	};
	const body = JSON.stringify(bodyObj);

	await fetch(`${CHANGELOG_ENDPOINT}/${id}`, {
		headers,
		method: "POST",
		body,
	});
};

const createProductionReleaseDraft = async (promotedPosts) => {

	let content = '<ul>';
	for (const {link, titleText} of promotedPosts) {
		content += `<li><a href="${link}">${titleText}</a></li>`
	}

	content += '</ul>'

	const headers = {
		Authorization: `Bearer ${CHANGELOG_BEARER_TOKEN}`,
		'Content-Type': 'application/json',
	};
	const title = `Mu-plugins release ${RELEASE_ID}`
	const bodyObj = {
		title,
		excerpt: title,
		content,

		status: 'draft',
		'release-channel': [RELEASE_CHANNEL_PRODUCTION],
		tags: [MU_PLUGINS_TAG],
	};
	const body = JSON.stringify(bodyObj);

	await fetch(`${CHANGELOG_ENDPOINT}`, {
		headers,
		method: "POST",
		body,
	});

	console.log(`Created a post: ${title}`);
}

const main = async () => {
	let pageResult = [];
	let page = 1;
	const promotedPosts = [];
	do {
		pageResult = await fetchPage(page);

		for (const post of pageResult) {
			const { id, status, link, title } = post;
			const releaseChannels = post['release-channel'];
			const titleText = title.rendered;

			if (releaseChannels.includes(RELEASE_CHANNEL_PRODUCTION)) {
				continue;
			}

			promotedPosts.push({link, titleText});
			console.log('Adding prod release schedule for post', id, status, releaseChannels);
			await updateReleaseChannels(id, [...releaseChannels, RELEASE_CHANNEL_PRODUCTION]);
			console.log('Updated:', id);
		}

		page++;
	} while (pageResult.length > 0);

	await createProductionReleaseDraft(promotedPosts);
};

main().catch((e) => console.error(e));

\